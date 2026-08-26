<?php

namespace App\Livewire\Admin;

use App\Models\School;
use App\Models\User;
use App\Support\Levels;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every student on the platform.
 *
 * Paginated rather than loaded whole, and the counts come from the query
 * rather than from the collection: at 270 students a page that sums averages
 * in PHP is a page that gets slower every time a school signs up.
 */
#[Layout('layouts.app')]
class Students extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $level = '';

    #[Url(as: 'school')]
    public ?int $schoolId = null;

    public function updatedSearch(): void { $this->resetPage(); }

    public function updatedLevel(): void { $this->resetPage(); }

    public function updatedSchoolId(): void { $this->resetPage(); }

    public function clear(): void
    {
        $this->reset(['search', 'level', 'schoolId']);
        $this->resetPage();
    }

    public function render()
    {
        $students = User::query()
            ->where('role', 'student')
            ->with(['school', 'enrolledClassrooms'])
            ->withCount([
                'subjects',
                'submissions',
                'submissions as approved_count' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->level !== '', fn ($q) => $q->where('level', $this->level))
            ->when($this->schoolId, fn ($q) => $q->where('school_id', $this->schoolId))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.admin.students', [
            'students' => $students,
            'levels' => Levels::ALL,
            'schools' => School::orderBy('name')->get(),
            'total' => User::where('role', 'student')->count(),
        ])->title('Students');
    }
}
