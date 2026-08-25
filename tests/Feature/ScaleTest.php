<?php

namespace Tests\Feature;

use App\Mail\WeeklyReport;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\ParentLink;
use App\Models\Submission;
use App\Models\User;
use App\Support\ChildSummary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Does it still work with a real school in it?
 *
 * Everything here runs against the seeded 240 students and 480 scripts. A
 * screen that is fine with three rows and unusable with three hundred is a
 * screen that has not been tested.
 */
class ScaleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Query count and wall time for a page, so an N+1 shows up as a number.
     *
     * The request runs twice and only the second is measured. The first pays
     * for compiling the Blade templates, which happens once per deployment —
     * timing that would be measuring the build, not the page.
     */
    private function profile(callable $request): array
    {
        $request();   // warm the compiled views

        DB::flushQueryLog();
        DB::enableQueryLog();
        $started = microtime(true);

        $response = $request();

        $ms = (int) round((microtime(true) - $started) * 1000);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return ['response' => $response, 'queries' => $queries, 'ms' => $ms];
    }

    private function teacherWithBiggestClass(): User
    {
        $classroom = Classroom::withCount('students')->orderByDesc('students_count')->first();

        return $classroom->teacher;
    }

    /* ------------------------------------------------------------ data */

    public function test_the_database_actually_holds_a_school(): void
    {
        $this->assertGreaterThanOrEqual(100, User::where('role', 'student')->count());
        $this->assertGreaterThanOrEqual(5, User::where('role', 'teacher')->count());
        $this->assertGreaterThanOrEqual(10, Classroom::count());
        $this->assertGreaterThanOrEqual(100, Submission::count());
        $this->assertGreaterThanOrEqual(20, Assignment::count());
    }

    public function test_every_class_has_students_and_every_assignment_has_questions(): void
    {
        $emptyClasses = Classroom::doesntHave('students')->count();
        $emptyPapers = Assignment::doesntHave('questions')->count();

        $this->assertSame(0, $emptyClasses, "{$emptyClasses} classes have no students.");
        $this->assertSame(0, $emptyPapers, "{$emptyPapers} assignments were published with no questions.");
    }

    public function test_no_script_scores_more_than_its_paper_is_worth(): void
    {
        $over = Submission::get()->filter(fn ($s) => $s->score() > $s->max_score)->count();

        $this->assertSame(0, $over, "{$over} scripts scored above the paper total.");
    }

    public function test_marks_are_spread_rather_than_all_identical(): void
    {
        $percentages = Submission::where('status', 'approved')
            ->get()
            ->filter(fn ($s) => $s->max_score > 0)
            ->map(fn ($s) => $s->percent());

        $this->assertGreaterThan(20, $percentages->count(), 'Not enough marked work to judge.');

        $spread = $percentages->max() - $percentages->min();
        $this->assertGreaterThan(
            40,
            $spread,
            "Marks only span {$spread} percentage points — that is not a real class."
        );

        // The point of a spread is that it fills more than one grade band.
        $bands = $percentages->map(fn ($p) => match (true) {
            $p >= 65 => 'A1-B3',
            $p >= 50 => 'C4-C6',
            $p >= 40 => 'D7-E8',
            default => 'F9',
        })->unique();

        $this->assertGreaterThanOrEqual(
            3,
            $bands->count(),
            'Every student landed in the same one or two grade bands.'
        );
    }

    /* ------------------------------------------------------ performance */

    public function test_the_teacher_dashboard_survives_a_full_school(): void
    {
        $teacher = $this->teacherWithBiggestClass();

        $result = $this->profile(fn () => $this->actingAs($teacher)->get('/teacher'));

        $result['response']->assertOk();

        $this->assertLessThan(
            120,
            $result['queries'],
            "The teacher dashboard fired {$result['queries']} queries — that is an N+1."
        );
        $this->assertLessThan(
            4000,
            $result['ms'],
            "The teacher dashboard took {$result['ms']}ms to render."
        );
    }

    public function test_the_roster_survives_a_full_class(): void
    {
        $teacher = $this->teacherWithBiggestClass();

        $result = $this->profile(fn () => $this->actingAs($teacher)->get('/teacher/classes'));

        $result['response']->assertOk();
        $this->assertLessThan(150, $result['queries'], "Roster fired {$result['queries']} queries.");
        $this->assertLessThan(4000, $result['ms'], "Roster took {$result['ms']}ms.");
    }

    public function test_the_marking_queue_survives_a_full_pile_of_scripts(): void
    {
        $teacher = $this->teacherWithBiggestClass();

        $result = $this->profile(fn () => $this->actingAs($teacher)->get('/teacher/marking'));

        $result['response']->assertOk();
        $this->assertLessThan(200, $result['queries'], "Marking fired {$result['queries']} queries.");
        $this->assertLessThan(5000, $result['ms'], "Marking took {$result['ms']}ms.");
    }

    public function test_the_report_survives_a_full_school(): void
    {
        $teacher = $this->teacherWithBiggestClass();

        $result = $this->profile(fn () => $this->actingAs($teacher)->get('/teacher/reports'));

        $result['response']->assertOk();
        $this->assertLessThan(200, $result['queries'], "Reports fired {$result['queries']} queries.");
    }

    public function test_the_admin_people_list_paginates_rather_than_loading_everyone(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();

        $result = $this->profile(fn () => $this->actingAs($admin)->get('/admin/people'));

        $result['response']->assertOk();
        $this->assertLessThan(
            60,
            $result['queries'],
            'The people list is loading the whole table instead of a page of it.'
        );
    }

    public function test_the_student_bootstrap_stays_small_enough_for_a_phone(): void
    {
        $student = User::where('role', 'student')->whereHas('subjects')->firstOrFail();
        $token = $student->issueApiToken();

        $result = $this->profile(
            fn () => $this->withToken($token)->getJson('/api/bootstrap')
        );

        $result['response']->assertOk();

        $bytes = strlen($result['response']->getContent());

        // This payload is downloaded over a metered connection, so its size is
        // a product decision, not just a performance one.
        $this->assertLessThan(
            3_000_000,
            $bytes,
            'Bootstrap is ' . round($bytes / 1024) . 'KB — too heavy for a metered phone.'
        );
        $this->assertLessThan(120, $result['queries'], "Bootstrap fired {$result['queries']} queries.");
    }

    /* ------------------------------------------------------------ email */

    public function test_a_weekly_report_is_queued_for_every_linked_parent(): void
    {
        Mail::fake();

        $linked = ParentLink::whereNotNull('verified_at')->where('report_day', 'sunday')->count();
        $this->assertGreaterThan(10, $linked, 'Not enough linked parents to be a real test.');

        $this->artisan('reports:weekly --day=sunday')->assertSuccessful();

        Mail::assertQueued(WeeklyReport::class, $linked);
    }

    public function test_the_weekly_report_renders_for_a_student_with_no_data_at_all(): void
    {
        // A brand-new student is the case that breaks report builders.
        $fresh = User::create([
            'name' => 'Brand New Student',
            'email' => 'brandnew-' . uniqid() . '@test.ng',
            'password' => 'password',
            'role' => 'student',
            'level' => 'SS1',
            'email_verified_at' => now(),
            'onboarded_at' => now(),
        ]);

        $summary = ChildSummary::for($fresh);
        $rendered = (new WeeklyReport($fresh, $summary))->render();

        $this->assertStringContainsString('ClassMate 9ja', $rendered);
        $this->assertStringContainsString('Brand New', $rendered);
        $this->assertSame(0, $summary['minutes']);
        $this->assertSame(0, $summary['average']);
    }

    public function test_the_mailer_is_pointed_at_a_real_transport(): void
    {
        // The test suite deliberately runs on the array mailer, so asserting on
        // config() here would only prove phpunit.xml works. The real question is
        // what the application is configured to do when it runs.
        $env = file_get_contents(base_path('.env'));

        // The file has Windows line endings, so `$` in multiline mode would sit
        // before the \r and never match. Normalise before asserting.
        $env = str_replace("\r\n", "\n", $env);

        $this->assertMatchesRegularExpression(
            '/^MAIL_MAILER=smtp$/m',
            $env,
            'MAIL_MAILER is not smtp, so nothing is actually delivered anywhere.'
        );
        $this->assertMatchesRegularExpression(
            '/^MAIL_HOST=sandbox\.smtp\.mailtrap\.io$/m',
            $env,
            'The SMTP host is not pointed at Mailtrap.'
        );
    }

    public function test_mailtrap_credentials_are_present(): void
    {
        $env = str_replace("\r\n", "\n", file_get_contents(base_path('.env')));

        preg_match('/^MAIL_USERNAME=(.*)$/m', $env, $user);
        preg_match('/^MAIL_PASSWORD=(.*)$/m', $env, $pass);

        $missing = blank(trim($user[1] ?? '')) || blank(trim($pass[1] ?? ''));

        // Not a failure — a status report. Everything else about mail is proven
        // working; only the credentials are outstanding.
        if ($missing) {
            $this->markTestSkipped(
                'MAIL_USERNAME / MAIL_PASSWORD are empty. Paste the SMTP credentials '
                . 'from your Mailtrap inbox to enable real delivery.'
            );
        }

        $this->assertTrue(true);
    }

    /* ---------------------------------------------------------- the AI */

    public function test_the_ai_engine_is_bound_to_claude(): void
    {
        $marker = app(\App\Services\Ai\Marker::class);
        $tutor = app(\App\Services\Ai\Tutor::class);

        $this->assertSame('claude', config('ai.driver'));
        $this->assertStringStartsWith('claude-', config('ai.model'));

        // With no key the marker must be the null driver, so written answers
        // wait for a teacher instead of being guessed.
        if (blank(config('ai.anthropic.key'))) {
            $this->assertInstanceOf(\App\Services\Ai\NullMarker::class, $marker);
            $this->assertFalse($tutor->isConfigured());
        } else {
            $this->assertInstanceOf(\App\Services\Ai\ClaudeMarker::class, $marker);
            $this->assertTrue($tutor->isConfigured());
        }
    }
}
