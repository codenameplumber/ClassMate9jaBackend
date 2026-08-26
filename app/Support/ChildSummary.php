<?php

namespace App\Support;

use App\Models\Submission;
use App\Models\User;

/**
 * What a parent actually wants to know, computed from marked work and recorded
 * study time — never from anything the child self-reports.
 */
class ChildSummary
{
    public static function for(User $student): array
    {
        $state = $student->state?->payload ?? [];
        $week = $state['week'] ?? [0, 0, 0, 0, 0, 0, 0];

        $sessions = $student->studySessions()
            ->where('occurred_on', '>=', now()->startOfWeek()->toDateString())
            ->get();

        // Prefer real recorded sessions; fall back to the device's own weekly
        // array only when the student has not synced any sessions yet.
        $minutes = $sessions->isNotEmpty() ? (int) $sessions->sum('minutes') : (int) array_sum($week);

        if ($sessions->isNotEmpty()) {
            // occurred_on is cast to a Carbon date, so grouping has to be done on
            // the formatted string. Comparing the Carbon instance to a date
            // string silently matches nothing, which showed up as a parent
            // report reading "249 minutes" and "0 of 7 days studied" at once.
            $byDay = $sessions->groupBy(fn ($session) => $session->occurred_on->toDateString());

            $week = collect(range(0, 6))->map(function ($i) use ($byDay) {
                $day = now()->startOfWeek()->addDays($i)->toDateString();

                return (int) ($byDay[$day] ?? collect())->sum('minutes');
            })->all();
        }

        $daysStudied = collect($week)->filter(fn ($m) => $m >= 20)->count();

        $submissions = Submission::with('assignment')
            ->where('user_id', $student->id)
            ->where('status', 'approved')
            ->get();

        $attempts = $student->attempts()->limit(6)->get();

        $marks = $submissions->filter(fn ($s) => $s->max_score > 0)
            ->map(fn ($s) => $s->score() / $s->max_score * 100)
            ->concat($attempts->filter(fn ($a) => $a->total > 0)->map(fn ($a) => $a->percent()));

        $average = $marks->isEmpty() ? 0 : (int) round($marks->avg());

        $subjects = collect($state['subjects'] ?? [])
            ->map(fn ($data, $slug) => [
                'slug' => $slug,
                'mastery' => $data['mastery'] ?? 0,
                'done' => $data['done'] ?? 0,
                'total' => $data['total'] ?? 0,
                'weak' => $data['weak'] ?? [],
            ])
            ->sortByDesc('mastery')
            ->values();

        $weakest = $subjects->last();

        return [
            'student' => $student,
            'week' => $week,
            'minutes' => $minutes,
            'days_studied' => $daysStudied,
            'streak' => $state['streak'] ?? 0,
            'best_streak' => $state['bestStreak'] ?? 0,
            'points' => $state['points'] ?? 0,
            'average' => $average,
            'subjects' => $subjects,
            'weakest' => $weakest,
            'submissions' => $submissions,
            'attempts' => $attempts,
            'on_track' => $daysStudied >= 4 && $minutes >= 120,
        ];
    }

    /** WAEC-style band for a percentage. */
    public static function band(int $pct): array
    {
        return match (true) {
            $pct >= 75 => ['A1', 'Excellent', 'success'],
            $pct >= 70 => ['B2', 'Very Good', 'success'],
            $pct >= 65 => ['B3', 'Good', 'success'],
            $pct >= 60 => ['C4', 'Credit', 'brand'],
            $pct >= 55 => ['C5', 'Credit', 'brand'],
            $pct >= 50 => ['C6', 'Credit', 'brand'],
            $pct >= 45 => ['D7', 'Pass', 'warn'],
            $pct >= 40 => ['E8', 'Pass', 'warn'],
            default => ['F9', 'Fail', 'danger'],
        };
    }

    public static function smsText(array $summary): string
    {
        $first = $summary['student']->firstName();
        $hours = intdiv($summary['minutes'], 60);
        $mins = $summary['minutes'] % 60;
        $weak = $summary['weakest']['slug'] ?? 'none';

        return "ClassMate 9ja: {$first} studied {$hours}hrs {$mins}mins across "
            . "{$summary['days_studied']} days this week. Streak: {$summary['streak']} days. "
            . "Weakest subject: {$weak}. Ask {$first} to explain one topic to you today.";
    }
}
