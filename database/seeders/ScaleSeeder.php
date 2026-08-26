<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentQuestion;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Classroom;
use App\Models\ParentLink;
use App\Models\School;
use App\Models\StudentState;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\SubmissionAnswer;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A platform at real-school scale.
 *
 * The demo seeder proves the marking screen. This one proves the platform:
 * three schools, several teachers, a couple of hundred students spread across
 * classes, and enough marked history that averages, grade spreads and topic
 * analysis are computed from real distributions rather than three rows.
 *
 * Everything is deterministic — names, abilities, dates all derive from a hash
 * of the record — so the same database comes out of every run and a demo never
 * shifts under you.
 */
class ScaleSeeder extends Seeder
{
    private const FIRST_NAMES = [
        'Chidera', 'Aisha', 'Tunde', 'Ngozi', 'Ibrahim', 'Halima', 'Segun', 'Blessing',
        'Yakubu', 'Chioma', 'Musa', 'Temitope', 'Uche', 'Zainab', 'Kelechi', 'Adaeze',
        'Bashir', 'Funmilayo', 'Chinedu', 'Maryam', 'Olumide', 'Ifeoma', 'Suleiman',
        'Titilayo', 'Godwin', 'Amina', 'Peter', 'Bukola', 'Nnamdi', 'Rukayat',
        'Emeka', 'Fatima', 'Gbenga', 'Chiamaka', 'Sadiq', 'Yewande', 'Obinna',
        'Hauwa', 'Damilola', 'Ekene', 'Kabiru', 'Folasade', 'Ikechukwu', 'Nafisa',
        'Oluwaseun', 'Chinelo', 'Abubakar', 'Morenike', 'Somtochukwu', 'Zubairu',
    ];

    private const LAST_NAMES = [
        'Okonkwo', 'Bello', 'Adeyemi', 'Eze', 'Musa', 'Yusuf', 'Ogunleye', 'Etim',
        'Danjuma', 'Obi', 'Abdullahi', 'Balogun', 'Nnamdi', 'Sani', 'Anyanwu',
        'Ugwu', 'Lawal', 'Oyelaran', 'Okafor', 'Garba', 'Fashola', 'Chukwu', 'Bala',
        'Akande', 'Ubah', 'Shehu', 'Enahoro', 'Ajayi', 'Salami', 'Adebayo',
        'Nwachukwu', 'Ibrahim', 'Oladipo', 'Onyeka', 'Aliyu', 'Adesina', 'Okoro',
    ];

    private const SCHOOLS = [
        ['Government Secondary School, Wuse', 'FCT', 'Abuja Municipal', 'public'],
        ['Command Day Secondary School, Ikeja', 'Lagos', 'Ikeja', 'public'],
        ['Holy Trinity College, Enugu', 'Enugu', 'Enugu North', 'private'],
    ];

    /** Deterministic pseudo-random, so every run produces the same school. */
    private function seed(string $key): int
    {
        return abs(crc32($key));
    }

    public function run(): void
    {
        $subjects = Subject::pluck('id', 'slug');
        $coreSubjectIds = Subject::where('is_core', true)->pluck('id')->take(5)->all();

        $totals = ['schools' => 0, 'teachers' => 0, 'students' => 0, 'parents' => 0,
            'classes' => 0, 'assignments' => 0, 'scripts' => 0, 'attempts' => 0];

        foreach (self::SCHOOLS as $index => [$name, $state, $lga, $type]) {
            $school = School::firstOrCreate(
                ['name' => $name],
                ['state' => $state, 'lga' => $lga, 'type' => $type]
            );
            $totals['schools']++;

            // Two teachers per school, each running two classes.
            foreach (['maths', 'english'] as $slot => $subjectSlug) {
                $teacher = $this->teacherFor($school, $subjectSlug, $index, $slot);
                $totals['teachers']++;

                // Both levels this subject is taught at in the demo school.
                foreach ([['SS2', 'A'], ['SS1', 'B']] as $arm => [$level, $letter]) {
                    $classroom = Classroom::firstOrCreate(
                        ['join_code' => strtoupper(substr($subjectSlug, 0, 3)) . "-{$index}{$slot}{$arm}"],
                        [
                            'school_id' => $school->id,
                            'teacher_id' => $teacher->id,
                            'subject_id' => $subjects[$subjectSlug] ?? null,
                            'name' => $level . $letter,
                            'level' => $level,
                            'room' => 'Block ' . chr(65 + $index) . ', Room ' . (($arm + 1) * 3),
                        ]
                    );
                    $totals['classes']++;

                    $students = $this->studentsFor($classroom, $school, $level, $coreSubjectIds, 20);
                    $totals['students'] += count($students);

                    // One parent for every third student — a realistic take-up rate.
                    foreach ($students as $i => $student) {
                        if ($i % 3 === 0) {
                            $this->parentFor($student);
                            $totals['parents']++;
                        }
                    }

                    $made = $this->assignmentsFor($classroom, $teacher, $subjectSlug, $students);
                    $totals['assignments'] += $made['assignments'];
                    $totals['scripts'] += $made['scripts'];

                    $totals['attempts'] += $this->practiceFor($students, $subjects);
                }
            }
        }

        $this->command->newLine();
        foreach ($totals as $label => $count) {
            $this->command->info(sprintf('  %-12s %s', $label, $count));
        }
    }

    /* ------------------------------------------------------------------ */

    private function teacherFor(School $school, string $subjectSlug, int $schoolIndex, int $slot): User
    {
        $titles = ['Mrs.', 'Mr.', 'Miss'];
        $s = $this->seed($school->name . $subjectSlug);
        $name = $titles[$s % 3] . ' '
            . self::FIRST_NAMES[$s % count(self::FIRST_NAMES)] . ' '
            . self::LAST_NAMES[($s >> 3) % count(self::LAST_NAMES)];

        $teacher = User::firstOrCreate(
            ['email' => "teacher{$schoolIndex}{$slot}@classmate9ja.ng"],
            [
                'name' => $name,
                'password' => 'password',
                'role' => 'teacher',
                'school_id' => $school->id,
                'subject_slug' => $subjectSlug,
                'email_verified_at' => now(),
                'onboarded_at' => now(),
                'status' => 'approved',
                'approved_at' => now(),
            ]
        );

        // Each of these teachers runs an SS2 and an SS1 arm of their subject.
        $subjectId = Subject::where('slug', $subjectSlug)->value('id');

        if ($subjectId) {
            $teacher->syncTeaching([$subjectId => ['SS1', 'SS2']]);
        }

        return $teacher;
    }

    /** @return User[] */
    private function studentsFor(Classroom $classroom, School $school, string $level, array $subjectIds, int $count): array
    {
        $students = [];

        for ($i = 0; $i < $count; $i++) {
            $s = $this->seed($classroom->join_code . $i);
            $name = self::FIRST_NAMES[$s % count(self::FIRST_NAMES)] . ' '
                . self::LAST_NAMES[($s >> 5) % count(self::LAST_NAMES)];

            $email = Str::slug(str_replace(' ', '.', $name)) . '.' . strtolower($classroom->join_code) . $i . '@demo.classmate9ja.ng';

            $student = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'role' => 'student',
                'school_id' => $school->id,
                'level' => $level,
                'locale' => ['en', 'pcm', 'both'][$s % 3],
                'goal' => ['waec', 'jamb', 'both', 'school'][$s % 4],
                'daily_goal' => [20, 30, 60][$s % 3],
                'email_verified_at' => now(),
                'onboarded_at' => now(),
            ]);

            $student->subjects()->syncWithoutDetaching($subjectIds);
            $classroom->students()->syncWithoutDetaching([$student->id]);

            $this->historyFor($student, $s);

            $students[] = $student;
        }

        return $students;
    }

    private function parentFor(User $student): void
    {
        $surname = Str::afterLast($student->name, ' ');
        $s = $this->seed('parent' . $student->email);
        $title = ['Mr.', 'Mrs.'][$s % 2];

        $parent = User::firstOrCreate(
            ['email' => 'parent.' . Str::before($student->email, '@') . '@demo.classmate9ja.ng'],
            [
                'name' => $title . ' ' . self::FIRST_NAMES[$s % count(self::FIRST_NAMES)] . ' ' . $surname,
                'password' => 'password',
                'role' => 'parent',
                'email_verified_at' => now(),
                'onboarded_at' => now(),
            ]
        );

        ParentLink::firstOrCreate(
            ['parent_id' => $parent->id, 'student_id' => $student->id],
            ['code' => $student->linkCode(), 'report_day' => 'sunday', 'verified_at' => now()]
        );
    }

    /** Study sessions and a progress blob, so parent screens have real numbers. */
    private function historyFor(User $student, int $s): void
    {
        if ($student->studySessions()->exists()) {
            return;
        }

        $week = [];
        for ($day = 0; $day < 7; $day++) {
            // Some students are consistent, some are weekend-only, some barely study.
            $minutes = match ($s % 4) {
                0 => 25 + (($s >> $day) % 40),
                1 => in_array($day, [5, 6], true) ? 60 + (($s >> $day) % 50) : 0,
                2 => (($s >> $day) % 3 === 0) ? 0 : 15 + (($s >> $day) % 30),
                default => (($s >> $day) % 5 === 0) ? 0 : 30 + (($s >> $day) % 25),
            };
            $week[] = $minutes;

            if ($minutes > 0) {
                StudySession::create([
                    'user_id' => $student->id,
                    'minutes' => $minutes,
                    'occurred_on' => now()->startOfWeek()->addDays($day)->toDateString(),
                    'source' => 'lesson',
                    'client_uuid' => (string) Str::uuid(),
                ]);
            }
        }

        $mastery = fn (int $offset) => max(18, min(96, 35 + (($s >> $offset) % 60)));

        StudentState::updateOrCreate(['user_id' => $student->id], [
            'payload' => [
                'points' => 400 + ($s % 4200),
                'streak' => $s % 22,
                'bestStreak' => 5 + ($s % 30),
                'minutesToday' => $week[6] ?? 0,
                'questionsAnswered' => 40 + ($s % 500),
                'lessonsDone' => 3 + ($s % 40),
                'week' => $week,
                'subjects' => [
                    'maths' => ['mastery' => $mastery(1), 'done' => $s % 24, 'total' => 24, 'weak' => ['Logarithms']],
                    'english' => ['mastery' => $mastery(4), 'done' => $s % 24, 'total' => 24, 'weak' => ['Oral English']],
                    'physics' => ['mastery' => $mastery(7), 'done' => $s % 24, 'total' => 24, 'weak' => ['Waves']],
                    'chemistry' => ['mastery' => $mastery(9), 'done' => $s % 24, 'total' => 24, 'weak' => ['Mole Concept']],
                    'biology' => ['mastery' => $mastery(11), 'done' => $s % 24, 'total' => 24, 'weak' => ['Genetics']],
                ],
                'badges' => ['first-lesson', 'streak-7'],
                'doneTopics' => [],
            ],
            'device_updated_at' => now(),
        ]);
    }

    /** Two marked papers plus one still open, per class. */
    private function assignmentsFor(Classroom $classroom, User $teacher, string $subjectSlug, array $students): array
    {
        $subject = Subject::where('slug', $subjectSlug)->first();

        // Topics belong to a level. Without this filter an SS1 class would be
        // set work from the SS2 scheme, because both share the same refs.
        $topics = Topic::where('subject_id', $subject?->id)
            ->where('level', $classroom->level)
            ->orderBy('week')
            ->take(6)
            ->get();

        if ($topics->isEmpty()) {
            return ['assignments' => 0, 'scripts' => 0];
        }

        $made = 0;
        $scripts = 0;

        foreach ([
            ['approved', $topics[0], 3],
            ['pending', $topics[2], 4],
            ['open', $topics[4], 4],
        ] as [$state, $topic, $questionCount]) {

            $assignment = Assignment::firstOrCreate(
                ['classroom_id' => $classroom->id, 'title' => "Week {$topic->week} · {$topic->title}"],
                [
                    'topic_id' => $topic->id,
                    'created_by' => $teacher->id,
                    'kind' => $state === 'approved' ? 'test' : 'classwork',
                    'ai_generated' => true,
                    'question_count' => $questionCount,
                    'due_on' => now()->addDays($state === 'open' ? 4 : -7)->toDateString(),
                    'published_at' => now()->subDays($state === 'open' ? 1 : 14),
                ]
            );
            $made++;

            if ($assignment->questions()->doesntExist()) {
                $this->questionsFor($assignment, $topic, $questionCount);
            }
            $assignment->refresh()->load('questions');

            if ($state === 'open') {
                continue;   // nothing submitted yet — students have work to do
            }

            foreach ($students as $student) {
                if (Submission::where('assignment_id', $assignment->id)->where('user_id', $student->id)->exists()) {
                    continue;
                }
                $this->scriptFor($assignment, $student, $teacher, $state === 'approved');
                $scripts++;
            }
        }

        return ['assignments' => $made, 'scripts' => $scripts];
    }

    private function questionsFor(Assignment $assignment, Topic $topic, int $count): void
    {
        $bank = $topic->classwork()->get();
        $position = 0;

        foreach ($bank->take($count - 1) as $item) {
            $position++;
            AssignmentQuestion::create([
                'assignment_id' => $assignment->id,
                'position' => $position,
                'kind' => 'objective',
                'prompt' => $item->prompt,
                'options' => $item->options,
                'answer_index' => $item->answer_index,
                'expected' => $item->options[$item->answer_index] ?? '',
                'max_mark' => 2,
            ]);
        }

        // Pad from the past-question bank if the topic had little classwork.
        if ($position < $count - 1) {
            foreach ($topic->subject->questions()->limit($count - 1 - $position)->get() as $item) {
                $position++;
                AssignmentQuestion::create([
                    'assignment_id' => $assignment->id,
                    'position' => $position,
                    'kind' => 'objective',
                    'prompt' => $item->prompt,
                    'options' => $item->options,
                    'answer_index' => $item->answer_index,
                    'expected' => $item->options[$item->answer_index] ?? '',
                    'max_mark' => 2,
                ]);
            }
        }

        // Every paper closes with a written answer — the part marking must prove
        // itself on, and the part a teacher actually has to read.
        AssignmentQuestion::create([
            'assignment_id' => $assignment->id,
            'position' => $position + 1,
            'kind' => 'written',
            'prompt' => "Explain {$topic->title} in your own words, and show one worked example.",
            'options' => null,
            'answer_index' => null,
            'expected' => 'A correct definition in the student\'s own words (2 marks), plus a '
                . 'worked example with the steps shown (3 marks). Give method marks even if '
                . 'the final answer is wrong.',
            'max_mark' => 5,
        ]);

        $assignment->update(['question_count' => $position + 1]);
    }

    private function scriptFor(Assignment $assignment, User $student, User $teacher, bool $approved): void
    {
        $s = $this->seed($student->email . $assignment->id);
        $ability = $s % 100;
        $questions = $assignment->questions;
        $maxScore = (int) $questions->sum('max_mark');

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'client_uuid' => (string) Str::uuid(),
            'max_score' => $maxScore,
            'status' => $approved ? 'approved' : 'marked',
            'submitted_at' => now()->subDays($approved ? 12 : 2)->addMinutes($s % 800),
            'approved_at' => $approved ? now()->subDays(10) : null,
            'approved_by' => $approved ? $teacher->id : null,
        ]);

        $total = 0;
        $needsReview = false;

        foreach ($questions as $index => $question) {
            if ($question->kind === 'written') {
                // Stronger students write more; the marker is least sure about
                // the middle band, which is exactly where a teacher is needed.
                $mark = (int) round($question->max_mark * min(1, max(0, ($ability - 15) / 75)));
                $confidence = $ability > 70 ? 92 : ($ability > 40 ? 74 : 88);

                SubmissionAnswer::create([
                    'submission_id' => $submission->id,
                    'assignment_question_id' => $question->id,
                    'answer_text' => $this->writtenAnswerFor($ability),
                    'ai_mark' => $mark,
                    'ai_confidence' => $confidence,
                    'ai_comment' => $this->commentFor($mark, $question->max_mark),
                ]);

                $total += $mark;
                if ($confidence < 80) {
                    $needsReview = true;
                }

                continue;
            }

            $correct = (($s >> $index) % 100) < $ability;
            $chosen = $correct
                ? $question->answer_index
                : ($question->answer_index === 0 ? 1 : 0);
            $mark = $correct ? $question->max_mark : 0;
            $total += $mark;

            SubmissionAnswer::create([
                'submission_id' => $submission->id,
                'assignment_question_id' => $question->id,
                'chosen' => $chosen,
                'answer_text' => $question->options[$chosen] ?? null,
                'ai_mark' => $mark,
                'ai_confidence' => 100,
                'ai_comment' => $correct
                    ? 'Correct.'
                    : 'The correct answer is: ' . ($question->options[$question->answer_index] ?? ''),
            ]);
        }

        $submission->update([
            'ai_score' => $total,
            'final_score' => $approved ? $total : null,
            'needs_review' => $needsReview,
        ]);
    }

    private function writtenAnswerFor(int $ability): string
    {
        return match (true) {
            $ability > 75 => 'The rule states that when you multiply, you add the indices. '
                . 'For example 2³ × 2⁵ = 2⁸ = 256, because you are multiplying eight twos together. '
                . 'I checked it by expanding both sides.',
            $ability > 45 => 'You add the powers when multiplying. 2³ × 2⁵ = 2⁸. '
                . 'I am not sure how to explain why, but that is the rule we were taught.',
            $ability > 25 => 'You add the numbers together to get the answer, like 3 + 5 = 8.',
            default => 'I did not understand this one.',
        };
    }

    private function commentFor(int $mark, int $max): string
    {
        $ratio = $max > 0 ? $mark / $max : 0;

        return match (true) {
            $ratio >= 0.9 => 'Clear definition and a correct worked example. Full marks.',
            $ratio >= 0.6 => 'Right rule and right answer, but the explanation of why it works is thin. '
                . 'Show the expansion next time.',
            $ratio >= 0.3 => 'You know the rule but not the reason, and there is no worked example. '
                . 'Method marks only.',
            default => 'This does not answer the question. Read the lesson again and ask the tutor '
                . 'to explain it in Pidgin.',
        };
    }

    /** A couple of practice papers each, so parent averages are not empty. */
    private function practiceFor(array $students, $subjects): int
    {
        $made = 0;

        foreach ($students as $student) {
            if ($student->attempts()->exists()) {
                continue;
            }

            $s = $this->seed('practice' . $student->email);

            foreach ([['mock', 40, 6], ['practice', 20, 2]] as [$kind, $total, $daysAgo]) {
                $score = (int) round($total * (0.25 + (($s >> $daysAgo) % 65) / 100));
                $slug = ['maths', 'english', 'chemistry'][$s % 3];

                $attempt = Attempt::create([
                    'user_id' => $student->id,
                    'subject_id' => $subjects[$slug] ?? null,
                    'kind' => $kind,
                    'title' => ($kind === 'mock' ? 'Mock exam · ' : 'Practice · ') . ucfirst($slug),
                    'score' => $score,
                    'total' => $total,
                    'minutes' => (int) round($total * 1.4),
                    'client_uuid' => (string) Str::uuid(),
                    'submitted_at' => now()->subDays($daysAgo),
                ]);

                $rows = [];
                for ($i = 0; $i < $total; $i++) {
                    $rows[] = [
                        'attempt_id' => $attempt->id,
                        'question_id' => null,
                        'topic' => null,
                        'chosen' => $i < $score ? 0 : 1,
                        'is_correct' => $i < $score,
                    ];
                }
                DB::table('attempt_answers')->insert($rows);

                $made++;
            }
        }

        return $made;
    }
}
