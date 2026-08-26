<?php

namespace App\Livewire\ParentArea;

use App\Mail\WeeklyReport;
use App\Support\ChildSummary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Report extends Component
{
    public ?int $childId = null;

    public function mount(): void
    {
        $this->childId ??= auth()->user()->children()->value('users.id');
    }

    public function emailIt(): void
    {
        $child = auth()->user()->children()->find($this->childId);

        if (! $child) {
            return;
        }

        $mailable = (new WeeklyReport($child, ChildSummary::for($child)))->onQueue('mail');

        // Queued, not sent inline: a parent on a slow phone should not hold the
        // request open for an SMTP handshake, and a mailer having a bad morning
        // should not turn a button into a 500.
        try {
            Mail::to(auth()->user())->queue($mailable);
        } catch (\Throwable $e) {
            Log::warning('Parent report could not be queued', ['message' => $e->getMessage()]);
            session()->flash('status', 'Could not send that just now. Please try again shortly.');

            return;
        }

        session()->flash('status', 'Report on its way to ' . auth()->user()->email . '.');
    }

    public function render()
    {
        $children = auth()->user()->children;
        $child = $children->firstWhere('id', $this->childId) ?? $children->first();

        return view('livewire.parent.report', [
            'summary' => $child ? ChildSummary::for($child) : null,
        ])->title('Weekly report');
    }
}
