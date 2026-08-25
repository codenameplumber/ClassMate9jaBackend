<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use Illuminate\Support\Collection;

/**
 * Everything the teacher screens need to know about a class.
 *
 * All of it is derived from marked scripts rather than stored as a running
 * total, so a re-mark or an approval instantly changes every number on every
 * screen with nothing to invalidate.
 */
class ClassStats
{
    public static function for(Classroom $classroom): array
    {
        $submissions = static::submissions($classroom);
        $marked = $submissions->filter(fn ($s) => $s->max_score > 0);

        $average = $marked->isEmpty()
            ? 0
            : (int) round($marked->avg(fn ($s) => $s->score() / $s->max_score * 100));

        $perStudent = static::averagesByStudent($classroom);

        return [
            'size' => $classroom->students()->count(),
            'average' => $average,
            'submitted' => $submissions->count(),
            'approved' => $submissions->where('status', 'approved')->count(),
            'pending' => $submissions->where('status', '!=', 'approved')->count(),
            'at_risk' => $perStudent->filter(fn ($avg) => $avg < 45)->count(),
        ];
    }

    /** Every student in the class with their marked average, searchable and sortable. */
    public static function roster(Classroom $classroom, string $search = '', string $sort = 'name'): Collection
    {
        $averages = static::averagesByStudent($classroom);
        $counts = static::submissions($classroom)->groupBy('user_id')->map->count();

        $rows = $classroom->students()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->get()
            ->map(fn ($student) => [
                'student' => $student,
                'average' => $averages[$student->id] ?? 0,
                'submissions' => $counts[$student->id] ?? 0,
                'at_risk' => ($averages[$student->id] ?? 0) < 45 && ($counts[$student->id] ?? 0) > 0,
            ]);

        return $sort === 'average'
            ? $rows->sortBy('average')->values()
            : $rows->sortBy(fn ($row) => $row['student']->name)->values();
    }

    public static function atRisk(Classroom $classroom, int $limit = 5): Collection
    {
        return static::roster($classroom, '', 'average')
            ->filter(fn ($row) => $row['at_risk'])
            ->take($limit)
            ->values();
    }

    /**
     * Per-question performance across the class, weakest first.
     *
     * Question level rather than topic level on purpose: "eighteen students
     * failed question 4" tells a teacher exactly what to re-teach on Monday,
     * where a subject-level average tells them nothing they can act on.
     */
    public static function topicHealth(Classroom $classroom): Collection
    {
        $answers = SubmissionAnswer::query()
            ->with('question.assignment.topic')
            ->whereHas('submission.assignment', fn ($q) => $q->where('classroom_id', $classroom->id))
            ->get();

        if ($answers->isEmpty()) {
            return collect();
        }

        return $answers
            ->groupBy('assignment_question_id')
            ->map(function ($group) {
                $question = $group->first()->question;
                if (! $question) {
                    return null;
                }

                $max = max(1, $question->max_mark);
                $average = (int) round($group->avg(fn ($a) => $a->mark() / $max * 100));

                return [
                    'topic' => $question->assignment?->topic?->title
                        ? "Q{$question->position} · " . $question->assignment->topic->title
                        : "Q{$question->position} · " . str($question->prompt)->limit(46),
                    'average' => $average,
                    'failing' => $group->filter(fn ($a) => $a->mark() / $max < 0.5)->count(),
                ];
            })
            ->filter()
            ->sortBy('average')
            ->values();
    }

    /** student id => average percent across their marked scripts */
    public static function averagesByStudent(Classroom $classroom): Collection
    {
        return static::submissions($classroom)
            ->filter(fn ($s) => $s->max_score > 0)
            ->groupBy('user_id')
            ->map(fn ($group) => (int) round(
                $group->avg(fn ($s) => $s->score() / $s->max_score * 100)
            ));
    }

    private static function submissions(Classroom $classroom): Collection
    {
        static $cache = [];

        return $cache[$classroom->id] ??= Submission::query()
            ->whereHas('assignment', fn ($q) => $q->where('classroom_id', $classroom->id))
            ->get();
    }
}
