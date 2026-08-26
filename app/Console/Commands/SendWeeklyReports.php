<?php

namespace App\Console\Commands;

use App\Mail\WeeklyReport;
use App\Models\ParentLink;
use App\Support\ChildSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWeeklyReports extends Command
{
    protected $signature = 'reports:weekly {--day= : only links set to this day}';

    protected $description = 'Email every linked parent their weekly report';

    public function handle(): int
    {
        $day = $this->option('day') ?? strtolower(now()->format('l'));

        $links = ParentLink::with('parent', 'student')
            ->whereNotNull('verified_at')
            ->where('report_day', $day)
            ->get();

        if ($links->isEmpty()) {
            $this->info("No reports scheduled for {$day}.");

            return self::SUCCESS;
        }

        // Every provider rate-limits, and a school with 500 linked parents would
        // trip that on the first Sunday. Stagger the queue so the worker releases
        // them at a pace any mailer accepts, instead of firing hundreds at once
        // and losing most of them to a 550.
        $perMinute = max(1, (int) config('mail.throttle_per_minute', 30));
        $spacing = 60 / $perMinute;
        $queued = 0;

        // Managed queues on Laravel Cloud (and SQS generally) refuse a per-job
        // delay beyond fifteen minutes, so the spread has to fit inside that
        // window. It only binds at very low throttles: at 5/minute the window
        // holds 70 reports, at 100/minute it holds 1,400. Anything past the
        // ceiling is released at the ceiling rather than silently dropped, and
        // the command says so.
        $ceiling = 14 * 60;
        $capacity = (int) floor($ceiling / $spacing) + 1;

        foreach ($links as $link) {
            if (! $link->parent?->email || ! $link->student) {
                continue;
            }

            Mail::to($link->parent)->later(
                now()->addSeconds((int) min($ceiling, round($queued * $spacing))),
                (new WeeklyReport($link->student, ChildSummary::for($link->student)))
                    ->onQueue('mail')
            );

            $queued++;

            if ($this->output->isVerbose()) {
                $this->line("  queued for {$link->parent->email} about {$link->student->name}");
            }
        }

        $minutes = (int) ceil($queued / $perMinute);

        $this->info("{$queued} reports queued, released at {$perMinute}/minute — "
            . "about {$minutes} " . str('minute')->plural($minutes) . ' to clear.');

        if ($queued > $capacity) {
            $over = $queued - $capacity;

            $this->warn("{$over} of them land on the 14-minute delay ceiling and will "
                . 'arrive in a burst. Raise MAIL_THROTTLE_PER_MINUTE to spread them '
                . "properly — {$perMinute}/minute only spaces {$capacity} cleanly.");
        }

        return self::SUCCESS;
    }
}
