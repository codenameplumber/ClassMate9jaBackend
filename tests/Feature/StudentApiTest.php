<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The API the student app actually runs on — the classwork loop, the account
 * screens, and the tutor.
 */
class StudentApiTest extends TestCase
{
    use DatabaseTransactions;

    private function student(): User
    {
        return User::where('email', 'student@classmate9ja.ng')->firstOrFail();
    }

    private function token(?string $email = null): string
    {
        return $this->postJson('/api/login', [
            'email' => $email ?? 'student@classmate9ja.ng',
            'password' => 'password',
        ])->json('token');
    }

    private function classwork(): Assignment
    {
        return Assignment::with('questions', 'classroom')
            ->has('questions')
            ->whereHas('classroom.students', fn ($q) => $q->where('users.id', $this->student()->id))
            ->firstOrFail();
    }

    private function outsider(): User
    {
        return User::create([
            'name' => 'Outsider ' . Str::random(4),
            'email' => 'outside-' . Str::random(8) . '@test.ng',
            'password' => 'password',
            'role' => 'student',
            'email_verified_at' => now(),
            'onboarded_at' => now(),
        ]);
    }

    /* ------------------------------------------------------ the classwork loop */

    public function test_a_student_sees_the_work_their_teacher_set(): void
    {
        $response = $this->withToken($this->token())->getJson('/api/assignments')->assertOk();

        $this->assertNotEmpty($response->json('assignments'), 'The classwork list came back empty.');

        $first = $response->json('assignments.0');
        foreach (['id', 'title', 'kind', 'classroom', 'submitted', 'questions'] as $field) {
            $this->assertArrayHasKey($field, $first, "The list is missing `{$field}`, which the screen renders.");
        }
    }

    public function test_opening_a_paper_returns_its_questions(): void
    {
        $assignment = $this->classwork();

        $response = $this->withToken($this->token())
            ->getJson("/api/assignments/{$assignment->id}")
            ->assertOk();

        $this->assertSame($assignment->id, $response->json('assignment.id'));
        $this->assertGreaterThan(0, $response->json('assignment.maxScore'));
        $this->assertCount($assignment->questions->count(), $response->json('assignment.questions'));

        $kinds = collect($response->json('assignment.questions'))->pluck('kind')->unique();
        $this->assertTrue($kinds->contains('written'), 'The paper should include a written answer.');
    }

    public function test_a_paper_never_leaks_its_answer_key_to_the_student(): void
    {
        $assignment = $this->classwork();

        $body = $this->withToken($this->token())
            ->getJson("/api/assignments/{$assignment->id}")
            ->getContent();

        $this->assertStringNotContainsString('answer_index', $body);
        $this->assertStringNotContainsString('answerIndex', $body);
        $this->assertStringNotContainsString('expected', $body);
    }

    public function test_an_outsider_cannot_open_a_paper(): void
    {
        $assignment = $this->classwork();

        $this->withToken($this->token($this->outsider()->email))
            ->getJson("/api/assignments/{$assignment->id}")
            ->assertForbidden();
    }

    public function test_submitting_written_work_stores_the_text_and_starts_marking(): void
    {
        $assignment = $this->classwork();
        Submission::where('assignment_id', $assignment->id)
            ->where('user_id', $this->student()->id)->delete();

        $written = $assignment->questions->firstWhere('kind', 'written');

        $answers = $assignment->questions->map(fn ($q) => [
            'questionId' => $q->id,
            'chosen' => $q->kind === 'objective' ? $q->answer_index : null,
            'text' => $q->kind === 'written' ? 'log(MN) = log M + log N, shown step by step.' : null,
        ])->all();

        $this->withToken($this->token())->postJson('/api/submissions', [
            'clientUuid' => (string) Str::uuid(),
            'assignmentId' => $assignment->id,
            'answers' => $answers,
        ])->assertCreated();

        $submission = Submission::with('answers')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $this->student()->id)
            ->firstOrFail();

        $writtenAnswer = $submission->answers->firstWhere('assignment_question_id', $written->id);

        $this->assertStringContainsString('log M + log N', (string) $writtenAnswer->answer_text);
        $this->assertGreaterThan(
            0,
            $submission->answers->whereNotNull('ai_mark')->count(),
            'Objective answers should already be marked with no AI involved.'
        );
    }

    public function test_a_mark_is_hidden_from_the_student_until_the_teacher_approves_it(): void
    {
        $assignment = $this->classwork();
        Submission::where('assignment_id', $assignment->id)
            ->where('user_id', $this->student()->id)->delete();

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->student()->id,
            'client_uuid' => (string) Str::uuid(),
            'ai_score' => 9,
            'max_score' => $assignment->maxScore(),
            'status' => 'marked',           // marked by the AI, NOT approved
            'submitted_at' => now(),
        ]);

        $body = $this->withToken($this->token())->getJson('/api/submissions')->assertOk();

        $row = collect($body->json('submissions'))->firstWhere('id', $submission->id);

        $this->assertNotNull($row);
        $this->assertFalse($row['released'], 'An unapproved AI mark must not be shown as a grade.');
        $this->assertNull($row['score'], 'The score leaked before a teacher signed it off.');
        $this->assertEmpty($row['answers'], 'Comments leaked before approval.');

        // Now approve it, and the same endpoint should release everything.
        $submission->update([
            'status' => 'approved',
            'final_score' => 11,
            'approved_at' => now(),
        ]);

        $after = collect(
            $this->withToken($this->token())->getJson('/api/submissions')->json('submissions')
        )->firstWhere('id', $submission->id);

        $this->assertTrue($after['released']);
        $this->assertSame(11, $after['score']);
    }

    /* --------------------------------------------------------------- account */

    public function test_onboarding_from_the_app_saves_to_the_server(): void
    {
        $student = User::create([
            'name' => 'App Newcomer',
            'email' => 'appnew-' . Str::random(8) . '@test.ng',
            'password' => 'password',
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $this->withToken($this->token($student->email))->postJson('/api/onboarding', [
            'level' => 'SS1',
            'subjects' => ['maths', 'english'],
            'goal' => 'waec',
            'locale' => 'pcm',
            'dailyGoal' => 20,
        ])->assertOk();

        $student->refresh();

        $this->assertSame('SS1', $student->level);
        $this->assertSame('waec', $student->goal);
        $this->assertSame('pcm', $student->locale);
        $this->assertSame(20, $student->daily_goal);
        $this->assertNotNull($student->onboarded_at);
        $this->assertEqualsCanonicalizing(
            ['maths', 'english'],
            $student->subjects()->pluck('slug')->all()
        );
    }

    public function test_onboarding_rejects_a_subject_that_does_not_exist(): void
    {
        $this->withToken($this->token())->postJson('/api/onboarding', [
            'level' => 'SS2',
            'subjects' => ['astrology'],
            'goal' => 'both',
            'locale' => 'en',
            'dailyGoal' => 30,
        ])->assertStatus(422);
    }

    public function test_settings_changes_from_the_app_persist(): void
    {
        $this->withToken($this->token())
            ->patchJson('/api/me', ['dailyGoal' => 60, 'locale' => 'en'])
            ->assertOk();

        $student = $this->student()->fresh();

        $this->assertSame(60, $student->daily_goal);
        $this->assertSame('en', $student->locale);
    }

    public function test_forgot_password_never_reveals_whether_an_account_exists(): void
    {
        Mail::fake();

        $known = $this->postJson('/api/forgot-password', ['email' => 'student@classmate9ja.ng']);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'nobody@nowhere.ng']);

        $known->assertOk();
        $unknown->assertOk();

        $this->assertSame(
            $known->json('message'),
            $unknown->json('message'),
            'The two responses differ, which turns this endpoint into a user-list oracle.'
        );
    }

    /* ----------------------------------------------------------------- tutor */

    public function test_the_tutor_declines_gracefully_with_no_key_configured(): void
    {
        $response = $this->withToken($this->token())
            ->postJson('/api/tutor', ['question' => 'Explain logarithms to me'])
            ->assertOk();

        // Not an error: the app falls back to its on-device tutor.
        $this->assertFalse($response->json('answered'));
        $this->assertSame('not-configured', $response->json('reason'));
    }

    public function test_the_tutor_validates_its_input(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/tutor', ['question' => ''])
            ->assertStatus(422);
    }

    public function test_the_tutor_requires_a_signed_in_student(): void
    {
        $this->postJson('/api/tutor', ['question' => 'hello'])->assertUnauthorized();
    }
}
