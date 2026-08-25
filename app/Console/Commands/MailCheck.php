<?php

namespace App\Console\Commands;

use App\Mail\WeeklyReport;
use App\Models\ParentLink;
use App\Models\User;
use App\Support\ChildSummary;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Sends one of every email the platform produces, through the real transport.
 *
 * A rendered mailable proves the Blade compiles. This proves SMTP credentials,
 * the connection, and the from-address are all actually working — which is a
 * different question, and the one that bites on launch day.
 */
class MailCheck extends Command
{
    protected $signature = 'mail:check {--to= : override the recipient}';

    protected $description = 'Send every transactional email through the configured mailer';

    public function handle(): int
    {
        $this->line('');
        $this->line('  transport : ' . config('mail.default'));
        $this->line('  host      : ' . config('mail.mailers.smtp.host') . ':' . config('mail.mailers.smtp.port'));
        $this->line('  from      : ' . config('mail.from.address'));
        $this->line('');

        if (config('mail.default') === 'log') {
            $this->error('  MAIL_MAILER is `log` — nothing will leave this machine.');

            return self::FAILURE;
        }

        if (blank(config('mail.mailers.smtp.username'))) {
            $this->error('  MAIL_USERNAME is empty — SMTP will be rejected.');

            return self::FAILURE;
        }

        $sent = 0;
        $failed = 0;

        $spacing = max(1, (int) round(60 / max(1, (int) config('mail.throttle_per_minute', 30))));
        $first = true;

        $attempt = function (string $label, callable $send) use (&$sent, &$failed, $spacing, &$first) {
            // Sandbox plans reject a burst outright, so pace the diagnostic the
            // same way the real bulk send paces itself.
            if (! $first) {
                sleep($spacing);
            }
            $first = false;

            $started = microtime(true);

            try {
                $send();
                $ms = (int) round((microtime(true) - $started) * 1000);
                $this->info(sprintf('  ✓ %-26s sent in %dms', $label, $ms));
                $sent++;
            } catch (\Throwable $e) {
                $this->error(sprintf('  ✗ %-26s %s', $label, $e->getMessage()));
                $failed++;
            }
        };

        /* --- 1. The plainest possible send, to isolate transport problems --- */
        $to = $this->option('to') ?: 'inbox@classmate9ja.test';

        $attempt('smoke test', fn () => Mail::raw(
            "If this arrived, SMTP is working.\n\nSent by php artisan mail:check at " . now()->toDateTimeString(),
            fn ($message) => $message->to($to)->subject('ClassMate 9ja — SMTP smoke test')
        ));

        /* --- 2. Password reset, the one users hit most --------------------- */
        $student = User::where('role', 'student')->whereNotNull('email')->first();

        if ($student) {
            $attempt('password reset link', function () use ($student) {
                $token = Password::createToken($student);
                $student->notify(new ResetPassword($token));
            });
        }

        /* --- 3. Email verification ----------------------------------------- */
        if ($student) {
            $attempt('email verification', fn () => $student->sendEmailVerificationNotification());
        }

        /* --- 4. The weekly parent report, the heaviest template ------------- */
        $link = ParentLink::with('parent', 'student')->whereNotNull('verified_at')->first();

        if ($link && $link->parent && $link->student) {
            $attempt('weekly parent report', fn () => Mail::to($this->option('to') ?: $link->parent)
                ->send(new WeeklyReport($link->student, ChildSummary::for($link->student))));
        } else {
            $this->warn('  – weekly parent report        skipped: no verified parent link');
        }

        /* --- 5. A report for a student with no data at all ------------------ */
        $blank = User::where('role', 'student')
            ->whereDoesntHave('studySessions')
            ->whereDoesntHave('attempts')
            ->first();

        if ($blank) {
            $attempt('report, student with no data', fn () => Mail::to($to)
                ->send(new WeeklyReport($blank, ChildSummary::for($blank))));
        } else {
            $this->warn('  – empty-data report           skipped: every student has history');
        }

        $this->line('');
        $this->line(sprintf('  %d sent, %d failed', $sent, $failed));
        $this->line('');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
