<?php

namespace Tests\Feature;

use App\Livewire\Admin\Teachers;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\TeacherSetup;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Registering as a teacher, and an admin deciding whether that is true.
 *
 * Before this, anyone could register with role=teacher and land straight on
 * the teacher dashboard. The account looked empty because it owned no classes,
 * but it was inside the staff app, and the moment an admin attached a class to
 * it, it could mark other people's children.
 */
class TeacherApprovalTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('email', 'admin@classmate9ja.ng')->firstOrFail();
    }

    private function registerTeacher(): User
    {
        $email = 'newteacher-' . Str::random(8) . '@test.ng';

        Livewire::test(Register::class)
            ->set('name', 'Mr. Applicant Teacher')
            ->set('email', $email)
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('role', 'teacher')
            ->set('terms', true)
            ->call('register');

        return User::where('email', $email)->firstOrFail();
    }

    /* ------------------------------------------------------- registering */

    public function test_a_new_teacher_account_starts_out_pending(): void
    {
        $teacher = $this->registerTeacher();

        $this->assertSame('teacher', $teacher->role);
        $this->assertTrue($teacher->isPending());
        $this->assertFalse($teacher->isActive());
    }

    public function test_a_new_student_account_does_not_wait_for_anyone(): void
    {
        $email = 'newstudent-' . Str::random(8) . '@test.ng';

        Livewire::test(Register::class)
            ->set('name', 'A New Student')
            ->set('email', $email)
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->set('role', 'student')
            ->set('terms', true)
            ->call('register');

        $student = User::where('email', $email)->firstOrFail();

        $this->assertTrue($student->isApproved());
        $this->assertTrue($student->isActive());
    }

    /* ------------------------------------------------------------ gating */

    public function test_a_pending_teacher_cannot_reach_the_marking_queue(): void
    {
        $teacher = $this->registerTeacher();
        $teacher->forceFill(['onboarded_at' => now()])->save();

        $this->actingAs($teacher)->get('/teacher/marking')
            ->assertRedirect(route('teacher.pending'));

        $this->actingAs($teacher)->get('/teacher')
            ->assertRedirect(route('teacher.pending'));
    }

    public function test_a_teacher_who_has_not_said_what_they_teach_is_sent_to_setup(): void
    {
        $teacher = $this->registerTeacher();

        $this->assertSame(route('teacher.setup'), $teacher->homeRoute());
        $this->actingAs($teacher)->get('/teacher/setup')->assertOk();
    }

    public function test_an_approved_teacher_reaches_their_dashboard(): void
    {
        $teacher = User::where('email', 'teacher@classmate9ja.ng')->firstOrFail();

        $this->assertTrue($teacher->isApproved());
        $this->actingAs($teacher)->get('/teacher')->assertOk();
    }

    /* ------------------------------------------------------------- setup */

    public function test_setup_records_the_school_and_what_they_teach(): void
    {
        $teacher = $this->registerTeacher();
        $maths = Subject::where('slug', 'maths')->firstOrFail();

        Livewire::actingAs($teacher)->test(TeacherSetup::class)
            ->set('school', 'Government Secondary School, Wuse')
            ->set('phone', '08030000000')
            ->call('toggle', $maths->id, 'SS1')
            ->call('toggle', $maths->id, 'SS2')
            ->call('submit')
            ->assertHasNoErrors();

        $teacher->refresh();

        $this->assertNotNull($teacher->onboarded_at);
        $this->assertNotNull($teacher->school_id);
        $this->assertTrue($teacher->isPending(), 'Setup must not approve the account.');

        // Two rows, one per level — the thing a plain pivot could not hold.
        $levels = $teacher->teachingSubjects->pluck('pivot.level')->sort()->values()->all();
        $this->assertSame(['SS1', 'SS2'], $levels);
    }

    public function test_setup_refuses_with_no_subject_chosen(): void
    {
        $teacher = $this->registerTeacher();

        Livewire::actingAs($teacher)->test(TeacherSetup::class)
            ->set('school', 'Some Secondary School')
            ->call('submit')
            ->assertHasErrors('teaching');

        $this->assertNull($teacher->fresh()->onboarded_at);
    }

    /* ---------------------------------------------------------- deciding */

    public function test_an_admin_approving_lets_the_teacher_in(): void
    {
        $teacher = $this->registerTeacher();
        $teacher->forceFill(['onboarded_at' => now()])->save();

        Livewire::actingAs($this->admin())->test(Teachers::class)
            ->call('approve', $teacher->id);

        $teacher->refresh();

        $this->assertTrue($teacher->isApproved());
        $this->assertSame($this->admin()->id, $teacher->approved_by);
        $this->assertNotNull($teacher->approved_at);

        $this->actingAs($teacher)->get('/teacher')->assertOk();
    }

    public function test_turning_a_teacher_down_requires_a_reason(): void
    {
        $teacher = $this->registerTeacher();

        Livewire::actingAs($this->admin())->test(Teachers::class)
            ->call('startReject', $teacher->id)
            ->set('rejectReason', '')
            ->call('reject')
            ->assertHasErrors('rejectReason');

        $this->assertTrue($teacher->fresh()->isPending());
    }

    public function test_a_rejected_teacher_is_told_why(): void
    {
        $teacher = $this->registerTeacher();

        Livewire::actingAs($this->admin())->test(Teachers::class)
            ->call('startReject', $teacher->id)
            ->set('rejectReason', 'We have no record of you at this school.')
            ->call('reject')
            ->assertHasNoErrors();

        $teacher->refresh();

        $this->assertTrue($teacher->isRejected());
        $this->assertSame('We have no record of you at this school.', $teacher->rejected_reason);

        $this->actingAs($teacher)->get('/teacher/pending')
            ->assertOk()
            ->assertSee('no record of you at this school', false);
    }

    /**
     * The ids arrive as Livewire properties, which the browser sets.
     *
     * Posting a student id at approve() would otherwise stamp them approved,
     * and the role gate would then wave them into the staff app.
     */
    public function test_the_approve_action_refuses_an_id_that_is_not_a_teacher(): void
    {
        $student = User::where('email', 'student@classmate9ja.ng')->firstOrFail();

        Livewire::actingAs($this->admin())->test(Teachers::class)
            ->call('approve', $student->id)
            ->assertStatus(404);

        $this->assertSame('student', $student->fresh()->role);
    }

    public function test_an_admin_can_change_what_a_teacher_teaches(): void
    {
        $teacher = User::where('email', 'teacher@classmate9ja.ng')->firstOrFail();
        $english = Subject::where('slug', 'english')->firstOrFail();

        Livewire::actingAs($this->admin())->test(Teachers::class)
            ->call('edit', $teacher->id)
            ->call('toggle', $english->id, 'SS2')
            ->call('saveTeaching');

        $this->assertTrue(
            $teacher->fresh()->teachingSubjects->contains('id', $english->id)
        );
    }

    public function test_the_admin_teacher_screen_renders(): void
    {
        $this->actingAs($this->admin())->get('/admin/teachers')->assertOk();
    }

    public function test_a_teacher_cannot_open_the_admin_teacher_screen(): void
    {
        $teacher = User::where('email', 'teacher@classmate9ja.ng')->firstOrFail();

        $this->actingAs($teacher)->get('/admin/teachers')
            ->assertRedirect(route('teacher.dashboard'));
    }
}
