<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Teacher\Marking;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Runs against the real seeded database inside a transaction, so it exercises
 * the actual content and the actual marking queue and rolls back afterwards.
 */
class PlatformSmokeTest extends TestCase
{
    use DatabaseTransactions;

    private function teacher(): User { return User::where('email', 'teacher@classmate9ja.ng')->firstOrFail(); }
    private function parent(): User { return User::where('email', 'parent@classmate9ja.ng')->firstOrFail(); }
    private function admin(): User { return User::where('email', 'admin@classmate9ja.ng')->firstOrFail(); }
    private function student(): User { return User::where('email', 'student@classmate9ja.ng')->firstOrFail(); }

    /* ------------------------------------------------------------ public */

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('ClassMate');
        $this->get('/login')->assertOk()->assertSee('Log in');
        $this->get('/register')->assertOk()->assertSee('Create your account');
        $this->get('/forgot-password')->assertOk();
    }

    public function test_guest_is_redirected_from_a_dashboard(): void
    {
        $this->get('/teacher')->assertRedirect('/login');
        $this->get('/parent')->assertRedirect('/login');
    }

    /* ------------------------------------------------------------- login */

    public function test_a_teacher_can_log_in_and_lands_on_their_dashboard(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'teacher@classmate9ja.ng')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('teacher.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'teacher@classmate9ja.ng')
            ->set('password', 'not-the-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    /* ----------------------------------------------------------- teacher */

    public function test_every_teacher_screen_renders(): void
    {
        $this->actingAs($this->teacher());

        $this->get('/teacher')->assertOk()->assertSee('scripts pending');
        $this->get('/teacher/classes')->assertOk()->assertSee('Roster');
        $this->get('/teacher/assignments')->assertOk()->assertSee('All assignments');
        $this->get('/teacher/marking')->assertOk()->assertSee('Marking queue');
        $this->get('/teacher/reports')->assertOk()->assertSee('Grade spread');
    }

    public function test_the_marking_queue_loads_the_seeded_scripts(): void
    {
        $this->actingAs($this->teacher());

        Livewire::test(Marking::class)
            ->assertSee('Scripts')
            ->assertSee('AI')
            ->assertSeeHtml('Approve');
    }

    public function test_approving_a_script_records_the_teachers_score(): void
    {
        $teacher = $this->teacher();
        $this->actingAs($teacher);

        $assignment = Assignment::whereHas('submissions', fn ($q) => $q->where('status', '!=', 'approved'))
            ->firstOrFail();

        $before = Submission::where('assignment_id', $assignment->id)
            ->where('status', '!=', 'approved')->count();

        Livewire::test(Marking::class, ['assignment' => $assignment])
            ->call('approve');

        $after = Submission::where('assignment_id', $assignment->id)
            ->where('status', '!=', 'approved')->count();

        $this->assertSame($before - 1, $after, 'Approving should clear exactly one script.');

        $approved = Submission::where('assignment_id', $assignment->id)
            ->where('status', 'approved')->latest('approved_at')->first();

        $this->assertNotNull($approved->final_score);
        $this->assertSame($teacher->id, $approved->approved_by);
    }

    public function test_a_teacher_can_override_a_mark_before_approving(): void
    {
        $this->actingAs($this->teacher());

        $component = Livewire::test(Marking::class);
        $current = $component->instance()->current;
        $answer = $current->answers->first();
        $before = $answer->ai_mark;

        $component->call('step', $answer->id, -1)->call('approve');

        $this->assertSame(
            max(0, $before - 1),
            (int) $answer->fresh()->teacher_mark,
            'The teacher override should be what gets stored.'
        );
    }

    public function test_bulk_approve_leaves_flagged_scripts_behind(): void
    {
        $this->actingAs($this->teacher());

        $assignment = Assignment::whereHas('submissions', fn ($q) => $q->where('status', '!=', 'approved'))
            ->firstOrFail();

        Livewire::test(Marking::class, ['assignment' => $assignment])->call('approveConfident');

        $left = Submission::where('assignment_id', $assignment->id)
            ->where('status', '!=', 'approved')->get();

        $this->assertTrue(
            $left->every(fn ($s) => $s->needs_review),
            'Only scripts the marker flagged should remain in the queue.'
        );
        $this->assertGreaterThan(0, $left->count(), 'The seeded queue should contain flagged scripts.');
    }

    /* ------------------------------------------------------------ parent */

    public function test_parent_screens_render_with_the_childs_real_numbers(): void
    {
        $this->actingAs($this->parent());

        $this->get('/parent')->assertOk()
            ->assertSee('Chidera')
            ->assertSee('days studied');

        $this->get('/parent/report')->assertOk()
            ->assertSee('Weekly progress report')
            ->assertSee('Day by day');
    }

    public function test_the_weekly_report_email_renders(): void
    {
        $child = $this->student();
        $mail = new \App\Mail\WeeklyReport($child, \App\Support\ChildSummary::for($child));

        $rendered = $mail->render();

        $this->assertStringContainsString('ClassMate 9ja', $rendered);
        $this->assertStringContainsString($child->firstName(), $rendered);
    }

    /* ------------------------------------------------------------- admin */

    public function test_admin_screens_render(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin')->assertOk()->assertSee('Platform overview');
        $this->get('/admin/content')->assertOk()->assertSee('Curriculum');
        $this->get('/admin/people')->assertOk()->assertSee('People');
    }

    public function test_a_teacher_cannot_reach_the_admin_area(): void
    {
        $this->actingAs($this->teacher())
            ->get('/admin')
            ->assertRedirect(route('teacher.dashboard'));
    }

    /* --------------------------------------------------------------- api */

    public function test_the_student_app_can_log_in_and_bootstrap(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'student@classmate9ja.ng',
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'role', 'subjects']]);

        $token = $response->json('token');

        $bootstrap = $this->withToken($token)->getJson('/api/bootstrap')->assertOk();

        $this->assertNotEmpty($bootstrap->json('subjects'), 'Bootstrap should carry the student subjects.');
        $this->assertNotEmpty(
            $bootstrap->json('subjects.0.topics'),
            'Every subject should arrive with its topics and lessons.'
        );
    }

    public function test_the_api_rejects_a_bad_token(): void
    {
        $this->withToken('not-a-real-token')->getJson('/api/bootstrap')->assertUnauthorized();
    }

    public function test_pushing_progress_is_idempotent_and_stored(): void
    {
        $token = $this->postJson('/api/login', [
            'email' => 'student@classmate9ja.ng',
            'password' => 'password',
        ])->json('token');

        $payload = ['points' => 4321, 'streak' => 19, 'week' => [10, 20, 30, 40, 50, 0, 0]];

        $this->withToken($token)->postJson('/api/state', ['payload' => $payload])->assertOk();
        $this->withToken($token)->postJson('/api/state', ['payload' => $payload])->assertOk();

        $this->assertSame(4321, $this->student()->fresh()->state->payload['points']);
        $this->assertSame(1, \App\Models\StudentState::where('user_id', $this->student()->id)->count());
    }

    public function test_an_attempt_resent_after_a_dropped_connection_is_not_duplicated(): void
    {
        $token = $this->postJson('/api/login', [
            'email' => 'student@classmate9ja.ng',
            'password' => 'password',
        ])->json('token');

        $body = [
            'clientUuid' => '3f1a7c9e-8b21-4a55-9d0e-2c7b6a4e1f88',
            'subject' => 'maths',
            'kind' => 'mock',
            'title' => 'Mock exam · Mathematics',
            'score' => 31,
            'total' => 40,
            'minutes' => 55,
        ];

        $this->withToken($token)->postJson('/api/attempts', $body)->assertCreated();
        $this->withToken($token)->postJson('/api/attempts', $body)->assertCreated();

        $this->assertSame(
            1,
            \App\Models\Attempt::where('client_uuid', $body['clientUuid'])->count(),
            'client_uuid is what makes a blind retry safe.'
        );
    }

    /* ----------------------------------------------------------- content */

    public function test_the_curriculum_actually_loaded(): void
    {
        $this->assertGreaterThanOrEqual(12, \App\Models\Subject::count());
        $this->assertGreaterThanOrEqual(280, \App\Models\Topic::count());
        $this->assertGreaterThanOrEqual(560, \App\Models\Lesson::count());

        $pidgin = \App\Models\Lesson::where('locale', 'pcm')->count();
        $english = \App\Models\Lesson::where('locale', 'en')->count();

        $this->assertSame($english, $pidgin, 'Every lesson should exist in both locales.');
    }

    public function test_objective_marking_needs_no_ai_key(): void
    {
        $marker = app(\App\Services\Ai\Marker::class);

        // With no key configured the driver declines written answers, which is
        // the behaviour the teacher queue is designed around.
        $this->assertFalse($marker->isConfigured());
        $this->assertNull($marker->mark(
            \App\Models\AssignmentQuestion::where('kind', 'written')->firstOrFail(),
            'some answer'
        ));
    }
}
