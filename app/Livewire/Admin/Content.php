<?php

namespace App\Livewire\Admin;

use App\Models\Subject;
use App\Models\Topic;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Content extends Component
{
    public ?string $subjectSlug = null;
    public ?int $topicId = null;
    public string $locale = 'en';

    public function render()
    {
        $subjects = Subject::withCount('topics')->orderByDesc('is_core')->orderBy('name')->get();
        $subject = $subjects->firstWhere('slug', $this->subjectSlug) ?? $subjects->first();

        $topics = $subject
            ? Topic::with('lessons')->where('subject_id', $subject->id)->orderBy('week')->orderBy('position')->get()
            : collect();

        $topic = $topics->firstWhere('id', $this->topicId) ?? $topics->first();

        return view('livewire.admin.content', [
            'subjects' => $subjects,
            'subject' => $subject,
            'topics' => $topics,
            'topic' => $topic,
            'lesson' => $topic?->lessonFor($this->locale),
        ])->title('Curriculum');
    }
}
