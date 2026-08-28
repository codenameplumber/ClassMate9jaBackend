<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The screens themselves, and the small things that made them look broken.
 *
 * Each of these is a bug someone actually hit. They are cheap to assert and
 * they fail loudly, which is the point: a literal `\u{1F3E0}` in a sidebar is
 * obvious to a person in half a second and invisible to every test that only
 * checks a page returned 200.
 */
class InterfaceRegressionTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /* --------------------------------------------------------- navigation */

    /**
     * PHP does not interpret \u{...} inside single quotes.
     *
     * Every sidebar and tab bar was rendering the literal nine characters
     * `\u{1F3E0}` next to each label, for teachers, parents and admins alike.
     */
    public function test_no_navigation_icon_is_a_raw_escape_sequence(): void
    {
        foreach (['teacher', 'parent', 'admin', 'student'] as $role) {
            foreach (Navigation::forRole($role) as $group) {
                foreach ($group['items'] as $item) {
                    $this->assertStringNotContainsString(
                        '\u{',
                        $item['emoji'],
                        "The {$role} nav item `{$item['label']}` is showing a raw escape sequence."
                    );
                }
            }

            foreach (Navigation::tabsForRole($role) as $tab) {
                $this->assertStringNotContainsString('\u{', $tab['emoji']);
            }
        }
    }

    public function test_navigation_icons_render_as_real_emoji(): void
    {
        $home = Navigation::forRole('teacher')[0]['items'][0]['emoji'];

        // A house emoji is four bytes; the broken escape was nine characters.
        $this->assertLessThan(9, strlen($home));
        $this->assertSame('🏠', $home);
    }

    /** The rendered page must not leak an escape sequence either. */
    public function test_the_teacher_sidebar_renders_clean(): void
    {
        $body = $this->actingAs($this->user('teacher@classmate9ja.ng'))
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('\u{', $body);
    }

    /* --------------------------------------------------------- pagination */

    /**
     * Laravel's default pager is Tailwind markup and this app never builds
     * Tailwind, so the admin lists rendered classes that resolved to nothing.
     */
    public function test_admin_lists_use_the_projects_own_pagination(): void
    {
        $admin = $this->user('admin@classmate9ja.ng');

        foreach ([route('admin.people'), route('admin.students'), route('admin.teachers')] as $url) {
            $body = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            // The design system's buttons, not Tailwind's utility classes.
            $this->assertStringNotContainsString('dark:bg-gray-800', $body);
            $this->assertStringNotContainsString('relative inline-flex items-center', $body);
        }
    }

    public function test_the_people_list_actually_paginates(): void
    {
        $body = $this->actingAs($this->user('admin@classmate9ja.ng'))
            ->get(route('admin.people'))
            ->assertOk()
            ->getContent();

        // 363 accounts at 20 a page means there is a next page to go to.
        $this->assertStringContainsString('nextPage', $body);
        $this->assertStringContainsString('aria-label="Pagination"', $body);
    }

    /* ------------------------------------------------------------- logout */

    public function test_signing_out_lands_on_the_sign_in_page(): void
    {
        $this->actingAs($this->user('student@classmate9ja.ng'))
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /* ----------------------------------------------------- password fields */

    /**
     * Every password field can be revealed.
     *
     * Typing a password blind on a phone keyboard is how a student ends up
     * locked out of an account they know the password to.
     */
    public function test_every_password_field_has_a_reveal_button(): void
    {
        $pages = [
            '/login' => 1,
            '/register' => 2,
            '/forgot-password' => 0,
        ];

        foreach ($pages as $url => $expected) {
            $body = $this->get($url)->assertOk()->getContent();

            $fields = substr_count($body, 'type="password"');
            $toggles = substr_count($body, 'data-password-toggle');

            $this->assertSame($expected, $fields, "{$url} has an unexpected number of password fields.");
            $this->assertSame(
                $fields,
                $toggles,
                "{$url} has {$fields} password fields but {$toggles} reveal buttons."
            );
        }
    }

    public function test_the_reset_password_screen_reveals_both_fields(): void
    {
        $user = $this->user('student@classmate9ja.ng');
        $token = \Illuminate\Support\Facades\Password::createToken($user);

        $body = $this->get("/reset-password/{$token}?email=" . urlencode($user->email))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($body, 'type="password"'));
        $this->assertSame(2, substr_count($body, 'data-password-toggle'));
    }

    /** The script has to be present, or the buttons are decoration. */
    public function test_the_toggle_script_is_loaded_wherever_a_password_is_typed(): void
    {
        $this->assertStringContainsString('password-toggle.js', $this->get('/login')->getContent());

        $this->assertStringContainsString(
            'password-toggle.js',
            $this->actingAs($this->user('student@classmate9ja.ng'))
                ->get('/learn/settings')
                ->getContent()
        );
    }

    /* ------------------------------------------------------- staff on a phone */

    /**
     * No staff view may hard-code its column layout inline.
     *
     * An inline style beats any stylesheet, so a grid written into the markup
     * can never be stacked by a media query. That is precisely why the teacher,
     * parent and admin pages stayed two columns at 360px and squeezed every
     * card into a strip. The layout belongs in a class.
     */
    public function test_no_staff_view_hard_codes_its_columns(): void
    {
        $offenders = [];

        foreach (glob(resource_path('views/livewire/**/*.blade.php'), GLOB_BRACE) as $file) {
            $markup = file_get_contents($file);

            if (preg_match('/style="[^"]*grid-template-columns/', $markup)) {
                $offenders[] = str_replace(resource_path('views/'), '', $file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These views set grid-template-columns inline, so no breakpoint can reach them: '
            . implode(', ', $offenders)
        );
    }

    /** The two-column split has to have somewhere to go on a narrow screen. */
    public function test_the_staff_split_stacks_on_a_phone(): void
    {
        $css = file_get_contents(public_path('app/css/pages/app.css'));

        $this->assertStringContainsString('.split {', $css);
        $this->assertMatchesRegularExpression(
            '/@media[^{]*max-width:\s*900px[^{]*\{[^}]*\.split/s',
            $css,
            'The staff two-column split never stacks.'
        );
    }

    /** Every staff screen that splits in two uses the class that can stack. */
    public function test_the_staff_screens_use_the_split_class(): void
    {
        $screens = [
            'livewire/teacher/dashboard.blade.php',
            'livewire/teacher/classes.blade.php',
            'livewire/parent/dashboard.blade.php',
            'livewire/admin/content.blade.php',
        ];

        foreach ($screens as $screen) {
            $this->assertStringContainsString(
                'class="split"',
                str_replace('class="split" style', 'class="split"|style',
                    file_get_contents(resource_path('views/' . $screen))),
                "{$screen} is not using the stacking split."
            );
        }
    }

    /** A wide table must scroll inside itself, not take the page sideways. */
    public function test_every_staff_table_can_scroll_on_its_own(): void
    {
        foreach (glob(resource_path('views/livewire/**/*.blade.php'), GLOB_BRACE) as $file) {
            $markup = file_get_contents($file);

            if (! str_contains($markup, '<table')) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/table-wrap|overflow-x/',
                $markup,
                str_replace(resource_path('views/'), '', $file) . ' has a table with nothing to scroll it.'
            );
        }
    }

    /* ----------------------------------------------------- the landing page */

    /**
     * An inline grid-template cannot be overridden by a media query, so the
     * problem section stayed two columns at 360px and crushed the headline.
     */
    public function test_the_problem_section_is_not_hard_coded_to_two_columns(): void
    {
        $body = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('problem-grid', $body);
        $this->assertStringNotContainsString('grid-template-columns:1fr 1.15fr', $body);
    }

    public function test_the_landing_page_has_a_breakpoint_for_that_section(): void
    {
        $css = file_get_contents(public_path('app/css/pages/home.css'));

        $this->assertStringContainsString('.problem-grid', $css);
        $this->assertMatchesRegularExpression(
            '/@media[^{]*max-width:\s*860px[^{]*\{[^}]*\.problem-grid/s',
            $css,
            'The problem section has no rule that stacks it on a phone.'
        );
    }
}
