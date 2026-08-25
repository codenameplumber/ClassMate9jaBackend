<?php

namespace Database\Seeders;

use App\Models\ClassworkQuestion;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads the curriculum from database/content/*.json.
 *
 * Those files are generated from the front-end's js/data modules, which stay
 * the place content is authored. Nothing here is transcribed by hand, so the
 * PWA's offline copy and the database can never drift apart.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $dir = database_path('content');

        if (! is_dir($dir)) {
            $this->command->error("Missing {$dir} — run the content export first.");

            return;
        }

        $read = fn (string $file) => json_decode(file_get_contents("{$dir}/{$file}"), true) ?? [];

        DB::transaction(function () use ($read) {
            $this->subjects($read('subjects.json'));
            $this->topics($read('topics.json'));
            $this->lessons($read('lessons.json'));
            $this->classwork($read('classwork.json'));
            $this->questions($read('questions.json'));
        });
    }

    private function subjects(array $rows): void
    {
        foreach ($rows as $row) {
            Subject::updateOrCreate(['slug' => $row['slug']], $row);
        }
        $this->command->info('  subjects   ' . count($rows));
    }

    private function topics(array $rows): void
    {
        $subjects = Subject::pluck('id', 'slug');
        $now = now();
        $insert = [];

        foreach ($rows as $row) {
            if (! isset($subjects[$row['subject_slug']])) {
                continue;
            }
            $insert[] = [
                'subject_id' => $subjects[$row['subject_slug']],
                'ref' => $row['ref'],
                'week' => $row['week'],
                'week_title' => $row['week_title'],
                'title' => $row['title'],
                'minutes' => $row['minutes'],
                'position' => $row['position'],
                'has_rich_lesson' => $row['has_rich_lesson'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($insert, 200) as $chunk) {
            DB::table('topics')->insert($chunk);
        }
        $this->command->info('  topics     ' . count($insert));
    }

    private function lessons(array $rows): void
    {
        $topics = $this->topicIndex();
        $now = now();
        $insert = [];

        foreach ($rows as $row) {
            $key = $row['subject_slug'] . ':' . $row['topic_ref'];
            if (! isset($topics[$key])) {
                continue;
            }
            $insert[] = [
                'topic_id' => $topics[$key],
                'locale' => $row['locale'],
                'title' => $row['title'],
                'objectives' => json_encode($row['objectives']),
                'body' => json_encode($row['body']),
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($insert, 100) as $chunk) {
            DB::table('lessons')->insert($chunk);
        }
        $this->command->info('  lessons    ' . count($insert));
    }

    private function classwork(array $rows): void
    {
        $topics = $this->topicIndex();
        $now = now();
        $insert = [];

        foreach ($rows as $row) {
            $key = $row['subject_slug'] . ':' . $row['topic_ref'];
            if (! isset($topics[$key])) {
                continue;
            }
            $insert[] = [
                'topic_id' => $topics[$key],
                'prompt' => $row['prompt'],
                'options' => json_encode($row['options']),
                'answer_index' => $row['answer_index'],
                'explanation' => $row['explanation'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($insert, 200) as $chunk) {
            DB::table('classwork_questions')->insert($chunk);
        }
        $this->command->info('  classwork  ' . count($insert));
    }

    private function questions(array $rows): void
    {
        $subjects = Subject::pluck('id', 'slug');

        foreach ($rows as $row) {
            if (! isset($subjects[$row['subject_slug']])) {
                continue;
            }
            Question::updateOrCreate(['ref' => $row['ref']], [
                'subject_id' => $subjects[$row['subject_slug']],
                'exam_body' => $row['exam_body'],
                'year' => $row['year'],
                'topic' => $row['topic'],
                'prompt' => $row['prompt'],
                'options' => $row['options'],
                'answer_index' => $row['answer_index'],
                'explanation' => $row['explanation'],
            ]);
        }
        $this->command->info('  questions  ' . count($rows));
    }

    /** "maths:w3t1" => topic id */
    private function topicIndex(): array
    {
        static $cache = null;

        return $cache ??= Topic::join('subjects', 'subjects.id', '=', 'topics.subject_id')
            ->select('topics.id', 'topics.ref', 'subjects.slug')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->slug}:{$row->ref}" => $row->id])
            ->all();
    }
}
