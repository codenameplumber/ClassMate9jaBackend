<?php

namespace Tests\Feature;

use App\Livewire\Admin\Courses;
use App\Livewire\Admin\Students;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SubjectLevel;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin surface: courses, teachers, students.
 *
 * The destructive paths get the most attention. Removing a course cascades to
 * its topics, its lessons and every question written for it, so the guards
 * against doing that to a live subject are the part worth testing.
 */
class AdminScreensTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('email', 'admin@classmate9ja.ng')->firstOrFail();
    }

    public function test_every_admin_screen_renders(): void
    {
        $this->actingAs($this->admin());

        foreach (['/admin', '/admin/courses', '/admin/content', '/admin/teachers', '/admin/students', '/admin/people'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_a_student_cannot_reach_the_admin_area(): void
    {
        $student = User::where('email', 'student@classmate9ja.ng')->firstOrFail();

        $this->actingAs($student)->get('/admin/courses')
            ->assertRedirect(route('learn.screen', 'dashboard'));
    }

    /* ------------------------------------------------------------ creating */

    public function test_an_admin_can_add_a_course_with_its_levels(): void
    {
        $slug = 'basic-science-' . Str::lower(Str::random(6));

        Livewire::actingAs($this->admin())->test(Courses::class)
            ->call('create')
            ->set('name', 'Basic Science')
            ->set('shortName', 'Basic Sci.')
            ->set('slug', $slug)
            ->set('emoji', '🔬')
            ->call('toggleLevel', 'JSS1')
            ->call('toggleLevel', 'JSS2')
            ->call('toggleLevel', 'JSS3')
            ->call('save')
            ->assertHasNoErrors();

        $subject = Subject::where('slug', $slug)->firstOrFail();

        $levels = $subject->levels()->pluck('level')->sort()->values()->all();
        $this->assertSame(['JSS1', 'JSS2', 'JSS3'], $levels);

        // Declared but empty: nobody should be offered it yet.
        $this->assertSame([], $subject->publishedLevels());
        $this->assertFalse($subject->offersLevel('JSS1'));
    }

    public function test_a_course_with_no_levels_is_refused(): void
    {
        Livewire::actingAs($this->admin())->test(Courses::class)
            ->call('create')
            ->set('name', 'Nowhere Studies')
            ->set('shortName', 'Nowhere')
            ->set('slug', 'nowhere-' . Str::lower(Str::random(6)))
            ->call('save')
            ->assertHasErrors('levels');
    }

    public function test_a_duplicate_slug_is_refused(): void
    {
        Livewire::actingAs($this->admin())->test(Courses::class)
            ->call('create')
            ->set('name', 'Another Maths')
            ->set('shortName', 'Maths 2')
            ->set('slug', 'maths')
            ->call('toggleLevel', 'SS1')
            ->call('save')
            ->assertHasErrors('slug');
    }

    /* ------------------------------------------------------------ removing */

    public function test_a_course_students_are_taking_cannot_be_removed(): void
    {
        $maths = Subject::where('slug', 'maths')->firstOrFail();

        $this->assertGreaterThan(0, $maths->students()->count());

        Livewire::actingAs($this->admin())->test(Courses::class)
            ->call('confirmDelete', $maths->id)
            ->call('delete');

        $this->assertDatabaseHas('subjects', ['id' => $maths->id]);
    }

    public function test_a_course_a_class_runs_on_cannot_be_removed(): void
    {
        $subject = Subject::create([
            'slug' => 'orphan-' . Str::lower(Str::random(6)),
            'name' => 'Orphan Studies', 'short_name' => 'Orphan',
            'emoji' => '📕', 'tone' => 'brand', 'is_core' => false,
        ]);

        $teacher = User::where('email', 'teacher@classmate9ja.ng')->firstOrFail();

        Classroom::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'name' => 'SS1Z', 'level' => 'SS1',
            'join_code' => 'ORPH-' . Str::upper(Str::random(4)),
        ]);

        Livewire::actingAs($this->admin())->test(Courses::class)
            ->call('confirmDelete', $subject->id)
            ->call('delete');

        $this->assertDatabaseHas('subjects', ['id' => $subject->id]);
    }

    public function test_an_unused_course_can_be_removed_with_its_content(): void
    {
        $subject = Subject::create([
            'slug' => 'disposable-' . Str::lower(Str::random(6)),
            'name' => 'Disposable Studies', 'short_name' => 'Disposable',
            'emoji' => '🗑️', 'tone' => 'brand', 'is_core' => false,
        ]);

        SubjectLevel::create(['subject_id' => $subject->id, 'level' => 'SS1', 'status' => 'draft']);

        $topic = Topic::create([
            'subject_id' => $subject->id, 'level' => 'SS1', 'ref' => 'w1t1',
            'week' => 1, 'week_title' => 'W', 'title' => 'T', 'minutes' => 20, 'position' => 1,
        ]);

        Livewire::actingAs($this->admin())->test(Courses::class)
            ->call('confirmDelete', $subject->id)
            ->call('delete');

        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
        $this->assertDatabaseMissing('topics', ['id' => $topic->id]);
        $this->assertDatabaseMissing('subject_levels', ['subject_id' => $subject->id]);
    }

    /**
     * Unticking a level that holds content must not quietly delete it.
     *
     * Students have already downloaded that year. Dropping it is a decision,
     * not a checkbox nobody meant to clear.
     */
    public function test_a_level_holding_content_is_kept_when_unticked(): void
    {
        $maths = Subject::where('slug', 'maths')->firstOrFail();

        $before = $maths->levels()->where('status', 'published')->pluck('level')->all();
        $this->assertNotEmpty($before);

        Livewire::actingAs($this->admin())->test(Courses::class)
            ->call('edit', $maths->id)
            ->set('levels', [])            // untick everything
            ->call('save');

        $after = $maths->fresh()->levels()->pluck('level')->all();

        foreach ($before as $level) {
            $this->assertContains($level, $after, "{$level} held content and was deleted anyway.");
        }
    }

    /* ------------------------------------------------------------ students */

    public function test_the_student_list_paginates_rather_than_loading_everyone(): void
    {
        $total = User::where('role', 'student')->count();

        $this->assertGreaterThan(50, $total, 'Not enough students for this to prove anything.');

        $response = $this->actingAs($this->admin())->get('/admin/students');

        $response->assertOk();

        // The seeded set is far larger than a page, so the first page must not
        // contain every name.
        $names = User::where('role', 'student')->orderBy('name')->pluck('name');

        $response->assertSee($names->first(), false);
        $response->assertDontSee($names->last(), false);
    }

    public function test_the_student_list_can_be_filtered_by_level(): void
    {
        $ss1 = User::where('role', 'student')->where('level', 'SS1')->orderBy('name')->first();
        $ss2 = User::where('role', 'student')->where('level', 'SS2')->orderBy('name')->first();

        $this->assertNotNull($ss1);
        $this->assertNotNull($ss2);

        Livewire::actingAs($this->admin())->test(Students::class)
            ->set('level', 'SS1')
            ->assertSee($ss1->name)
            ->assertDontSee($ss2->name);
    }

    public function test_searching_students_narrows_the_list(): void
    {
        $target = User::where('email', 'student@classmate9ja.ng')->firstOrFail();

        Livewire::actingAs($this->admin())->test(Students::class)
            ->set('search', $target->name)
            ->assertSee($target->email);
    }
}
