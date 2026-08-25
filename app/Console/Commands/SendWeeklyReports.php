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

        foreach ($links as $link) {
            if (! $link->parent?->email || ! $link->student) {
                continue;
            }

            Mail::to($link->parent)->queue(
                new WeeklyReport($link->student, ChildSummary::for($link->student))
            );

            $this->line("  queued for {$link->parent->email} about {$link->student->name}");
        }

        $this->info($links->count() . ' reports queued.');

        return self::SUCCESS;
    }
}
