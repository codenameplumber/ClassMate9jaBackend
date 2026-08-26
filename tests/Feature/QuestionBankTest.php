<?php

namespace Tests\Feature;

use App\Jobs\GenerateQuestions;
use App\Services\Ai\CourseWriter;
use App\Livewire\Admin\Courses;
use App\Models\Question;
use App\Models\Subject;
use App\Models\SubjectLevel;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stocking the practice bank in bulk.
 *
 * The command queues thousands of pounds' worth of nothing if it gets the
 * arithmetic wrong, so what is tested here is the counting: that it splits a
 * target across topics, skips what is already stocked, refuses offerings with
 * no scheme of work, and can be run twice without paying twice.
 */
class QuestionBankTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('email', 'admin@classmate9ja.ng')->firstOrFail();
    }

    /** A published offering with a known number of topics and no questions. */
    private function offering(int $topics = 4, string $level = 'SS1'): SubjectLevel
    {
        $subject = Subject::create([
            'slug' => 'bank-' . Str::lower(Str::random(8)),
            'name' => 'Bank Test Subject',
            'short_name' => 'BankTest',
            'emoji' => '📚',
            'tone' => 'brand',
            'is_core' => false,
            'status' => 'published',
        ]);

        for ($i = 1; $i <= $topics; $i++) {
            Topic::create([
                'subject_id' => $subject->id,
                'level' => $level,
                'ref' => 'w1t' . $i,
                'week' => 1,
                'week_title' => 'Week one',
                'title' => 'Topic number ' . $i,
                'minutes' => 25,
                'position' => $i,
            ]);
        }

        return SubjectLevel::create([
            'subject_id' => $subject->id,
            'level' => $level,
            'status' => 'published',
            'topic_count' => $topics,
        ]);
    }

    private function writer(): \App\Services\Ai\OpenAiCourseWriter
    {
        return new \App\Services\Ai\OpenAiCourseWriter(
            key: 'sk-test-not-a-real-key',
            baseUrl: 'https://api.openai.com/v1',
            model: 'gpt-4o-mini',
        );
    }

    /** A chat completion carrying `$count` distinct questions tagged `$tag`. */
    private function questionsReply(string $tag, int $count): array
    {
        $questions = [];

        for ($i = 0; $i < $count; $i++) {
            $questions[] = [
                'prompt' => "Question {$tag} number {$i}?",
                'options' => ['a', 'b', 'c', 'd'],
                'answer_index' => $i % 4,
                'explanation' => 'Because.',
            ];
        }

        return ['choices' => [['message' => ['content' => json_encode(['questions' => $questions])]]]];
    }

    /* ------------------------------------------------------------ the plan */

    public function test_the_target_is_split_across_the_offerings_topics(): void
    {
        Queue::fake();

        $offering = $this->offering(topics: 4);

        // 100 across 4 topics is 25 each.
        $this->artisan('questions:generate', [
            '--subject' => $offering->subject->slug,
            '--target' => 100,
            '--force' => true,
        ])->assertSuccessful();

        Queue::assertPushed(GenerateQuestions::class, 4);
        Queue::assertPushed(
            GenerateQuestions::class,
            fn ($job) => $job->perTopic === 25 && $job->level === 'SS1'
        );
    }

    public function test_a_dry_run_queues_nothing(): void
    {
        Queue::fake();

        $offering = $this->offering();

        $this->artisan('questions:generate', [
            '--subject' => $offering->subject->slug,
            '--dry-run' => true,
        ])->assertSuccessful();

        Queue::assertNothingPushed();
    }

    /**
     * An offering with no scheme of work has nothing to write questions about.
     * It is named rather than silently skipped.
     */
    public function test_an_offering_with_no_topics_is_reported_not_queued(): void
    {
        Queue::fake();

        $subject = Subject::create([
            'slug' => 'empty-' . Str::lower(Str::random(8)),
            'name' => 'Empty Subject', 'short_name' => 'Empty',
            'emoji' => '📭', 'tone' => 'brand', 'is_core' => false,
        ]);

        SubjectLevel::create([
            'subject_id' => $subject->id,
            'level' => 'JSS1',
            'status' => 'published',
        ]);

        $this->artisan('questions:generate', ['--subject' => $subject->slug, '--force' => true])
            ->expectsOutputToContain('no topics yet')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_a_topic_already_stocked_is_not_queued_again(): void
    {
        Queue::fake();

        $offering = $this->offering(topics: 2);
        $topics = Topic::where('subject_id', $offering->subject_id)->orderBy('position')->get();

        // Fill the first topic past the per-topic split (20 across 2 = 10 each).
        for ($i = 0; $i < 12; $i++) {
            Question::create([
                'ref' => 'seeded-' . Str::random(10),
                'subject_id' => $offering->subject_id,
                'level' => 'SS1',
                'exam_body' => 'PRACTICE',
                'year' => 2025,
                'topic' => $topics[0]->title,
                'prompt' => 'Existing question ' . $i,
                'options' => ['a', 'b', 'c', 'd'],
                'answer_index' => 0,
                'source' => 'ai',
            ]);
        }

        $this->artisan('questions:generate', [
            '--subject' => $offering->subject->slug,
            '--target' => 20,
            '--force' => true,
        ])->assertSuccessful();

        // Only the second topic is short.
        Queue::assertPushed(GenerateQuestions::class, 1);
        Queue::assertPushed(
            GenerateQuestions::class,
            fn ($job) => $job->topicId === $topics[1]->id
        );
    }

    public function test_a_fully_stocked_offering_queues_nothing(): void
    {
        Queue::fake();

        $offering = $this->offering(topics: 1);
        $topic = Topic::where('subject_id', $offering->subject_id)->firstOrFail();

        for ($i = 0; $i < 10; $i++) {
            Question::create([
                'ref' => 'seeded-' . Str::random(10),
                'subject_id' => $offering->subject_id,
                'level' => 'SS1',
                'exam_body' => 'PRACTICE',
                'year' => 2025,
                'topic' => $topic->title,
                'prompt' => 'Existing ' . $i,
                'options' => ['a', 'b', 'c', 'd'],
                'answer_index' => 1,
                'source' => 'ai',
            ]);
        }

        $this->artisan('questions:generate', [
            '--subject' => $offering->subject->slug,
            '--target' => 10,
            '--force' => true,
        ])->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_an_unknown_level_is_refused(): void
    {
        $this->artisan('questions:generate', ['--level' => 'SS9'])
            ->assertFailed();
    }

    public function test_generation_is_queued_on_the_content_queue(): void
    {
        Queue::fake();

        $offering = $this->offering(topics: 1);

        $this->artisan('questions:generate', [
            '--subject' => $offering->subject->slug,
            '--target' => 10,
            '--force' => true,
        ]);

        Queue::assertPushed(GenerateQuestions::class, fn ($job) => $job->queue === 'content');
    }

    /* ------------------------------------------------- filling to target */

    /**
     * One job fills its topic to the target, not to one batch of it.
     *
     * This was wrong first time round: the job made a single call capped at
     * twelve and stopped, so a target of twenty-one quietly produced twelve
     * and the command's estimate was double what actually landed.
     */
    public function test_a_job_keeps_asking_until_the_topic_hits_its_target(): void
    {
        $offering = $this->offering(topics: 1);
        $topic = Topic::where('subject_id', $offering->subject_id)->firstOrFail();

        // Two distinct batches, then a repeat of the second.
        Http::fakeSequence()
            ->push($this->questionsReply('batch-a', 12))
            ->push($this->questionsReply('batch-b', 12))
            ->push($this->questionsReply('batch-b', 12));

        $this->app->instance(CourseWriter::class, $this->writer());

        (new GenerateQuestions($offering->subject_id, 'SS1', $topic->id, 21))
            ->handle(app(CourseWriter::class));

        $written = Question::where('subject_id', $offering->subject_id)->count();

        // 12 from the first call, 9 asked for on the second.
        $this->assertGreaterThan(12, $written, 'The job stopped after one batch.');
        $this->assertLessThanOrEqual(21, $written);
    }

    /**
     * A model repeating itself must not loop forever.
     *
     * Every question collides with one already stored, so the count stops
     * moving. That is the signal to stop, not to keep paying for the same
     * twelve questions five more times.
     */
    public function test_a_repeating_model_stops_instead_of_spending(): void
    {
        $offering = $this->offering(topics: 1);
        $topic = Topic::where('subject_id', $offering->subject_id)->firstOrFail();

        // The same three questions, every single time.
        Http::fake(['*/chat/completions' => Http::response($this->questionsReply('same', 3))]);

        $this->app->instance(CourseWriter::class, $this->writer());

        (new GenerateQuestions($offering->subject_id, 'SS1', $topic->id, 500))
            ->handle(app(CourseWriter::class));

        $this->assertSame(3, Question::where('subject_id', $offering->subject_id)->count());

        // Two calls: one that made progress, one that proved there was none.
        Http::assertSentCount(2);
    }

    /** A writer that declines is not asked again in the same run. */
    public function test_a_declined_batch_ends_the_run_for_that_topic(): void
    {
        $offering = $this->offering(topics: 1);
        $topic = Topic::where('subject_id', $offering->subject_id)->firstOrFail();

        Http::fake(['*/chat/completions' => Http::response(['error' => 'nope'], 500)]);

        $this->app->instance(CourseWriter::class, $this->writer());

        (new GenerateQuestions($offering->subject_id, 'SS1', $topic->id, 500))
            ->handle(app(CourseWriter::class));

        $this->assertSame(0, Question::where('subject_id', $offering->subject_id)->count());

        Http::assertSentCount(1);
    }

    /** The runtime ceiling holds, so one job cannot outrun its worker timeout. */
    public function test_a_job_never_makes_more_calls_than_its_round_cap(): void
    {
        $offering = $this->offering(topics: 1);
        $topic = Topic::where('subject_id', $offering->subject_id)->firstOrFail();

        $sequence = Http::fakeSequence();
        for ($i = 0; $i < 20; $i++) {
            $sequence->push($this->questionsReply('round-' . $i, 12));
        }

        $this->app->instance(CourseWriter::class, $this->writer());

        (new GenerateQuestions($offering->subject_id, 'SS1', $topic->id, 500))
            ->handle(app(CourseWriter::class));

        Http::assertSentCount(GenerateQuestions::MAX_ROUNDS);
    }

    /* ----------------------------------------------------- the admin button */

    public function test_an_admin_can_stock_one_offering_from_the_screen(): void
    {
        Queue::fake();

        $offering = $this->offering(topics: 3);

        Livewire::actingAs($this->admin())->test(Courses::class)
            ->call('stockQuestions', $offering->subject_id, 'SS1');

        Queue::assertPushed(GenerateQuestions::class, 3);
    }

    /**
     * An offering with no scheme of work has nothing to write questions about.
     *
     * (The admin is also shown a warning saying so; that flash is set inside
     * the component and is not observable through Livewire's test session, so
     * what is asserted here is the part that costs money if it is wrong.)
     */
    public function test_stocking_an_offering_with_no_topics_queues_nothing(): void
    {
        Queue::fake();

        $subject = Subject::create([
            'slug' => 'bare-' . Str::lower(Str::random(8)),
            'name' => 'Bare Subject', 'short_name' => 'Bare',
            'emoji' => '📭', 'tone' => 'brand', 'is_core' => false,
        ]);

        SubjectLevel::create(['subject_id' => $subject->id, 'level' => 'JSS2', 'status' => 'draft']);

        Livewire::actingAs($this->admin())->test(Courses::class)
            ->call('stockQuestions', $subject->id, 'JSS2');

        Queue::assertNothingPushed();

        $this->assertSame(0, Question::where('subject_id', $subject->id)->count());
    }

    public function test_stocking_refuses_a_level_that_is_not_a_level(): void
    {
        Queue::fake();

        $offering = $this->offering(topics: 1);

        Livewire::actingAs($this->admin())->test(Courses::class)
            ->call('stockQuestions', $offering->subject_id, 'NOPE');

        Queue::assertNothingPushed();
    }

    /* ------------------------------------------------------------ the split */

    public function test_the_bulk_job_carries_its_per_topic_target_into_the_writer(): void
    {
        Queue::fake();

        $offering = $this->offering(topics: 24);

        // The real default: 500 across 24 topics is 21 each.
        $this->artisan('questions:generate', [
            '--subject' => $offering->subject->slug,
            '--force' => true,
        ])->assertSuccessful();

        Queue::assertPushed(
            GenerateQuestions::class,
            fn ($job) => $job->perTopic === (int) ceil(config('ai.questions_per_offering') / 24)
        );
    }
}
