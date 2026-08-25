<?php

namespace App\Livewire\Admin;

use App\Models\Assignment;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Submission;
use App\Models\Topic;
use App\Models\User;
use App\Services\Ai\Marker;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render(Marker $marker)
    {
        return view('livewire.admin.dashboard', [
            'counts' => [
                'subjects' => Subject::count(),
                'topics' => Topic::count(),
                'lessons' => Lesson::count(),
                'questions' => Question::count(),
                'users' => User::count(),
                'students' => User::where('role', 'student')->count(),
                'assignments' => Assignment::count(),
                'submissions' => Submission::count(),
                'pending' => Submission::where('status', '!=', 'approved')->count(),
            ],
            'aiReady' => $marker->isConfigured(),
            'recent' => User::latest()->limit(8)->get(),
        ])->title('Overview');
    }
}
