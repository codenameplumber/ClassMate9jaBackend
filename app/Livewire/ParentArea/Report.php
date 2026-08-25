<?php

namespace App\Livewire\ParentArea;

use App\Mail\WeeklyReport;
use App\Support\ChildSummary;
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

        Mail::to(auth()->user())->send(new WeeklyReport($child, ChildSummary::for($child)));

        session()->flash('status', 'Report sent to ' . auth()->user()->email . '.');
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
