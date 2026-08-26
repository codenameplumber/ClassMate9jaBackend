<?php

namespace App\Console\Commands;

use App\Jobs\GenerateQuestions;
use App\Models\Question;
use App\Models\Subject;
use App\Models\SubjectLevel;
use App\Models\Topic;
use App\Support\Levels;
use Illuminate\Console\Command;

/**
 * Fills the practice bank in bulk.
 *
 * The on-demand path stocks a subject to ten questions a topic when a student
 * opens it thin. This is the other end: stocking every published offering to a
 * full bank before anyone asks, so a school that goes live on Monday finds
 * practice already there.
 *
 * Two things it refuses to do quietly:
 *
 *  1. Run without telling you the size of it first. Ten thousand questions is
 *     real money and several hours of worker time, and the number nobody
 *     checked is how that becomes a surprise.
 *
 *  2. Touch an offering with no topics. A subject with no scheme of work has
 *     nothing to write questions *about* — those need a course generated
 *     first, and the command says which ones.
 *
 * Everything it queues is idempotent: questions are keyed by a hash of their
 * prompt, and each job re-counts before it writes. Running it twice costs the
 * second run nothing but a few counting queries.
 */
class GenerateQuestionBank extends Command
{
    protected $signature = 'questions:generate
        {--subject= : one subject slug, instead of all of them}
        {--level= : one level, instead of every published one}
        {--target= : questions per subject-level (default ai.questions_per_offering)}
        {--per-topic= : override the per-topic split directly}
        {--dry-run : show what would be queued and stop}
        {--force : skip the confirmation}';

    protected $description = 'Stock the practice bank for every published subject and level';

    public function handle(): int
    {
        $target = (int) ($this->option('target') ?: config('ai.questions_per_offering', 500));

        if ($target < 1) {
            $this->error('  --target must be at least 1.');

            return self::FAILURE;
        }

        if (($level = $this->option('level')) && ! Levels::isValid($level)) {
            $this->error("  `{$level}` is not a level. One of: " . implode(', ', Levels::ALL));

            return self::FAILURE;
        }

        $offerings = $this->offerings();

        if ($offerings->isEmpty()) {
            $this->warn('  Nothing matches that.');

            return self::SUCCESS;
        }

        [$plan, $skipped] = $this->plan($offerings, $target);

        $this->report($plan, $skipped, $target);

        if (! $plan) {
            $this->info('  Every offering is already stocked. Nothing to do.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->line('');
            $this->info('  Dry run — nothing was queued.');

            return self::SUCCESS;
        }

        $jobs = collect($plan)->sum(fn ($row) => count($row['topics']));

        if (! $this->option('force') && ! $this->confirm("  Queue {$jobs} jobs?", false)) {
            $this->line('  Nothing queued.');

            return self::SUCCESS;
        }

        $this->dispatchAll($plan);

        $this->line('');
        $this->info("  {$jobs} jobs queued on the `content` queue.");
        $this->line('  Watch them with: php artisan queue:work --queue=content');
        $this->line('');

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------------ */

    /** Published offerings, optionally narrowed by the options. */
    private function offerings()
    {
        return SubjectLevel::query()
            ->with('subject')
            ->where('status', 'published')
            ->when($this->option('level'), fn ($q, $v) => $q->where('level', $v))
            ->when($this->option('subject'), fn ($q, $v) => $q->whereHas(
                'subject', fn ($s) => $s->where('slug', $v)
            ))
            ->get()
            ->filter(fn ($o) => $o->subject)
            ->sortBy([
                fn ($a, $b) => $a->subject->name <=> $b->subject->name,
                fn ($a, $b) => array_search($a->level, Levels::ALL) <=> array_search($b->level, Levels::ALL),
            ])
            ->values();
    }

    /**
     * Work out what each offering is short of.
     *
     * @return array{0: array<int, array>, 1: array<int, string>}
     */
    private function plan($offerings, int $target): array
    {
        $plan = [];
        $skipped = [];

        foreach ($offerings as $offering) {
            $topics = Topic::where('subject_id', $offering->subject_id)
                ->where('level', $offering->level)
                ->orderBy('week')->orderBy('position')
                ->get(['id', 'title']);

            $label = $offering->subject->short_name . ' ' . $offering->level;

            if ($topics->isEmpty()) {
                $skipped[] = $label . ' — no topics yet, generate the course first';

                continue;
            }

            $have = Question::where('subject_id', $offering->subject_id)
                ->where('level', $offering->level)
                ->count();

            if ($have >= $target) {
                continue;
            }

            // Split the offering's target across its topics, so a 24-topic
            // course asks for roughly 21 each rather than 500 from one.
            $perTopic = (int) ($this->option('per-topic') ?: (int) ceil($target / $topics->count()));

            // Only queue the topics that are actually short.
            $counts = Question::where('subject_id', $offering->subject_id)
                ->where('level', $offering->level)
                ->selectRaw('topic, count(*) as total')
                ->groupBy('topic')
                ->pluck('total', 'topic');

            $short = $topics->filter(fn ($t) => (int) ($counts[$t->title] ?? 0) < $perTopic);

            if ($short->isEmpty()) {
                continue;
            }

            $plan[] = [
                'offering' => $offering,
                'label' => $label,
                'have' => $have,
                'perTopic' => $perTopic,
                'topics' => $short->pluck('id')->all(),
                'wanted' => $perTopic * $short->count(),
            ];
        }

        return [$plan, $skipped];
    }

    private function report(array $plan, array $skipped, int $target): void
    {
        $this->line('');
        $this->line('  target     : ' . $target . ' questions per subject-level');
        $this->line('  model      : ' . (config('ai.writer_model') ?: config('ai.model')));
        $this->line('');

        if ($plan) {
            $this->table(
                ['Offering', 'Has', 'Per topic', 'Topics', 'Will write'],
                array_map(fn ($row) => [
                    $row['label'],
                    $row['have'],
                    $row['perTopic'],
                    count($row['topics']),
                    '~' . $row['wanted'],
                ], $plan),
            );
        }

        foreach ($skipped as $line) {
            $this->warn('  - ' . $line);
        }

        if (! $plan) {
            return;
        }

        $jobs = collect($plan)->sum(fn ($row) => count($row['topics']));
        $questions = collect($plan)->sum('wanted');

        // What one job actually does: up to MAX_ROUNDS calls of BATCH each,
        // per topic. This is the number to plan spend and worker time against,
        // so it is derived from the job's own constants rather than guessed.
        $roundsPer = fn (int $perTopic) => min(
            GenerateQuestions::MAX_ROUNDS,
            (int) ceil($perTopic / GenerateQuestions::BATCH)
        );

        $calls = collect($plan)->sum(
            fn ($row) => count($row['topics']) * $roundsPer($row['perTopic'])
        );

        // A topic wanting more than one run of the job can hold is topped up
        // by running this again, so say how much lands on this pass.
        $reach = collect($plan)->sum(
            fn ($row) => count($row['topics'])
                * min($row['perTopic'], $roundsPer($row['perTopic']) * GenerateQuestions::BATCH)
        );

        $this->line('');
        $this->line(sprintf('  %d jobs, roughly %s model calls.',
            $jobs, number_format($calls)));

        $this->line(sprintf('  Up to %s questions this pass, out of %s wanted.',
            number_format($reach), number_format($questions)));

        // Roughly twenty seconds a call, one worker, no parallelism.
        $hours = $calls * 20 / 3600;

        $this->line(sprintf('  About %s on one worker; divide by however many you run.',
            $hours < 1
                ? max(1, (int) round($hours * 60)) . ' minutes'
                : round($hours, 1) . ' hours'));

        if ($reach < $questions) {
            $this->line('  Run it again afterwards to top the rest up — it is idempotent.');
        }

        $this->warn('  Everything written is flagged `source: ai` with no review. '
            . 'An answer key nobody has checked marks a correct student wrong.');
    }

    private function dispatchAll(array $plan): void
    {
        $bar = $this->output->createProgressBar(collect($plan)->sum(fn ($r) => count($r['topics'])));
        $bar->start();

        foreach ($plan as $row) {
            $offering = $row['offering'];

            foreach ($row['topics'] as $topicId) {
                GenerateQuestions::dispatch(
                    $offering->subject_id,
                    $offering->level,
                    $topicId,
                    $row['perTopic'],
                );

                $bar->advance();
            }
        }

        $bar->finish();
        $this->line('');
    }
}
