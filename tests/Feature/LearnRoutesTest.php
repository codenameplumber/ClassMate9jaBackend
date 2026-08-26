<?php

namespace Tests\Feature;

use App\Http\Controllers\LearnController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The student app, now that it is routes rather than files.
 *
 * Two things are being proven here. That every screen still exists at a clean
 * URL — no .html anywhere — and that the pages are actually gated, which they
 * were not before: the old shells were static files any browser could open,
 * and the account they trusted lived in local storage.
 */
class LearnRoutesTest extends TestCase
{
    use DatabaseTransactions;

    private function student(): User
    {
        return User::where('email', 'student@classmate9ja.ng')->firstOrFail();
    }

    public function test_every_screen_answers_at_a_clean_url(): void
    {
        $this->actingAs($this->student());

        foreach (array_keys(LearnController::SCREENS) as $screen) {
            $this->get("/learn/{$screen}")
                ->assertOk()
                ->assertSee('/app/js/pages/' . LearnController::SCREENS[$screen][0] . '.js', false);
        }
    }

    public function test_no_screen_url_carries_a_file_extension(): void
    {
        $this->actingAs($this->student());

        foreach (array_keys(LearnController::SCREENS) as $screen) {
            $this->assertStringNotContainsString('.html', "/learn/{$screen}");
        }

        // The rendered page must not link back to the old files either.
        $body = $this->get('/learn/dashboard')->getContent();

        $this->assertStringNotContainsString('/app/student/', $body);
        $this->assertStringNotContainsString('.html', $body);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/learn/dashboard')->assertRedirect('/login');
    }

    /** The old static shells were readable by anyone who knew the path. */
    public function test_the_old_static_shells_are_gone(): void
    {
        $this->get('/app/student/dashboard.html')->assertNotFound();
        $this->get('/app/auth/login.html')->assertNotFound();
    }

    public function test_an_unknown_screen_is_a_404_not_a_blank_page(): void
    {
        $this->actingAs($this->student());

        $this->get('/learn/nonsense')->assertNotFound();
    }

    public function test_learn_redirects_to_the_dashboard(): void
    {
        $this->actingAs($this->student());

        $this->get('/learn')->assertRedirect('/learn/dashboard');
    }

    /**
     * The page hands the device its credentials, so the app inside never has
     * to ask the student to sign in a second time.
     */
    public function test_the_page_issues_an_api_token_the_device_can_use(): void
    {
        $student = $this->student();

        $body = $this->actingAs($student)->get('/learn/dashboard')->getContent();

        preg_match('/cm9ja:api-token., JSON\.stringify\("([^"]+)"\)/', $body, $match);

        $this->assertNotEmpty($match[1] ?? null, 'No API token was injected into the page.');

        // And it is a real one: it authenticates against the API.
        $this->withToken($match[1])->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', $student->email);
    }

    /** Re-rendering must not invalidate the token the device is already holding. */
    public function test_the_token_is_stable_across_page_loads(): void
    {
        $this->actingAs($this->student());

        $first = $this->tokenFrom($this->get('/learn/dashboard')->getContent());
        $second = $this->tokenFrom($this->get('/learn/practice')->getContent());

        $this->assertSame($first, $second);
    }

    public function test_a_student_who_has_not_onboarded_is_sent_to_finish(): void
    {
        $fresh = User::create([
            'name' => 'Fresh Student ' . Str::random(4),
            'email' => 'fresh-' . Str::random(8) . '@test.ng',
            'password' => 'password',
            'role' => 'student',
            'level' => 'SS2',
        ]);

        $this->actingAs($fresh)->get('/learn/dashboard')->assertRedirect(route('onboarding'));
    }

    public function test_a_teacher_landing_on_the_student_app_is_sent_to_their_own(): void
    {
        $teacher = User::where('email', 'teacher@classmate9ja.ng')->firstOrFail();

        $this->actingAs($teacher)->get('/learn/dashboard')
            ->assertRedirect(route('teacher.dashboard'));
    }

    private function tokenFrom(string $body): ?string
    {
        preg_match('/cm9ja:api-token., JSON\.stringify\("([^"]+)"\)/', $body, $match);

        return $match[1] ?? null;
    }
}
