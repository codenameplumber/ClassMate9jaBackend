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
use Illuminate\Support\Str;

/**
 * A demo school with a real marking queue waiting.
 *
 * The point of this seeder is that a teacher can log in and immediately have
 * thirty AI-marked scripts to approve — the claim the product is sold on is
 * demonstrable within ten seconds of a fresh install.
 */
class DemoSeeder extends Seeder
{
    private const NAMES = [
        'Chidera Okonkwo', 'Aisha Bello', 'Tunde Adeyemi', 'Ngozi Eze', 'Ibrahim Musa',
        'Halima Yusuf', 'Segun Ogunleye', 'Blessing Etim', 'Yakubu Danjuma', 'Chioma Obi',
        'Musa Abdullahi', 'Temitope Balogun', 'Uche Nnamdi', 'Zainab Sani', 'Kelechi Anyanwu',
        'Adaeze Ugwu', 'Bashir Lawal', 'Funmilayo Oyelaran', 'Chinedu Okafor', 'Maryam Garba',
        'Olumide Fashola', 'Ifeoma Chukwu', 'Suleiman Bala', 'Titilayo Akande', 'Godwin Ubah',
        'Amina Shehu', 'Peter Enahoro', 'Bukola Ajayi', 'Nnamdi Eze', 'Rukayat Salami',
    ];

    /** The paper being marked. Two written answers on purpose — objective
     *  marking proves nothing about an AI marker. */
    private const PAPER = [
        ['kind' => 'objective', 'max' => 2, 'prompt' => 'Evaluate log₁₀ 1000.',
            'options' => ['10', '100', '3', '30'], 'answer' => 2, 'expected' => '3'],
        ['kind' => 'objective', 'max' => 2, 'prompt' => 'Simplify log 12 − log 4.',
            'options' => ['log 8', 'log 3', 'log 48', '3'], 'answer' => 1, 'expected' => 'log 3'],
        ['kind' => 'objective', 'max' => 2, 'prompt' => 'If log₂ x = 5, find x.',
            'options' => ['10', '25', '32', '7'], 'answer' => 2, 'expected' => '32'],
        ['kind' => 'written', 'max' => 4, 'prompt' => 'State the three laws of logarithms.',
            'options' => null, 'answer' => null,
            'expected' => 'log(MN) = log M + log N; log(M/N) = log M − log N; log(Mⁿ) = n log M'],
        ['kind' => 'written', 'max' => 5,
            'prompt' => 'Given that log 2 = 0.3010 and log 3 = 0.4771, find log 12. Show your working.',
            'options' => null, 'answer' => null,
            'expected' => 'log 12 = log(4 × 3) = 2log 2 + log 3 = 0.6020 + 0.4771 = 1.0791'],
    ];

    /** Answer variants per question, best first, with the mark and comment the
     *  marker produces. Confidence drops on the genuinely ambiguous ones. */
    private const VARIANTS = [
        0 => [
            ['a' => '3', 'mark' => 2, 'conf' => 99, 'note' => 'Correct.'],
            ['a' => '3', 'mark' => 2, 'conf' => 99, 'note' => 'Correct.'],
            ['a' => '10', 'mark' => 0, 'conf' => 97, 'note' => 'Confused the base with the answer. 10³ = 1000, so the log is 3.'],
            ['a' => '100', 'mark' => 0, 'conf' => 96, 'note' => 'This is 10², not the power that gives 1000.'],
        ],
        1 => [
            ['a' => 'log 3', 'mark' => 2, 'conf' => 98, 'note' => 'Correct — used log(M/N) = log M − log N.'],
            ['a' => 'log 8', 'mark' => 0, 'conf' => 95, 'note' => 'Subtracted the numbers instead of dividing them. 12 ÷ 4 = 3, not 12 − 4.'],
            ['a' => 'log 3', 'mark' => 2, 'conf' => 98, 'note' => 'Correct.'],
            ['a' => '3', 'mark' => 1, 'conf' => 74, 'note' => 'Right value inside, but the answer should stay as log 3 — the log has not been evaluated.'],
        ],
        2 => [
            ['a' => '32', 'mark' => 2, 'conf' => 99, 'note' => 'Correct — x = 2⁵.'],
            ['a' => '32', 'mark' => 2, 'conf' => 99, 'note' => 'Correct.'],
            ['a' => '10', 'mark' => 0, 'conf' => 96, 'note' => 'Multiplied 2 × 5 instead of raising 2 to the power 5.'],
            ['a' => '25', 'mark' => 0, 'conf' => 95, 'note' => 'Raised 5 to the power 2. The base is 2, so it is 2⁵ = 32.'],
        ],
        3 => [
            ['a' => 'log(MN) = log M + log N, log(M/N) = log M − log N, log(M^n) = n log M',
                'mark' => 4, 'conf' => 96, 'note' => 'All three laws stated correctly and in the right form.'],
            ['a' => 'log MN = log M + log N and log M/N = log M − log N',
                'mark' => 3, 'conf' => 88, 'note' => 'Two laws correct. The power law, log(Mⁿ) = n log M, is missing.'],
            ['a' => 'Product law, quotient law and power law',
                'mark' => 2, 'conf' => 71, 'note' => 'Names the three laws but does not state them. The question says "state", so the expressions are needed.'],
            ['a' => 'log M + log N = log M + N',
                'mark' => 0, 'conf' => 93, 'note' => 'This is the classic error: log M + log N = log(MN), not log(M + N).'],
        ],
        4 => [
            ['a' => 'log 12 = log(4×3) = 2log2 + log3 = 2(0.3010) + 0.4771 = 0.6020 + 0.4771 = 1.0791',
                'mark' => 5, 'conf' => 94, 'note' => 'Full working, correct factorisation and accurate arithmetic. Nothing to take off.'],
            ['a' => 'log 12 = log 4 + log 3 = 0.6020 + 0.4771 = 1.0791',
                'mark' => 4, 'conf' => 82, 'note' => 'Correct answer and method, but log 4 was written straight down without showing it comes from 2 log 2. One mark off for working.'],
            ['a' => 'log12 = log2 + log3 = 0.3010 + 0.4771 = 0.7781',
                'mark' => 2, 'conf' => 90, 'note' => 'Split 12 as 2 × 3 instead of 4 × 3. Method mark earned, but the factorisation and the final answer are wrong.'],
            ['a' => 'log 12 = 0.3010 × 0.4771 = 0.1436',
                'mark' => 0, 'conf' => 91, 'note' => 'Multiplied the logs. Multiplying inside a log becomes addition outside it, not multiplication of the values.'],
        ],
    ];

    public function run(): void
    {
        $school = School::create([
            'name' => 'Government Secondary School, Wuse',
            'state' => 'FCT',
            'lga' => 'Abuja Municipal',
            'type' => 'public',
        ]);

        $maths = Subject::where('slug', 'maths')->firstOrFail();

        /* --- Staff and demo accounts ---------------------------------- */
        $admin = $this->user('Adaobi Nwachukwu', 'admin@classmate9ja.ng', 'admin', [
            'school_id' => $school->id,
        ]);

        $teacher = $this->user('Mrs. Folake Adebayo', 'teacher@classmate9ja.ng', 'teacher', [
            'school_id' => $school->id,
            'subject_slug' => 'maths',
            'phone' => '08023456789',
        ]);

        $parent = $this->user('Mr. Emeka Okonkwo', 'parent@classmate9ja.ng', 'parent', [
            'phone' => '08034567890',
        ]);

        /* --- The class ------------------------------------------------ */
        $classroom = Classroom::create([
            'school_id' => $school->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $maths->id,
            'name' => 'SS2A',
            'level' => 'SS2',
            'room' => 'Block B, Room 4',
            'join_code' => 'SS2A-30',
        ]);

        $coreSubjects = Subject::where('is_core', true)->take(5)->pluck('id');
        $students = [];

        foreach (self::NAMES as $i => $name) {
            $isDemo = $i === 0;   // Chidera is the account a judge logs into
            $email = $isDemo
                ? 'student@classmate9ja.ng'
                : Str::slug(explode(' ', $name)[0] . '.' . explode(' ', $name)[1]) . '@demo.classmate9ja.ng';

            $student = $this->user($name, $email, 'student', [
                'school_id' => $school->id,
                'level' => 'SS2',
                'locale' => 'both',
                'goal' => 'both',
                'daily_goal' => 30,
            ]);

            $student->subjects()->syncWithoutDetaching($coreSubjects);
            $classroom->students()->syncWithoutDetaching([$student->id]);
            $students[] = $student;
        }

        $chidera = $students[0];
        ParentLink::create([
            'parent_id' => $parent->id,
            'student_id' => $chidera->id,
            'code' => $chidera->linkCode(),
            'report_day' => 'sunday',
            'verified_at' => now(),
        ]);

        $this->progressFor($chidera, $maths);

        /* --- The assignment and its thirty scripts -------------------- */
        $topic = Topic::where('subject_id', $maths->id)->where('ref', 'w3t1')->first();

        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'topic_id' => $topic?->id,
            'created_by' => $teacher->id,
            'title' => 'Week 3 Classwork · Logarithms',
            'kind' => 'classwork',
            'ai_generated' => true,
            'question_count' => count(self::PAPER),
            'due_on' => now()->addDays(2)->toDateString(),
            'published_at' => now()->subDays(3),
        ]);

        $questions = [];
        foreach (self::PAPER as $i => $q) {
            $questions[] = AssignmentQuestion::create([
                'assignment_id' => $assignment->id,
                'position' => $i + 1,
                'kind' => $q['kind'],
                'prompt' => $q['prompt'],
                'options' => $q['options'],
                'answer_index' => $q['answer'],
                'expected' => $q['expected'],
                'max_mark' => $q['max'],
            ]);
        }

        $maxScore = array_sum(array_column(self::PAPER, 'max'));

        foreach ($students as $student) {
            $this->submissionFor($assignment, $questions, $student, $maxScore);
        }

        /* --- A second paper, already marked and released ---------------
           So the student app has one piece of work to do and one that has
           come back with a grade — both halves of the loop are visible on a
           fresh install. */
        $indices = Assignment::create([
            'classroom_id' => $classroom->id,
            'created_by' => $teacher->id,
            'title' => 'Week 2 Test · Laws of Indices',
            'kind' => 'test',
            'ai_generated' => true,
            'question_count' => 3,
            'due_on' => now()->subDays(6)->toDateString(),
            'published_at' => now()->subDays(10),
        ]);

        $indicesPaper = [
            ['Simplify 2³ × 2⁵ ÷ 2⁴.', ['2²', '2⁴', '2⁶', '2⁸'], 1, '2⁴', 2],
            ['Evaluate 5⁰ + 3¹.', ['3', '4', '8', '15'], 1, '4 — anything to the power zero is 1', 2],
            ['Simplify (x²)³.', ['x⁵', 'x⁶', 'x⁸', 'x⁹'], 1, 'x⁶ — multiply the indices', 2],
        ];

        $indicesQuestions = [];
        foreach ($indicesPaper as $i => [$prompt, $options, $answer, $expected, $max]) {
            $indicesQuestions[] = AssignmentQuestion::create([
                'assignment_id' => $indices->id,
                'position' => $i + 1,
                'kind' => 'objective',
                'prompt' => $prompt,
                'options' => $options,
                'answer_index' => $answer,
                'expected' => $expected,
                'max_mark' => $max,
            ]);
        }

        $this->markedSubmissionFor($indices, $indicesQuestions, $chidera, $teacher);

        /* --- A third paper, freshly set and untouched -------------------
           The demo needs all three states visible at once: work still to do,
           work waiting on the teacher, and work that has come back marked. */
        $quadratics = Assignment::create([
            'classroom_id' => $classroom->id,
            'topic_id' => Topic::where('subject_id', $maths->id)->where('ref', 'w6t1')->value('id'),
            'created_by' => $teacher->id,
            'title' => 'Week 6 Classwork · Quadratic Equations',
            'kind' => 'classwork',
            'ai_generated' => true,
            'question_count' => 4,
            'due_on' => now()->addDays(3)->toDateString(),
            'published_at' => now()->subDay(),
        ]);

        $quadraticsPaper = [
            ['objective', 'Solve x² − 5x + 6 = 0.',
                ['x = 1 or 6', 'x = 2 or 3', 'x = −2 or −3', 'x = 5 or 6'], 1,
                'x = 2 or 3, by factorising into (x − 2)(x − 3) = 0', 2],
            ['objective', 'What is the value of the discriminant of x² + 4x + 4 = 0?',
                ['0', '4', '8', '16'], 0,
                '0 — b² − 4ac = 16 − 16, so the roots are equal', 2],
            ['objective', 'The sum of the roots of 2x² − 6x + 4 = 0 is:',
                ['2', '3', '−3', '6'], 1,
                '3 — the sum of roots is −b/a = 6/2', 2],
            ['written', 'Solve 2x² + 5x − 3 = 0 by factorisation. Show every step.',
                null, null,
                'Factorise to (2x − 1)(x + 3) = 0 (3 marks), then state x = ½ or x = −3 (2 marks). '
                . 'Award method marks for a correct factorisation even if the final values are wrong.', 5],
        ];

        foreach ($quadraticsPaper as $i => [$kind, $prompt, $options, $answer, $expected, $max]) {
            AssignmentQuestion::create([
                'assignment_id' => $quadratics->id,
                'position' => $i + 1,
                'kind' => $kind,
                'prompt' => $prompt,
                'options' => $options,
                'answer_index' => $answer,
                'expected' => $expected,
                'max_mark' => $max,
            ]);
        }

        $this->command->info('  school     ' . $school->name);
        $this->command->info('  accounts   admin / teacher / parent / ' . count($students) . ' students');
        $this->command->info('  marking    ' . count($students) . ' scripts pending on "' . $assignment->title . '"');
    }

    private function user(string $name, string $email, string $role, array $extra = []): User
    {
        return User::updateOrCreate(['email' => $email], array_merge([
            'name' => $name,
            'role' => $role,
            'password' => 'password',
            'email_verified_at' => now(),
            'onboarded_at' => now(),
        ], $extra));
    }

    /** Stable per-student pseudo-randomness, so the demo looks identical every run. */
    private function seed(string $key): int
    {
        return abs(crc32($key));
    }

    private function submissionFor(Assignment $assignment, array $questions, User $student, int $maxScore): void
    {
        $s = $this->seed($student->email . $assignment->id);
        $ability = $s % 100;                       // 0-99, drives which variant they land on

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'client_uuid' => (string) Str::uuid(),
            'max_score' => $maxScore,
            'status' => 'marked',
            'submitted_at' => now()->subDays(2)->addMinutes($s % 900),
        ]);

        $total = 0;
        $needsReview = false;
        $floor = (int) config('ai.confidence_floor', 80);

        foreach ($questions as $index => $question) {
            $pool = self::VARIANTS[$index];
            $pick = (int) floor($ability / 100 * count($pool));
            $pick = max(0, min(count($pool) - 1, ($pick + (($s >> $index) % 2)) % count($pool)));
            $variant = $pool[$pick];

            SubmissionAnswer::create([
                'submission_id' => $submission->id,
                'assignment_question_id' => $question->id,
                'answer_text' => $variant['a'],
                'chosen' => $question->kind === 'objective'
                    ? array_search($variant['a'], $question->options ?? [], true) ?: null
                    : null,
                'ai_mark' => $variant['mark'],
                'ai_confidence' => $variant['conf'],
                'ai_comment' => $variant['note'],
            ]);

            $total += $variant['mark'];
            if ($variant['conf'] < $floor) {
                $needsReview = true;
            }
        }

        $submission->update(['ai_score' => $total, 'needs_review' => $needsReview]);
    }

    /**
     * A paper the teacher has already marked and approved, so the student app
     * can show a released grade with real comments on it.
     */
    private function markedSubmissionFor(
        Assignment $assignment,
        array $questions,
        User $student,
        User $teacher
    ): void {
        $maxScore = array_sum(array_map(fn ($q) => $q->max_mark, $questions));

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'client_uuid' => (string) Str::uuid(),
            'max_score' => $maxScore,
            'status' => 'approved',
            'submitted_at' => now()->subDays(7),
            'approved_at' => now()->subDays(6),
            'approved_by' => $teacher->id,
        ]);

        $total = 0;
        foreach ($questions as $i => $question) {
            // Right on the first two, wrong on the last — a realistic script.
            $correct = $i < 2;
            $chosen = $correct ? $question->answer_index : ($question->answer_index === 0 ? 1 : 0);
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
                    : 'The correct answer is: ' . ($question->options[$question->answer_index] ?? $question->expected),
            ]);
        }

        $submission->update(['ai_score' => $total, 'final_score' => $total]);
    }

    /** Give the demo student a believable three weeks of history. */
    private function progressFor(User $student, Subject $maths): void
    {
        $week = [35, 50, 20, 65, 40, 15, 24];

        foreach ($week as $offset => $minutes) {
            if ($minutes === 0) {
                continue;
            }
            StudySession::create([
                'user_id' => $student->id,
                'minutes' => $minutes,
                'occurred_on' => now()->startOfWeek()->addDays($offset)->toDateString(),
                'source' => 'lesson',
                'client_uuid' => (string) Str::uuid(),
            ]);
        }

        $papers = [
            ['english', 'WAEC Mock · English', 32, 40, 45, 4],
            ['maths', 'Scheme Exam · Maths', 27, 40, 60, 8],
            ['chemistry', 'JAMB Mock · Chemistry', 16, 40, 40, 12],
        ];

        foreach ($papers as [$slug, $title, $score, $total, $minutes, $daysAgo]) {
            $subject = Subject::where('slug', $slug)->first();
            $attempt = Attempt::create([
                'user_id' => $student->id,
                'subject_id' => $subject?->id,
                'kind' => str_contains($title, 'Mock') ? 'mock' : 'scheme_exam',
                'title' => $title,
                'score' => $score,
                'total' => $total,
                'minutes' => $minutes,
                'client_uuid' => (string) Str::uuid(),
                'submitted_at' => now()->subDays($daysAgo),
            ]);

            for ($i = 0; $i < $total; $i++) {
                AttemptAnswer::create([
                    'attempt_id' => $attempt->id,
                    'topic' => null,
                    'chosen' => $i < $score ? 0 : 1,
                    'is_correct' => $i < $score,
                ]);
            }
        }

        StudentState::create([
            'user_id' => $student->id,
            'payload' => [
                'points' => 2340,
                'streak' => 12,
                'bestStreak' => 14,
                'minutesToday' => 24,
                'questionsAnswered' => 386,
                'lessonsDone' => 27,
                'week' => $week,
                'subjects' => [
                    'maths' => ['mastery' => 74, 'done' => 17, 'total' => 24, 'weak' => ['Logarithms', 'Bearings']],
                    'english' => ['mastery' => 81, 'done' => 19, 'total' => 24, 'weak' => ['Oral English']],
                    'physics' => ['mastery' => 68, 'done' => 14, 'total' => 24, 'weak' => ['Electric Fields', 'Waves']],
                    'chemistry' => ['mastery' => 41, 'done' => 8, 'total' => 24, 'weak' => ['Mole Concept', 'Organic Chemistry', 'Redox']],
                    'biology' => ['mastery' => 63, 'done' => 12, 'total' => 24, 'weak' => ['Genetics']],
                ],
                'badges' => ['first-lesson', 'streak-7', 'exam-pass', 'offline-hero'],
                'doneTopics' => [
                    'physics:w1t1', 'physics:w1t2', 'physics:w2t1', 'physics:w2t2', 'physics:w3t1', 'physics:w3t2',
                    'maths:w1t1', 'maths:w1t2', 'maths:w2t1', 'maths:w2t2',
                    'chemistry:w1t1', 'chemistry:w1t2',
                    'english:w1t1', 'english:w1t2', 'english:w2t1',
                    'biology:w1t1', 'biology:w1t2',
                ],
            ],
            'device_updated_at' => now(),
        ]);
    }
}
