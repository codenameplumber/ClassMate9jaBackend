<?php

namespace App\Livewire\Teacher;

use App\Models\Classroom;
use App\Models\Submission;
use App\Support\ClassStats;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        $teacher = auth()->user();
        $classrooms = Classroom::where('teacher_id', $teacher->id)
            ->withCount('students')
            ->get();

        $queues = Submission::with('assignment.classroom')
            ->whereHas('assignment.classroom', fn ($q) => $q->where('teacher_id', $teacher->id))
            ->where('status', '!=', 'approved')
            ->get()
            ->groupBy('assignment_id')
            ->map(fn ($group) => [
                'assignment' => $group->first()->assignment,
                'pending' => $group->count(),
                'total' => Submission::where('assignment_id', $group->first()->assignment_id)->count(),
            ])
            ->values();

        $primary = $classrooms->first();

        return view('livewire.teacher.dashboard', [
            'classrooms' => $classrooms,
            'queues' => $queues,
            'totalPending' => $queues->sum('pending'),
            'stats' => $primary ? ClassStats::for($primary) : null,
            'health' => $primary ? ClassStats::topicHealth($primary) : collect(),
            'atRisk' => $primary ? ClassStats::atRisk($primary) : collect(),
        ])->title('Dashboard');
    }
}
