<?php

namespace App\Livewire\Teacher;

use App\Models\Classroom;
use App\Support\ClassStats;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Reports extends Component
{
    #[Url(as: 'class')]
    public ?int $classroomId = null;

    public function render()
    {
        $classrooms = Classroom::where('teacher_id', auth()->id())->get();
        $classroom = $classrooms->firstWhere('id', $this->classroomId) ?? $classrooms->first();

        $roster = $classroom ? ClassStats::roster($classroom, '', 'average') : collect();
        $marked = $roster->filter(fn ($r) => $r['submissions'] > 0);

        // WAEC bands, because that is how a Nigerian head teacher reads a class.
        $bands = collect([
            ['key' => 'A1–B3', 'min' => 65, 'max' => 101],
            ['key' => 'C4–C6', 'min' => 50, 'max' => 65],
            ['key' => 'D7–E8', 'min' => 40, 'max' => 50],
            ['key' => 'F9', 'min' => 0, 'max' => 40],
        ])->map(function ($band) use ($marked) {
            $count = $marked->filter(fn ($r) => $r['average'] >= $band['min'] && $r['average'] < $band['max'])->count();

            return $band + [
                'count' => $count,
                'percent' => $marked->count() ? (int) round($count / $marked->count() * 100) : 0,
            ];
        });

        return view('livewire.teacher.reports', [
            'classrooms' => $classrooms,
            'classroom' => $classroom,
            'stats' => $classroom ? ClassStats::for($classroom) : null,
            'health' => $classroom ? ClassStats::topicHealth($classroom) : collect(),
            'bands' => $bands,
            'top' => $marked->sortByDesc('average')->take(5),
            'bottom' => $marked->sortBy('average')->take(5),
        ])->title('Reports');
    }
}
