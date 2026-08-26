<?php

return [

    /*
    | Which provider marks written answers, drafts papers and answers the tutor.
    |
    |   openai — uses OPENAI_API_KEY
    |   claude — uses ANTHROPIC_API_KEY
    |   null   — no AI at all
    |
    | With no key, whatever the driver says, the platform stays fully
    | functional: objective marking runs in PHP, the tutor answers from the
    | on-device layer, and written answers wait for a teacher. That is
    | deliberate, not a fallback.
    */
    'driver' => env('AI_DRIVER', 'openai'),

    /*
    | Marking and drafting. Both work against a marking scheme, so the model has
    | plenty of scaffolding to lean on.
    */
    'model' => env('AI_MODEL', 'gpt-4o-mini'),

    /*
    | The tutor talks to a student directly with no scheme to lean on, so it is
    | the one place worth spending more. Falls back to AI_MODEL when unset.
    */
    'tutor_model' => env('AI_TUTOR_MODEL'),

    /*
    | Any answer the marker is less sure about than this is flagged for the
    | teacher and pushed to the top of the queue. Below this line the marker is
    | asking for a human, not making a decision.
    */
    /*
    | Writing a course is a longer, more open generation than marking one
    | answer against a scheme, so it gets its own model and its own timeout.
    | Falls back to AI_MODEL when unset.
    */
    'writer_model' => env('AI_WRITER_MODEL'),

    'writer_timeout' => (int) env('AI_WRITER_TIMEOUT', 90),

    /*
    | How many practice questions a topic is filled to when a student opens a
    | thin subject. Generation is on demand and cached forever after, so this
    | is a ceiling rather than a batch size.
    */
    'questions_per_topic' => (int) env('AI_QUESTIONS_PER_TOPIC', 10),

    /*
    | What a full bank looks like: how many questions one subject at one level
    | is stocked to by `php artisan questions:generate`. Divided across that
    | offering's topics, so a 24-topic course asks for roughly 21 each.
    */
    'questions_per_offering' => (int) env('AI_QUESTIONS_PER_OFFERING', 500),

    'confidence_floor' => (int) env('AI_CONFIDENCE_FLOOR', 80),

    'timeout' => (int) env('AI_TIMEOUT', 60),

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],
];
