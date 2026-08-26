<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Subject;
use App\Models\SubjectLevel;
use App\Models\Topic;
use App\Support\Levels;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads the curriculum from database/content/*.json.
 *
 * Those files are generated from the front-end's js/data modules, which stay
 * the place content is authored. Nothing here is transcribed by hand, so the
 * PWA's offline copy and the database can never drift apart.
 *
 * Content is scoped by level. A subject is declared to be taught at a set of
 * levels (`subject_levels`), and each level is either filled with topics or
 * sitting in `draft` waiting for the generator. That is why Further Maths has
 * no JSS1 and Basic Science has no SS3: the offering is a decision, made here
 * or by an admin, not something inferred from whatever content happens to
 * exist.
 */
class ContentSeeder extends Seeder
{
    /**
     * Which levels each shipped subject is taught at.
     *
     * Follows the Nigerian curriculum: Mathematics, English and Civic
     * Education run the whole way through; the sciences, commercials and
     * Further Maths are senior only.
     */
    private const OFFERED_AT = [
        'maths' => Levels::ALL,
        'english' => Levels::ALL,
        'civic' => Levels::ALL,
        'physics' => Levels::SENIOR,
        'chemistry' => Levels::SENIOR,
        'biology' => Levels::SENIOR,
        'economics' => Levels::SENIOR,
        'government' => Levels::SENIOR,
        'literature' => Levels::SENIOR,
        'agric' => Levels::SENIOR,
        'furthermaths' => Levels::SENIOR,
        'geography' => Levels::SENIOR,
    ];

    /**
     * The levels the shipped scheme is actually written out at.
     *
     * The exported JSON is one scheme of work with no level on it, and its
     * content is senior material -- logarithms, the mole concept, electric
     * fields. So it is written out across all three senior years, which is
     * what the school asked for: SS1, SS2 and SS3 share a scheme.
     *
     * The junior years are deliberately left empty. Seeding SS-level topics
     * into JSS1 would be worse than an empty course, and those offerings are
     * exactly the queue the course generator exists to work through.
     */
    private const SEEDED_LEVELS = ['SS1', 'SS2', 'SS3'];

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
            $this->offerings();
            $this->topics($read('topics.json'));
            $this->lessons($read('lessons.json'));
            $this->classwork($read('classwork.json'));
            $this->questions($read('questions.json'));
            $this->recount();
        });
    }

    private function subjects(array $rows): void
    {
        foreach ($rows as $row) {
            Subject::updateOrCreate(['slug' => $row['slug']], $row + ['status' => 'published']);
        }

        $this->command->info('  subjects   ' . count($rows));
    }

    /** Declare which levels each subject is taught at, before any content exists. */
    private function offerings(): void
    {
        $subjects = Subject::pluck('id', 'slug');
        $made = 0;

        foreach (self::OFFERED_AT as $slug => $levels) {
            if (! isset($subjects[$slug])) {
                continue;
            }

            foreach ($levels as $level) {
                SubjectLevel::updateOrCreate(
                    ['subject_id' => $subjects[$slug], 'level' => $level],
                    ['status' => 'draft']
                );
                $made++;
            }
        }

        $this->command->info('  offerings  ' . $made);
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

            foreach ($this->levelsFor($row['subject_slug']) as $level) {
                $insert[] = [
                    'subject_id' => $subjects[$row['subject_slug']],
                    'level' => $level,
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
            foreach ($this->levelsFor($row['subject_slug']) as $level) {
                $key = $row['subject_slug'] . ':' . $level . ':' . $row['topic_ref'];

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
            foreach ($this->levelsFor($row['subject_slug']) as $level) {
                $key = $row['subject_slug'] . ':' . $level . ':' . $row['topic_ref'];

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
        }

        foreach (array_chunk($insert, 200) as $chunk) {
            DB::table('classwork_questions')->insert($chunk);
        }

        $this->command->info('  classwork  ' . count($insert));
    }

    private function questions(array $rows): void
    {
        $subjects = Subject::pluck('id', 'slug');
        $written = 0;

        foreach ($rows as $row) {
            if (! isset($subjects[$row['subject_slug']])) {
                continue;
            }

            foreach ($this->levelsFor($row['subject_slug']) as $level) {
                // The ref carries the level because the same shipped question
                // is written out once per level it is offered at.
                Question::updateOrCreate(
                    ['ref' => $row['ref'] . '-' . strtolower($level)],
                    [
                        'subject_id' => $subjects[$row['subject_slug']],
                        'level' => $level,
                        'exam_body' => $row['exam_body'],
                        'year' => $row['year'],
                        'topic' => $row['topic'],
                        'prompt' => $row['prompt'],
                        'options' => $row['options'],
                        'answer_index' => $row['answer_index'],
                        'explanation' => $row['explanation'],
                        'source' => 'seeded',
                    ]
                );
                $written++;
            }
        }

        $this->command->info('  questions  ' . $written);
    }

    /** Publish every offering that actually received content. */
    private function recount(): void
    {
        SubjectLevel::with('subject')->get()->each->refreshCounts();

        $ready = SubjectLevel::where('status', 'published')->count();
        $waiting = SubjectLevel::where('status', 'draft')->count();

        $this->command->info("  published  {$ready} offerings, {$waiting} awaiting generation");
    }

    /** The levels this subject is both offered at and shipped content for. */
    private function levelsFor(string $slug): array
    {
        return array_values(array_intersect(
            self::OFFERED_AT[$slug] ?? [],
            self::SEEDED_LEVELS
        ));
    }

    /** "maths:SS2:w3t1" => topic id */
    private function topicIndex(): array
    {
        static $cache = null;

        return $cache ??= Topic::join('subjects', 'subjects.id', '=', 'topics.subject_id')
            ->select('topics.id', 'topics.ref', 'topics.level', 'subjects.slug')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->slug}:{$row->level}:{$row->ref}" => $row->id])
            ->all();
    }
}
