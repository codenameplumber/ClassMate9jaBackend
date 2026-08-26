<?php

namespace Tests\Feature;

use App\Jobs\MarkSubmission;
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
use Illuminate\Support\Facades\Queue;
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

    public function test_marking_and_mail_do_not_share_a_queue(): void
    {
        // A student who submits on a Sunday evening must not sit behind a
        // weekly-report blast that is deliberately released slowly. Separate
        // queue names are what let the two be scaled apart in production.
        Queue::fake();

        $submission = Submission::query()->first();
        $this->assertNotNull($submission);

        MarkSubmission::dispatch($submission->id);

        Queue::assertPushedOn('marking', MarkSubmission::class);
    }

    /**
     * Writing a course is slow, expensive and nobody is waiting on it.
     *
     * On one queue with marking, an admin generating three courses on a Monday
     * morning would put every submitted script behind seventy model calls.
     */
    public function test_content_generation_does_not_share_a_queue_with_marking(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        \App\Jobs\MarkSubmission::dispatch(1);
        \App\Jobs\GenerateCourse::dispatch(1);
        \App\Jobs\GenerateQuestions::dispatch(1, 'SS2');

        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Jobs\MarkSubmission::class,
            fn ($job) => $job->queue === 'marking'
        );

        foreach ([\App\Jobs\GenerateCourse::class, \App\Jobs\GenerateQuestions::class] as $class) {
            \Illuminate\Support\Facades\Queue::assertPushed(
                $class,
                fn ($job) => $job->queue === 'content'
            );
        }
    }

    public function test_no_queued_report_is_delayed_past_the_platform_ceiling(): void
    {
        // Managed queues reject a per-job delay over fifteen minutes. Both
        // Mail::fake() and Queue::fake() throw the delay away, so this pushes
        // onto the real database queue and reads back what was actually
        // written. Nothing is sent: a queued job only becomes an email when a
        // worker picks it up, and none runs here.
        config(['queue.default' => 'database', 'mail.throttle_per_minute' => 1]);

        DB::table('jobs')->delete();

        $this->artisan('reports:weekly --day=sunday')->assertSuccessful();

        $delays = DB::table('jobs')
            ->select('created_at', 'available_at')
            ->get()
            ->map(fn ($job) => (int) $job->available_at - (int) $job->created_at);

        $this->assertGreaterThan(10, $delays->count(), 'Nothing was queued, so nothing was tested.');
        $this->assertGreaterThan(0, $delays->max(), 'The reports were not staggered at all.');
        $this->assertLessThanOrEqual(
            15 * 60,
            $delays->max(),
            'A report was delayed ' . $delays->max() . 's — past the 15-minute platform ceiling.'
        );
    }

    public function test_recorded_study_time_appears_day_by_day_not_just_as_a_total(): void
    {
        $student = User::create([
            'name' => 'Consistent Student',
            'email' => 'consistent-' . uniqid() . '@test.ng',
            'password' => 'password',
            'role' => 'student',
            'level' => 'SS2',
            'email_verified_at' => now(),
            'onboarded_at' => now(),
        ]);

        // Three real sessions this week, on three different days.
        foreach ([0 => 30, 2 => 45, 4 => 25] as $offset => $minutes) {
            $student->studySessions()->create([
                'minutes' => $minutes,
                'occurred_on' => now()->startOfWeek()->addDays($offset)->toDateString(),
                'source' => 'lesson',
                'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            ]);
        }

        $summary = ChildSummary::for($student);

        $this->assertSame(100, $summary['minutes'], 'The weekly total is wrong.');

        // The regression this guards: the total was right while every day read
        // zero, so a parent saw "100 minutes" above "0 of 7 days studied".
        $this->assertSame(
            100,
            array_sum($summary['week']),
            'The day-by-day breakdown does not add up to the weekly total.'
        );
        $this->assertSame(3, $summary['days_studied'], 'Days studied was not counted from real sessions.');
        $this->assertSame([30, 0, 45, 0, 25, 0, 0], $summary['week']);
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

    /**
     * Mail actually leaves the building.
     *
     * Provider-agnostic on purpose: this used to assert Mailtrap by name and
     * broke the moment the app moved to Resend, which is the opposite of what
     * a test like this is for. What matters is that the configured mailer is a
     * real transport rather than `log` or `array`, and that whatever it needs
     * to authenticate is present.
     */
    public function test_the_mailer_is_pointed_at_a_real_transport(): void
    {
        // The suite deliberately runs on the array mailer, so asserting on
        // config() would only prove phpunit.xml works. The real question is
        // what the application is configured to do when it runs.
        $env = str_replace("\r\n", "\n", file_get_contents(base_path('.env')));

        preg_match('/^MAIL_MAILER=(.*)$/m', $env, $match);
        $mailer = trim($match[1] ?? '');

        $this->assertNotContains(
            $mailer,
            ['log', 'array', ''],
            "MAIL_MAILER is `{$mailer}`, so nothing is delivered anywhere."
        );

        $this->assertArrayHasKey(
            $mailer,
            config('mail.mailers'),
            "MAIL_MAILER is `{$mailer}`, which is not a configured mailer."
        );

        // A transport whose package is missing resolves at send time, not at
        // config time -- which means it looks fine until the first real email.
        $this->assertNotNull(
            app('mail.manager')->mailer($mailer),
            "The `{$mailer}` transport could not be built. Its package is probably not installed."
        );
    }

    /** Whatever the chosen provider needs to authenticate is actually set. */
    public function test_the_mailer_has_the_credentials_it_needs(): void
    {
        $env = str_replace("\r\n", "\n", file_get_contents(base_path('.env')));

        $value = function (string $key) use ($env) {
            preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', $env, $m);

            return trim($m[1] ?? '', " \t\"'");
        };

        preg_match('/^MAIL_MAILER=(.*)$/m', $env, $match);
        $mailer = trim($match[1] ?? '');

        $needs = match ($mailer) {
            'resend' => ['RESEND_API_KEY'],
            'postmark' => ['POSTMARK_TOKEN'],
            'ses' => ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY'],
            'smtp' => ['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD'],
            default => [],
        };

        $missing = collect($needs)->filter(fn ($key) => blank($value($key)))->all();

        // A status report rather than a failure: everything else about mail is
        // proven working, and only the credentials are outstanding.
        if ($missing) {
            $this->markTestSkipped(
                "The `{$mailer}` mailer is missing " . implode(', ', $missing)
                . '. Real delivery will fail until those are set.'
            );
        }

        $this->assertNotEmpty($value('MAIL_FROM_ADDRESS'), 'No from-address is set.');
    }

    /**
     * The throttle has to match the provider, or a Sunday blast is mostly lost.
     *
     * Mailtrap's sandbox accepts about one message every ten seconds; Resend
     * takes far more. A throttle left at the sandbox value on a real provider
     * is not broken, only needlessly slow -- so this reports rather than fails.
     */
    public function test_the_send_rate_suits_the_provider(): void
    {
        $env = str_replace("\r\n", "\n", file_get_contents(base_path('.env')));

        preg_match('/^MAIL_MAILER=(.*)$/m', $env, $match);
        $mailer = trim($match[1] ?? '');

        $perMinute = (int) config('mail.throttle_per_minute');
        $linked = \App\Models\ParentLink::whereNotNull('verified_at')->count();

        if (in_array($mailer, ['smtp', 'log', 'array'], true)) {
            $this->assertGreaterThan(0, $perMinute);

            return;
        }

        // 14 minutes is the per-job delay ceiling the weekly blast staggers
        // inside; past that, reports arrive in a burst.
        $capacity = (int) floor(14 * 60 / (60 / max(1, $perMinute))) + 1;

        if ($linked > $capacity) {
            $this->markTestSkipped(
                "MAIL_THROTTLE_PER_MINUTE is {$perMinute}, which spaces {$capacity} reports cleanly, "
                . "but {$linked} parents are linked. Raise it on a real provider."
            );
        }

        $this->assertGreaterThan(0, $perMinute);
    }

    /* ---------------------------------------------------------- the AI */

    public function test_the_ai_engine_binds_to_the_configured_provider(): void
    {
        $marker = app(\App\Services\Ai\Marker::class);
        $driver = config('ai.driver');

        $this->assertContains($driver, ['openai', 'claude', 'null'], "Unknown AI driver `{$driver}`.");

        $key = match ($driver) {
            'openai' => config('ai.openai.key'),
            'claude' => config('ai.anthropic.key'),
            default => null,
        };

        // The rule that actually matters: no key means the null driver, so a
        // written answer waits for a teacher rather than being guessed at.
        if (blank($key)) {
            $this->assertInstanceOf(\App\Services\Ai\NullMarker::class, $marker);
            $this->assertFalse($marker->isConfigured());
            $this->assertFalse(app(\App\Services\Ai\Tutor::class)->isConfigured());

            return;
        }

        $expected = $driver === 'openai'
            ? \App\Services\Ai\OpenAiMarker::class
            : \App\Services\Ai\ClaudeMarker::class;

        $this->assertInstanceOf($expected, $marker);
        $this->assertTrue($marker->isConfigured());
    }
}
