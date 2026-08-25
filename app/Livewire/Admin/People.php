<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class People extends Component
{
    use WithPagination;

    public string $search = '';
    public string $role = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedRole(): void { $this->resetPage(); }

    public function render()
    {
        $people = User::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->role !== '', fn ($q) => $q->where('role', $this->role))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.admin.people', ['people' => $people])->title('People');
    }
}
