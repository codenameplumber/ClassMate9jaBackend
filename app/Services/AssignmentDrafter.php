<?php

namespace App\Services;

use App\Models\Topic;
use App\Services\Ai\Marker;

/**
 * Turns a topic into a ready-to-review paper.
 *
 * With a key configured it asks the model. Without one it assembles a real
 * paper from the seeded classwork and past-question bank — so "draft an
 * assignment" works on a fresh install with no credentials at all.
 */
class AssignmentDrafter
{
    public function __construct(private readonly Marker $marker) {}

    public function draft(Topic $topic, int $count, string $kind): array
    {
        $subject = $topic->subject;
        $level = $topic->subject->topics()->first()?->week_title ? 'SS2' : 'SS2';

        $generated = $this->marker->draftQuestions(
            $subject->name, $topic->title, $level, $count
        );

        if ($generated) {
            return [
                'title' => "Week {$topic->week} " . ucfirst($kind) . " · {$topic->title}",
                'generated' => true,
                'note' => 'Drafted from the ' . $topic->week_title . ' scheme, with a marking scheme on every question.',
                'questions' => $this->normalise($generated, $count),
            ];
        }

        return [
            'title' => "Week {$topic->week} " . ucfirst($kind) . " · {$topic->title}",
            'generated' => false,
            'note' => 'Assembled from the seeded question bank for this topic. Add an AI key to have questions written fresh.',
            'questions' => $this->fromBank($topic, $count),
        ];
    }

    /** A real paper built from the content already on the device. */
    private function fromBank(Topic $topic, int $count): array
    {
        $questions = [];

        foreach ($topic->classwork as $item) {
            if (count($questions) >= $count - 1) {
                break;
            }
            $questions[] = [
                'kind' => 'objective',
                'prompt' => $item->prompt,
                'options' => $item->options,
                'answer_index' => $item->answer_index,
                'expected' => $item->options[$item->answer_index] ?? '',
                'max_mark' => 2,
            ];
        }

        $pool = $topic->subject->questions()->inRandomOrder()->limit($count)->get();
        foreach ($pool as $item) {
            if (count($questions) >= $count - 1) {
                break;
            }
            $questions[] = [
                'kind' => 'objective',
                'prompt' => $item->prompt,
                'options' => $item->options,
                'answer_index' => $item->answer_index,
                'expected' => $item->options[$item->answer_index] ?? '',
                'max_mark' => 2,
            ];
        }

        // Always close with a written answer: it is the part that shows whether
        // the student understood, and the part marking has to prove itself on.
        $questions[] = [
            'kind' => 'written',
            'prompt' => "Explain {$topic->title} in your own words, and give one worked example.",
            'options' => null,
            'answer_index' => null,
            'expected' => "A correct definition in the student's own words (2 marks), "
                . 'plus a worked example with the steps shown (3 marks).',
            'max_mark' => 5,
        ];

        return $questions;
    }

    private function normalise(array $questions, int $count): array
    {
        return collect($questions)->take($count)->map(fn ($q) => [
            'kind' => ($q['kind'] ?? 'objective') === 'written' ? 'written' : 'objective',
            'prompt' => (string) ($q['prompt'] ?? ''),
            'options' => is_array($q['options'] ?? null) ? array_values($q['options']) : null,
            'answer_index' => isset($q['answer_index']) ? (int) $q['answer_index'] : null,
            'expected' => (string) ($q['expected'] ?? ''),
            'max_mark' => max(1, (int) ($q['max_mark'] ?? 1)),
        ])->filter(fn ($q) => $q['prompt'] !== '')->values()->all();
    }
}
