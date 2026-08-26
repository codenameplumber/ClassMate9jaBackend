<?php

namespace App\Livewire\Teacher;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Where a teacher waits for an administrator.
 *
 * A component rather than a plain view purely so it reaches the guest layout
 * the same way every other full-page screen here does — Livewire resolves
 * #[Layout] against resources/views, which is where the two layouts live.
 */
#[Layout('layouts.guest')]
#[Title('Waiting for approval')]
class Pending extends Component
{
    public function mount()
    {
        $user = auth()->user();

        // An approved teacher has no business on a waiting page.
        if ($user->isTeacher() && $user->isApproved()) {
            return $this->redirect(route('teacher.dashboard'), navigate: false);
        }
    }

    public function render()
    {
        return view('livewire.teacher.pending');
    }
}
