<?php

namespace Tests\Feature;

use App\Jobs\MarkSubmission;
use App\Livewire\Auth\Onboarding;
use App\Livewire\Teacher\Assignments;
use App\Livewire\Teacher\Marking;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Adversarial probes. Every test here is written to expose a defect, not to
 * confirm something already believed to work.
 */
class AuditTest extends TestCase
{
    use DatabaseTransactions;

    private function teacher(): User { return User::where('email', 'teacher@classmate9ja.ng')->firstOrFail(); }
    private function student(): User { return User::where('email', 'student@classmate9ja.ng')->firstOrFail(); }

    private function otherTeacher(): User
    {
        return User::create([
            'name' => 'Mr. Intruder Okoro',
            'email' => 'intruder-' . Str::random(6) . '@test.ng',
            'password' => 'password',
            'role' => 'teacher',
            'email_verified_at' => now(),
            'onboarded_at' => now(),
        ]);
    }

    /** A real member of the class with no submission yet. */
    private function freshStudentInClass(Assignment $assignment): User
    {
        $student = User::create([
            'name' => 'Fresh Student ' . Str::random(4),
            'email' => 'fresh-' . Str::random(8) . '@test.ng',
            'password' => 'password',
            'role' => 'student',
            'level' => 'SS2',
            'email_verified_at' => now(),
            'onboarded_at' => now(),
        ]);

        $assignment->classroom->students()->attach($student->id);

        return $student;
    }

    private function apiToken(string $email = 'student@classmate9ja.ng'): string
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => 'password'])->json('token');
    }

    /* ==================================================================
     | AUTHORISATION — can someone reach data that is not theirs?
     ================================================================== */

    public function test_a_teacher_cannot_open_another_teachers_marking_queue(): void
    {
        $assignment = Assignment::whereHas('classroom', fn ($q) =>
            $q->where('teacher_id', $this->teacher()->id))->firstOrFail();

        $this->actingAs($this->otherTeacher());

        // Opening someone else's assignment by URL must not expose their class.
        $response = $this->get(route('teacher.marking', $assignment));

        $this->assertTrue(
            $response->isRedirect() || $response->status() === 403
                || ! str_contains($response->getContent(), 'Chidera'),
            'A teacher opened another teacher\'s marking queue and saw their students.'
        );
    }

    public function test_a_teacher_cannot_approve_a_script_in_another_teachers_class(): void
    {
        $assignment = Assignment::whereHas('classroom', fn ($q) =>
            $q->where('teacher_id', $this->teacher()->id))
            ->whereHas('submissions', fn ($q) => $q->where('status', '!=', 'approved'))
            ->firstOrFail();

        $before = Submission::where('assignment_id', $assignment->id)
            ->where('status', '!=', 'approved')->count();

        $this->actingAs($this->otherTeacher());

        try {
            Livewire::test(Marking::class, ['assignment' => $assignment])->call('approve');
        } catch (\Throwable) {
            // Throwing is an acceptable way to refuse.
        }

        $after = Submission::where('assignment_id', $assignment->id)
            ->where('status', '!=', 'approved')->count();

        $this->assertSame($before, $after, 'An unrelated teacher approved somebody else\'s script.');
    }

    public function test_a_student_cannot_submit_to_a_class_they_are_not_in(): void
    {
        $outsider = User::create([
            'name' => 'Outsider Bello',
            'email' => 'outsider-' . Str::random(6) . '@test.ng',
            'password' => 'password',
            'role' => 'student',
            'email_verified_at' => now(),
            'onboarded_at' => now(),
        ]);

        $assignment = Assignment::with('questions')->has('questions')->firstOrFail();
        $token = $this->apiToken($outsider->email);

        $response = $this->withToken($token)->postJson('/api/submissions', [
            'clientUuid' => (string) Str::uuid(),
            'assignmentId' => $assignment->id,
            'answers' => [['questionId' => $assignment->questions->first()->id, 'chosen' => 0]],
        ]);

        $this->assertTrue(
            $response->status() >= 400,
            'A student submitted work to a class they do not belong to.'
        );
    }

    public function test_the_api_login_endpoint_is_rate_limited(): void
    {
        $blocked = false;

        for ($i = 0; $i < 12; $i++) {
            $status = $this->postJson('/api/login', [
                'email' => 'teacher@classmate9ja.ng',
                'password' => 'wrong-password',
            ])->status();

            if ($status === 429) {
                $blocked = true;
                break;
            }
        }

        $this->assertTrue($blocked, 'The API accepted 12 password guesses without throttling.');
    }

    /* ==================================================================
     | MARKING — does the queue behave the way the screen claims?
     ================================================================== */

    public function test_flagged_scripts_are_sorted_to_the_top_of_the_queue(): void
    {
        $this->actingAs($this->teacher());

        $scripts = Livewire::test(Marking::class)->instance()->scripts;
        $pending = $scripts->filter(fn ($s) => ! $s->isApproved())->values();

        $this->assertGreaterThan(0, $pending->count());

        // Once a non-flagged script appears, no flagged one may follow it.
        $seenConfident = false;
        foreach ($pending as $script) {
            if (! $script->needs_review) {
                $seenConfident = true;
            } elseif ($seenConfident) {
                $this->fail('A flagged script sits below a confident one — the sort is wrong.');
            }
        }

        $this->assertTrue(true);
    }

    public function test_objective_answers_are_marked_without_any_ai(): void
    {
        $assignment = Assignment::with('questions', 'classroom')->has('questions')->firstOrFail();
        $student = $this->freshStudentInClass($assignment);
        $objective = $assignment->questions->firstWhere('kind', 'objective');

        $this->assertNotNull($objective, 'The seeded paper should contain objective questions.');

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'client_uuid' => (string) Str::uuid(),
            'max_score' => $assignment->maxScore(),
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $submission->answers()->create([
            'assignment_question_id' => $objective->id,
            'chosen' => $objective->answer_index,        // the correct option
            'answer_text' => $objective->options[$objective->answer_index] ?? null,
        ]);

        (new MarkSubmission($submission->id))->handle(app(\App\Services\Ai\Marker::class));

        $answer = $submission->fresh()->answers->first();

        $this->assertSame($objective->max_mark, (int) $answer->ai_mark, 'A correct option scored wrong.');
        $this->assertSame(100, (int) $answer->ai_confidence, 'Objective marking should be fully confident.');
    }

    public function test_a_wrong_objective_answer_scores_zero_and_names_the_right_one(): void
    {
        $assignment = Assignment::with('questions', 'classroom')->has('questions')->firstOrFail();
        $student = $this->freshStudentInClass($assignment);
        $objective = $assignment->questions->firstWhere('kind', 'objective');

        $wrong = $objective->answer_index === 0 ? 1 : 0;

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
            'client_uuid' => (string) Str::uuid(),
            'max_score' => $assignment->maxScore(),
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $submission->answers()->create([
            'assignment_question_id' => $objective->id,
            'chosen' => $wrong,
        ]);

        (new MarkSubmission($submission->id))->handle(app(\App\Services\Ai\Marker::class));

        $answer = $submission->fresh()->answers->first();

        $this->assertSame(0, (int) $answer->ai_mark);
        $this->assertStringContainsString(
            (string) ($objective->options[$objective->answer_index] ?? ''),
            (string) $answer->ai_comment,
            'A wrong answer should tell the student what the right one was.'
        );
    }

    public function test_a_written_answer_with_no_ai_key_is_flagged_for_the_teacher(): void
    {
        $assignment = Assignment::with('questions', 'classroom')->has('questions')->firstOrFail();
        $written = $assignment->questions->firstWhere('kind', 'written');

        $this->assertNotNull($written, 'The seeded paper should contain a written question.');

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->freshStudentInClass($assignment)->id,
            'client_uuid' => (string) Str::uuid(),
            'max_score' => $assignment->maxScore(),
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $submission->answers()->create([
            'assignment_question_id' => $written->id,
            'answer_text' => 'log(MN) = log M + log N and the rest',
        ]);

        (new MarkSubmission($submission->id))->handle(app(\App\Services\Ai\Marker::class));

        $fresh = $submission->fresh();

        $this->assertTrue((bool) $fresh->needs_review, 'An unmarked written answer must flag the script.');
        $this->assertNull($fresh->answers->first()->ai_mark, 'It must not guess a mark.');
    }

    /* ==================================================================
     | END TO END — student submits, teacher marks, parent sees it
     ================================================================== */

    public function test_a_student_submission_reaches_the_teachers_queue(): void
    {
        $student = $this->student();
        $assignment = Assignment::with('questions')->has('questions')
            ->whereHas('classroom.students', fn ($q) => $q->where('users.id', $student->id))
            ->firstOrFail();

        // Clear any seeded submission so this is a genuine first submit.
        Submission::where('assignment_id', $assignment->id)->where('user_id', $student->id)->delete();

        $token = $this->apiToken();

        $answers = $assignment->questions->map(fn ($q) => [
            'questionId' => $q->id,
            'chosen' => $q->kind === 'objective' ? $q->answer_index : null,
            'text' => $q->kind === 'written' ? 'My written answer, showing the working.' : null,
        ])->all();

        $this->withToken($token)->postJson('/api/submissions', [
            'clientUuid' => (string) Str::uuid(),
            'assignmentId' => $assignment->id,
            'answers' => $answers,
        ])->assertCreated();

        $submission = Submission::where('assignment_id', $assignment->id)
            ->where('user_id', $student->id)->firstOrFail();

        $this->assertNotNull($submission, 'The submission never reached the database.');

        // QUEUE_CONNECTION is sync under test, so marking has already run.
        $this->assertContains(
            $submission->fresh()->status,
            ['marked', 'pending', 'marking'],
            'The submission should have entered the marking pipeline.'
        );

        $this->assertGreaterThan(
            0,
            $submission->fresh()->answers()->whereNotNull('ai_mark')->count(),
            'At least the objective answers should have been marked automatically.'
        );
    }

    /* ==================================================================
     | ONBOARDING & DRAFTING
     ================================================================== */

    public function test_onboarding_saves_the_students_choices(): void
    {
        $fresh = User::create([
            'name' => 'Newcomer Adeleke',
            'email' => 'newcomer-' . Str::random(6) . '@test.ng',
            'password' => 'password',
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $maths = Subject::where('slug', 'maths')->firstOrFail();

        $this->actingAs($fresh);

        Livewire::test(Onboarding::class)
            ->set('level', 'SS3')
            ->call('next')
            ->call('toggleSubject', $maths->id)
            ->call('next')
            ->set('goal', 'jamb')
            ->call('next')
            ->set('locale', 'pcm')
            ->set('daily_goal', 60)
            ->call('next');

        $fresh->refresh();

        $this->assertSame('SS3', $fresh->level);
        $this->assertSame('jamb', $fresh->goal);
        $this->assertSame('pcm', $fresh->locale);
        $this->assertSame(60, $fresh->daily_goal);
        $this->assertNotNull($fresh->onboarded_at);
        $this->assertTrue($fresh->subjects->contains('id', $maths->id));
    }

    public function test_onboarding_refuses_to_advance_with_no_subject_chosen(): void
    {
        $fresh = User::create([
            'name' => 'Indecisive Musa',
            'email' => 'indecisive-' . Str::random(6) . '@test.ng',
            'password' => 'password',
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($fresh);

        Livewire::test(Onboarding::class)
            ->call('next')          // step 1 -> 2
            ->call('next')          // step 2 with nothing picked
            ->assertHasErrors('subjects')
            ->assertSet('step', 2);
    }

    public function test_drafting_an_assignment_from_the_bank_produces_a_usable_paper(): void
    {
        $this->actingAs($this->teacher());

        $topic = Topic::whereHas('classwork')->firstOrFail();

        $component = Livewire::test(Assignments::class)
            ->set('topicId', $topic->id)
            ->set('count', 5)
            ->call('generate');

        $draft = $component->get('draft');

        $this->assertNotEmpty($draft['questions'] ?? [], 'The drafter returned no questions.');
        $this->assertTrue(
            collect($draft['questions'])->contains(fn ($q) => $q['kind'] === 'written'),
            'Every paper should close with a written answer.'
        );
        $this->assertTrue(
            collect($draft['questions'])->every(fn ($q) => filled($q['prompt']) && filled($q['expected'])),
            'Every drafted question needs a prompt and a marking scheme.'
        );

        $before = Assignment::count();
        $component->call('publish');

        $this->assertSame($before + 1, Assignment::count(), 'Publishing did not create the assignment.');

        $created = Assignment::latest('id')->first();
        $this->assertGreaterThan(0, $created->questions()->count(), 'The published paper has no questions.');
        $this->assertGreaterThan(0, $created->maxScore(), 'The published paper is worth zero marks.');
    }

    /* ==================================================================
     | EMAIL
     ================================================================== */

    public function test_the_weekly_report_command_queues_mail_for_linked_parents(): void
    {
        Mail::fake();

        $this->artisan('reports:weekly --day=sunday')->assertSuccessful();

        Mail::assertQueued(\App\Mail\WeeklyReport::class);
    }

    public function test_a_password_reset_link_actually_resets_the_password(): void
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Forgetful Yusuf',
            'email' => 'forgetful-' . Str::random(6) . '@test.ng',
            'password' => 'password',
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $token = \Illuminate\Support\Facades\Password::createToken($user);

        Livewire::test(\App\Livewire\Auth\ResetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'a-brand-new-password')
            ->set('password_confirmation', 'a-brand-new-password')
            ->call('resetPassword')
            ->assertRedirect(route('login'));

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('a-brand-new-password', $user->fresh()->password),
            'The reset flow reported success but the password did not change.'
        );
    }

    /* ==================================================================
     | DATA INTEGRITY
     ================================================================== */

    public function test_no_lesson_body_is_empty(): void
    {
        $empty = \App\Models\Lesson::get()->filter(fn ($l) => empty($l->body))->count();

        $this->assertSame(0, $empty, "{$empty} lessons were seeded with an empty body.");
    }

    public function test_every_classwork_question_has_a_valid_answer_index(): void
    {
        $broken = \App\Models\ClassworkQuestion::get()
            ->filter(fn ($q) => ! isset($q->options[$q->answer_index]))
            ->count();

        $this->assertSame(0, $broken, "{$broken} classwork questions point at an option that does not exist.");
    }

    public function test_every_past_question_has_a_valid_answer_index(): void
    {
        $broken = \App\Models\Question::get()
            ->filter(fn ($q) => ! isset($q->options[$q->answer_index]))
            ->count();

        $this->assertSame(0, $broken, "{$broken} past questions point at an option that does not exist.");
    }

    public function test_seeded_submission_scores_never_exceed_the_paper_total(): void
    {
        $over = Submission::get()->filter(fn ($s) => $s->score() > $s->max_score)->count();

        $this->assertSame(0, $over, "{$over} scripts scored more than the paper is worth.");
    }
}
