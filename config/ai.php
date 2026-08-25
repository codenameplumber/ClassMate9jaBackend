<?php

return [

    /*
    | Which marking driver to use.
    |
    |   null   — no AI at all. Objective marking still runs in PHP, the tutor
    |            still answers from the on-device layer, and written answers
    |            simply wait for a human. The platform is fully functional in
    |            this mode; that is deliberate, not a fallback.
    |
    |   claude — written marking, assignment drafting and the live tutor all
    |            switch on.
    */
    'driver' => env('AI_DRIVER', 'claude'),

    /*
    | Marking and drafting. Both work against a marking scheme, so the model
    | has a lot of scaffolding to lean on.
    */
    'model' => env('AI_MODEL', 'claude-opus-5'),

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
    'confidence_floor' => (int) env('AI_CONFIDENCE_FLOOR', 80),

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],
];
