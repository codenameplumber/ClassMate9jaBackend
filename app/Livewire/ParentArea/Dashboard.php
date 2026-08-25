<?php

namespace App\Livewire\ParentArea;

use App\Models\User;
use App\Support\ChildSummary;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public ?int $childId = null;

    public function mount(): void
    {
        $this->childId ??= auth()->user()->children()->value('users.id');
    }

    public function nudge(): void
    {
        session()->flash('status', 'Encouragement sent to your child\'s app.');
    }

    public function render()
    {
        $children = auth()->user()->children;
        $child = $children->firstWhere('id', $this->childId) ?? $children->first();

        return view('livewire.parent.dashboard', [
            'children' => $children,
            'summary' => $child ? ChildSummary::for($child) : null,
        ])->title($child ? $child->firstName() . "'s progress" : 'Dashboard');
    }
}
