<?php

namespace App\Livewire\Admin;

use App\Models\Assignment;
use App\Models\CourseGeneration;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Subject;
use App\Models\SubjectLevel;
use App\Models\Submission;
use App\Models\Topic;
use App\Models\User;
use App\Services\Ai\CourseWriter;
use App\Services\Ai\Marker;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render(Marker $marker, CourseWriter $writer)
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

            /*
            | The two things an admin is actually meant to act on.
            |
            | A teacher waiting on a decision cannot mark, and a level with no
            | content is a subject students can enrol in and find empty. Both
            | are invisible unless something puts a number on them.
            */
            'pendingTeachers' => User::pendingTeachers()->count(),
            'emptyLevels' => SubjectLevel::where('status', 'draft')->count(),
            'writingNow' => SubjectLevel::where('status', 'generating')->count(),

            // Questions a model wrote that nobody has checked. An unreviewed
            // answer key marks a correct student wrong and nothing looks broken.
            'unreviewed' => Question::where('source', 'ai')->whereNull('reviewed_at')->count(),

            'recentRuns' => CourseGeneration::with('subject')->latest()->limit(5)->get(),
            'aiReady' => $marker->isConfigured(),
            'writerReady' => $writer->isConfigured(),
            'recent' => User::latest()->limit(8)->get(),
        ])->title('Overview');
    }
}
