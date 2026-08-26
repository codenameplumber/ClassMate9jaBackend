<?php

namespace Tests\Feature;

use App\Jobs\GenerateCourse;
use App\Jobs\GenerateQuestions;
use App\Jobs\GenerateTopicContent;
use App\Livewire\Admin\Courses;
use App\Models\ClassworkQuestion;
use App\Models\CourseGeneration;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Subject;
use App\Models\SubjectLevel;
use App\Models\Topic;
use App\Models\User;
use App\Services\Ai\CourseWriter;
use App\Services\Ai\NullCourseWriter;
use App\Services\Ai\OpenAiCourseWriter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Writing a course, and the ways it is allowed to fail.
 *
 * The happy path matters least here. What matters is that a missing key, a
 * refusal or a broken reply all end with an admin who can see what happened —
 * because the alternative is a subject that sits in `generating` forever and
 * nobody can tell whether it is working or stuck.
 */
class CourseGenerationTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::where('email', 'admin@classmate9ja.ng')->firstOrFail();
    }

    /** A subject with a level nothing has been written for yet. */
    private function emptyOffering(): SubjectLevel
    {
        $subject = Subject::create([
            'slug' => 'test-' . Str::lower(Str::random(8)),
            'name' => 'Test Subject',
            'short_name' => 'Test',
            'emoji' => '🧪',
            'tone' => 'brand',
            'is_core' => false,
            'status' => 'published',
        ]);

        return SubjectLevel::create([
            'subject_id' => $subject->id,
            'level' => 'JSS1',
            'status' => 'draft',
        ]);
    }

    private function newRun(SubjectLevel $offering): CourseGeneration
    {
        return CourseGeneration::create([
            'subject_id' => $offering->subject_id,
            'level' => $offering->level,
            'requested_by' => $this->admin()->id,
            'brief' => 'Twelve weeks of introductory material following the NERDC scheme.',
            'status' => 'queued',
        ]);
    }

    private function reply(array $payload): array
    {
        return ['choices' => [['message' => ['content' => json_encode($payload)]]]];
    }

    private function writer(): OpenAiCourseWriter
    {
        return new OpenAiCourseWriter(
            key: 'sk-test-not-a-real-key',
            baseUrl: 'https://api.openai.com/v1',
            model: 'gpt-4o-mini',
        );
    }

    /* --------------------------------------------------------- the null path */

    public function test_with_no_key_the_run_fails_and_says_why(): void
    {
        $this->app->instance(CourseWriter::class, new NullCourseWriter());

        $offering = $this->emptyOffering();
        $run = $this->newRun($offering);

        (new GenerateCourse($run->id))->handle(app(CourseWriter::class));

        $run->refresh();

        $this->assertTrue($run->hasFailed());
        $this->assertStringContainsString('No AI provider', $run->error);

        // And crucially the offering is not left saying "writing".
        $this->assertNotSame('generating', $offering->fresh()->status);
    }

    public function test_an_unreadable_scheme_fails_the_run_rather_than_half_building(): void
    {
        Http::fake(['*/chat/completions' => Http::response(
            ['choices' => [['message' => ['content' => 'I cannot do that.']]]]
        )]);

        $this->app->instance(CourseWriter::class, $this->writer());

        $offering = $this->emptyOffering();
        $run = $this->newRun($offering);

        (new GenerateCourse($run->id))->handle(app(CourseWriter::class));

        $run->refresh();

        $this->assertTrue($run->hasFailed());
        $this->assertSame(0, Topic::where('subject_id', $offering->subject_id)->count());
    }

    /* ------------------------------------------------------------ the scheme */

    public function test_a_scheme_becomes_topics_and_queues_a_job_for_each(): void
    {
        Queue::fake();

        Http::fake(['*/chat/completions' => Http::response($this->reply(['weeks' => [
            ['week' => 1, 'week_title' => 'Measurement', 'topics' => [
                ['title' => 'Units and standards', 'minutes' => 30],
                ['title' => 'Reading instruments', 'minutes' => 25],
            ]],
            ['week' => 2, 'week_title' => 'Matter', 'topics' => [
                ['title' => 'States of matter', 'minutes' => 30],
            ]],
        ]]))]);

        $this->app->instance(CourseWriter::class, $this->writer());

        $offering = $this->emptyOffering();
        $run = $this->newRun($offering);

        (new GenerateCourse($run->id))->handle(app(CourseWriter::class));

        $topics = Topic::where('subject_id', $offering->subject_id)
            ->where('level', 'JSS1')
            ->orderBy('week')->orderBy('position')->get();

        $this->assertCount(3, $topics);
        $this->assertSame('Units and standards', $topics[0]->title);

        // Refs follow the shipped w{week}t{position} convention, so generated
        // content is indistinguishable from seeded content downstream.
        $this->assertSame(['w1t1', 'w1t2', 'w2t1'], $topics->pluck('ref')->all());

        $this->assertSame(3, $run->fresh()->topics_written);

        Queue::assertPushed(GenerateTopicContent::class, 3);
    }

    public function test_generation_runs_on_its_own_queue(): void
    {
        Queue::fake();

        $offering = $this->emptyOffering();
        $run = $this->newRun($offering);

        GenerateCourse::dispatch($run->id);

        // Writing a course must never sit in front of a script a student just
        // submitted, nor behind a weekly report blast.
        Queue::assertPushed(GenerateCourse::class, fn ($job) => $job->queue === 'content');
    }

    /* ------------------------------------------------------------- a lesson */

    public function test_a_topic_gets_both_locales_and_its_classwork(): void
    {
        $offering = $this->emptyOffering();
        $run = $this->newRun($offering);

        $topic = Topic::create([
            'subject_id' => $offering->subject_id,
            'level' => 'JSS1',
            'ref' => 'w1t1',
            'week' => 1,
            'week_title' => 'Measurement',
            'title' => 'Units and standards',
            'minutes' => 30,
            'position' => 1,
        ]);

        Http::fakeSequence()
            ->push($this->reply([
                'title' => 'Units and standards',
                'objectives' => ['Name the SI units'],
                'body' => [['t' => 'p', 'text' => 'A unit is an agreed size.']],
            ]))
            ->push($this->reply([
                'title' => 'Units and standards',
                'objectives' => ['Sabi the SI units'],
                'body' => [['t' => 'p', 'text' => 'Unit na size wey everybody gree on.']],
            ]))
            ->push($this->reply(['questions' => [
                ['prompt' => 'What is the SI unit of length?',
                 'options' => ['metre', 'litre', 'gram', 'second'],
                 'answer_index' => 0, 'explanation' => 'Length is measured in metres.'],
            ]]));

        $this->app->instance(CourseWriter::class, $this->writer());

        (new GenerateTopicContent($topic->id, $run->id))->handle(app(CourseWriter::class));

        $locales = Lesson::where('topic_id', $topic->id)->pluck('locale')->sort()->values()->all();

        // Both, always. lessonFor() falls back to English when a locale is
        // missing — silently — so a course generated with the Pidgin half
        // skipped would look complete and quietly serve the wrong language.
        $this->assertSame(['en', 'pcm'], $locales);

        $this->assertSame(1, ClassworkQuestion::where('topic_id', $topic->id)->count());
    }

    public function test_the_last_topic_to_finish_publishes_the_offering(): void
    {
        $offering = $this->emptyOffering();
        $run = $this->newRun($offering);
        $offering->update(['status' => 'generating']);

        $topic = Topic::create([
            'subject_id' => $offering->subject_id,
            'level' => 'JSS1', 'ref' => 'w1t1', 'week' => 1,
            'week_title' => 'Measurement', 'title' => 'Units', 'minutes' => 30, 'position' => 1,
        ]);

        Http::fake(['*/chat/completions' => Http::response($this->reply([
            'title' => 'Units', 'objectives' => ['Know units'],
            'body' => [['t' => 'p', 'text' => 'Body.']],
            'questions' => [],
        ]))]);

        $this->app->instance(CourseWriter::class, $this->writer());

        (new GenerateTopicContent($topic->id, $run->id))->handle(app(CourseWriter::class));

        $offering->refresh();

        $this->assertTrue($offering->isPublished());
        $this->assertSame(1, $offering->topic_count);
        $this->assertTrue($run->fresh()->isDone());
    }

    /* ----------------------------------------------------------- questions */

    public function test_generated_questions_are_flagged_as_unreviewed_ai(): void
    {
        $offering = $this->emptyOffering();

        $topic = Topic::create([
            'subject_id' => $offering->subject_id,
            'level' => 'JSS1', 'ref' => 'w1t1', 'week' => 1,
            'week_title' => 'Measurement', 'title' => 'Units', 'minutes' => 30, 'position' => 1,
        ]);

        Http::fake(['*/chat/completions' => Http::response($this->reply(['questions' => [
            ['prompt' => 'The SI unit of mass is?',
             'options' => ['kilogram', 'metre', 'second', 'newton'],
             'answer_index' => 0, 'explanation' => 'Mass is in kilograms.', 'topic' => 'Units'],
        ]]))]);

        $this->app->instance(CourseWriter::class, $this->writer());

        (new GenerateQuestions($offering->subject_id, 'JSS1', $topic->id))->handle(app(CourseWriter::class));

        $question = Question::where('subject_id', $offering->subject_id)->firstOrFail();

        $this->assertSame('ai', $question->source);
        $this->assertNull($question->reviewed_at);
        $this->assertTrue($question->isUnreviewedAi());
        $this->assertTrue($question->hasUsableAnswer());

        // Never dressed up as a real past paper.
        $this->assertSame('PRACTICE', $question->exam_body);
    }

    /**
     * An answer index pointing past the end of the options marks a correct
     * student wrong, and nothing on the screen looks broken. Those never reach
     * the database.
     */
    public function test_a_question_with_a_broken_answer_key_is_dropped(): void
    {
        Http::fake(['*/chat/completions' => Http::response($this->reply(['questions' => [
            ['prompt' => 'Broken one', 'options' => ['a', 'b'], 'answer_index' => 7, 'explanation' => 'x'],
            ['prompt' => 'Good one', 'options' => ['a', 'b', 'c', 'd'], 'answer_index' => 2, 'explanation' => 'y'],
        ]]))]);

        $written = $this->writer()->questions('Test Subject', 'JSS1', 'Units', 10);

        $this->assertCount(1, $written);
        $this->assertSame('Good one', $written[0]['prompt']);
    }

    public function test_generated_questions_are_not_written_twice(): void
    {
        $offering = $this->emptyOffering();

        $topic = Topic::create([
            'subject_id' => $offering->subject_id,
            'level' => 'JSS1', 'ref' => 'w1t1', 'week' => 1,
            'week_title' => 'M', 'title' => 'Units', 'minutes' => 30, 'position' => 1,
        ]);

        Http::fake(['*/chat/completions' => Http::response($this->reply(['questions' => [
            ['prompt' => 'The SI unit of mass is?',
             'options' => ['kilogram', 'metre', 'second', 'newton'],
             'answer_index' => 0, 'explanation' => 'Mass is in kilograms.'],
        ]]))]);

        $this->app->instance(CourseWriter::class, $this->writer());

        (new GenerateQuestions($offering->subject_id, 'JSS1', $topic->id))->handle(app(CourseWriter::class));
        (new GenerateQuestions($offering->subject_id, 'JSS1', $topic->id))->handle(app(CourseWriter::class));

        $this->assertSame(1, Question::where('subject_id', $offering->subject_id)->count());
    }

    /* ------------------------------------------------------ the admin screen */

    public function test_an_admin_can_queue_a_course(): void
    {
        Queue::fake();

        $offering = $this->emptyOffering();

        Livewire::actingAs($this->admin())->test(Courses::class)
            ->call('startGeneration', $offering->subject_id, 'JSS1')
            ->set('brief', 'Twelve weeks following the NERDC junior secondary scheme, '
                . 'starting with measurement and ending with simple machines.')
            ->call('requestGeneration')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('course_generations', [
            'subject_id' => $offering->subject_id,
            'level' => 'JSS1',
            'status' => 'queued',
        ]);

        $this->assertSame('generating', $offering->fresh()->status);

        Queue::assertPushed(GenerateCourse::class);
    }

    public function test_a_thin_brief_is_refused(): void
    {
        Queue::fake();

        $offering = $this->emptyOffering();

        Livewire::actingAs($this->admin())->test(Courses::class)
            ->call('startGeneration', $offering->subject_id, 'JSS1')
            ->set('brief', 'do biology')
            ->call('requestGeneration')
            ->assertHasErrors('brief');

        Queue::assertNothingPushed();
    }
}
