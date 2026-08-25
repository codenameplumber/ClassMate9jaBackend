<?php

namespace App\Livewire\Teacher;

use App\Models\Classroom;
use App\Support\ClassStats;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Classes extends Component
{
    #[Url(as: 'class')]
    public ?int $classroomId = null;

    #[Url]
    public string $search = '';

    public string $sort = 'name';

    public function updatedSearch(): void { /* re-render only */ }

    public function selectClass(int $id): void
    {
        $this->classroomId = $id;
        $this->search = '';
    }

    public function render()
    {
        $teacher = auth()->user();
        $classrooms = Classroom::where('teacher_id', $teacher->id)->withCount('students')->get();
        $classroom = $classrooms->firstWhere('id', $this->classroomId) ?? $classrooms->first();

        $roster = collect();
        if ($classroom) {
            $roster = ClassStats::roster($classroom, $this->search, $this->sort);
        }

        return view('livewire.teacher.classes', [
            'classrooms' => $classrooms,
            'classroom' => $classroom,
            'roster' => $roster,
            'stats' => $classroom ? ClassStats::for($classroom) : null,
            'health' => $classroom ? ClassStats::topicHealth($classroom) : collect(),
        ])->title($classroom?->name ?? 'My classes');
    }
}
