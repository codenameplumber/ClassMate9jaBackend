<?php

namespace Tests\Feature;

use App\Livewire\Teacher\Assignments;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Editing a paper before thirty students see it.
 *
 * The drafter pulls from the scheme or from a model, and both get things
 * wrong: an option that gives the answer away, marks that do not match how the
 * teacher teaches it, a question about a topic the class has not reached. The
 * draft used to be read-only, so the choice was publish it as written or throw
 * it away — which is how a teacher stops using the tool.
 */
class AssignmentDraftTest extends TestCase
{
    use DatabaseTransactions;

    private function teacher(): User
    {
        return User::where('email', 'teacher@classmate9ja.ng')->firstOrFail();
    }

    /** A component with a drafted paper open, ready to edit. */
    private function drafting()
    {
        $teacher = $this->teacher();
        $classroom = Classroom::where('teacher_id', $teacher->id)->firstOrFail();
        $topic = Topic::where('subject_id', $classroom->subject_id)
            ->where('level', $classroom->level)
            ->firstOrFail();

        return Livewire::actingAs($teacher)->test(Assignments::class)
            ->set('classroomId', $classroom->id)
            ->call('openDrafter')
            ->set('topicId', $topic->id)
            ->call('generate');
    }

    public function test_a_draft_arrives_with_questions_to_edit(): void
    {
        $this->drafting()
            ->assertSet('editing', null)
            ->assertCount('draft.questions', 5);
    }

    public function test_a_teacher_can_rewrite_a_question(): void
    {
        $this->drafting()
            ->call('editQuestion', 0)
            ->assertSet('editing', 0)
            ->set('draft.questions.0.prompt', 'What is the capital of Nigeria?')
            ->assertSet('draft.questions.0.prompt', 'What is the capital of Nigeria?');
    }

    public function test_a_teacher_can_change_which_option_is_correct(): void
    {
        $component = $this->drafting()->call('setAnswer', 0, 2);

        $question = $component->get('draft.questions.0');

        $this->assertSame(2, $question['answer_index']);

        // The marking scheme follows the answer, or the marker checks against
        // the option that used to be right.
        $this->assertSame($question['options'][2], $question['expected']);
    }

    public function test_a_teacher_can_delete_a_question(): void
    {
        $component = $this->drafting();
        $before = count($component->get('draft.questions'));

        $component->call('removeQuestion', 1)
            ->assertCount('draft.questions', $before - 1);
    }

    public function test_a_teacher_can_add_a_written_question(): void
    {
        $component = $this->drafting();
        $before = count($component->get('draft.questions'));

        $component->call('addQuestion', 'written')
            ->assertCount('draft.questions', $before + 1)
            ->assertSet('editing', $before);

        $this->assertSame('written', $component->get("draft.questions.{$before}.kind"));
    }

    public function test_a_teacher_can_reorder_questions(): void
    {
        $component = $this->drafting();
        $first = $component->get('draft.questions.0.prompt');
        $second = $component->get('draft.questions.1.prompt');

        $component->call('moveQuestion', 0, 1);

        $this->assertSame($second, $component->get('draft.questions.0.prompt'));
        $this->assertSame($first, $component->get('draft.questions.1.prompt'));
    }

    /* ------------------------------------------------------- what publishes */

    public function test_an_edited_paper_publishes_what_the_teacher_wrote(): void
    {
        $component = $this->drafting()
            ->set('draft.questions.0.prompt', 'A question the teacher typed themselves')
            ->set('draft.questions.0.max_mark', 7)
            ->call('publish');

        $component->assertHasNoErrors();

        $assignment = Assignment::latest('id')->with('questions')->first();
        $question = $assignment->questions->first();

        $this->assertSame('A question the teacher typed themselves', $question->prompt);
        $this->assertSame(7, $question->max_mark);
        $this->assertSame($assignment->questions->count(), $assignment->question_count);
    }

    /** A question with nothing in it must never reach a student. */
    public function test_an_empty_question_blocks_publishing(): void
    {
        $component = $this->drafting()
            ->set('draft.questions.0.prompt', '   ')
            ->call('publish')
            ->assertHasErrors('draft');

        // The teacher is told which question and why, not just "invalid".
        $this->assertStringContainsString(
            'no question in it',
            $component->instance()->getErrorBag()->first('draft')
        );
    }

    public function test_a_written_question_with_no_marking_scheme_blocks_publishing(): void
    {
        $component = $this->drafting();
        $index = count($component->get('draft.questions'));

        $component->call('addQuestion', 'written')
            ->set("draft.questions.{$index}.prompt", 'Explain photosynthesis.')
            ->set("draft.questions.{$index}.expected", '')
            ->call('publish')
            ->assertHasErrors('draft');
    }

    public function test_an_objective_question_with_no_correct_answer_blocks_publishing(): void
    {
        $component = $this->drafting();
        $index = count($component->get('draft.questions'));

        $component->call('addQuestion', 'objective')
            ->set("draft.questions.{$index}.prompt", 'Which is which?')
            ->set("draft.questions.{$index}.options", ['', '', '', ''])
            ->call('publish')
            ->assertHasErrors('draft');
    }

    /**
     * Blank options are dropped, and the answer follows its own text.
     *
     * Re-finding the answer by value matters: removing a blank option above it
     * would otherwise leave the index pointing at the wrong choice, and the
     * paper would mark a correct student wrong.
     */
    public function test_blank_options_are_dropped_without_moving_the_answer(): void
    {
        $component = $this->drafting();
        $index = count($component->get('draft.questions'));

        $component->call('addQuestion', 'objective')
            ->set("draft.questions.{$index}.prompt", 'Pick the right one.')
            ->set("draft.questions.{$index}.options", ['', 'Wrong', 'Right', ''])
            ->call('setAnswer', $index, 2)
            ->call('publish')
            ->assertHasNoErrors();

        $question = Assignment::latest('id')->first()->questions->last();

        $this->assertSame(['Wrong', 'Right'], $question->options);
        $this->assertSame('Right', $question->options[$question->answer_index]);
    }

    public function test_deleting_every_question_closes_the_draft(): void
    {
        $component = $this->drafting();

        for ($i = count($component->get('draft.questions')); $i > 0; $i--) {
            $component->call('removeQuestion', 0);
        }

        $component->assertSet('draft', []);
    }
}
