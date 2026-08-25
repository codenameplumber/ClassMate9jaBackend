<?php

namespace App\Services\Ai\Schema;

use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;

/**
 * The shape a marked answer comes back in.
 *
 * Using a structured output rather than asking for JSON in the prompt means the
 * response is validated at the API boundary. A marking pipeline that has to
 * regex a number out of prose is one bad reply away from writing a wrong grade
 * into a student's record.
 */
class MarkResult implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    #[Constrained(description: 'Marks awarded, a whole number from 0 up to the maximum for this question.')]
    public int $mark;

    #[Constrained(description: 'How confident you are that a teacher would agree with this mark, 0-100. Below 80 means a human should check it.')]
    public int $confidence;

    #[Constrained(description: 'One or two sentences addressed to the student, naming the specific mistake or what earned the marks.')]
    public string $comment;
}
