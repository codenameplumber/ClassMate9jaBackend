<?php

namespace Tests\Feature;

use App\Models\AssignmentQuestion;
use App\Services\Ai\OpenAiMarker;
use App\Services\Ai\Tutor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The OpenAI path, proven without a key and without spending anything.
 *
 * No model call has ever run against this codebase, so every one of these
 * behaviours was, until now, an assumption: that the request is shaped the way
 * the API expects, that a good reply is parsed, and — the part that actually
 * protects a student — that every failure returns null instead of a guess.
 *
 * Http::fake() covers all of it. What it deliberately does not cover is
 * whether the marks are any good; that needs a real key and `php artisan
 * ai:check`, and after that a real agreement measurement against scripts a
 * teacher has already marked.
 */
class OpenAiEngineTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = 'https://api.openai.com/v1';

    private function marker(int $timeout = 35): OpenAiMarker
    {
        return new OpenAiMarker(
            key: 'sk-test-not-a-real-key',
            baseUrl: self::URL,
            model: 'gpt-4o-mini',
            timeout: $timeout,
        );
    }

    private function tutor(): Tutor
    {
        return new Tutor(
            key: 'sk-test-not-a-real-key',
            baseUrl: self::URL,
            model: 'gpt-4o',
            timeout: 35,
        );
    }

    private function question(): AssignmentQuestion
    {
        return AssignmentQuestion::where('kind', 'written')
            ->whereNotNull('expected')
            ->firstOrFail();
    }

    /** A well-formed chat completion carrying `$content` as the message body. */
    private function reply(string $content): array
    {
        return ['choices' => [['message' => ['content' => $content]]]];
    }

    /* ----------------------------------------------------------- marking */

    public function test_a_marked_answer_is_parsed_into_a_mark_and_a_confidence(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response($this->reply(
                '{"mark": 3, "confidence": 91, "comment": "Both laws stated correctly."}'
            )),
        ]);

        $question = $this->question();
        $result = $this->marker()->mark($question, 'log(MN) = log M + log N');

        $this->assertSame(3, $result['mark']);
        $this->assertSame(91, $result['confidence']);
        $this->assertSame('Both laws stated correctly.', $result['comment']);
    }

    /**
     * The scheme goes in the system message and the answer in the user turn.
     *
     * That ordering is not cosmetic: the scheme is identical for all thirty
     * scripts on an assignment and only the answer varies, which is what lets
     * the provider reuse the prefix across a whole class. Reversing it would
     * still work and would quietly cost full price on every script.
     */
    public function test_the_marking_scheme_leads_and_the_answer_follows(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response($this->reply(
                '{"mark": 1, "confidence": 85, "comment": "Partly right."}'
            )),
        ]);

        $question = $this->question();
        $this->marker()->mark($question, 'A distinctive student answer.');

        Http::assertSent(function (Request $request) use ($question) {
            $body = $request->data();

            $this->assertSame(self::URL . '/chat/completions', $request->url());
            $this->assertSame('Bearer sk-test-not-a-real-key', $request->header('Authorization')[0]);
            $this->assertSame('gpt-4o-mini', $body['model']);

            // Marking is not a creative task; the same script twice must not
            // produce two different grades.
            $this->assertSame(0, $body['temperature']);
            $this->assertSame('json_object', $body['response_format']['type']);

            $this->assertSame('system', $body['messages'][0]['role']);
            $this->assertStringContainsString($question->expected, $body['messages'][0]['content']);

            $this->assertSame('user', $body['messages'][1]['role']);
            $this->assertStringContainsString('A distinctive student answer.', $body['messages'][1]['content']);

            return true;
        });
    }

    /** A model that returns 7 out of 4 must not write 7 into a student's record. */
    public function test_a_mark_above_the_maximum_is_clamped(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response($this->reply(
                '{"mark": 99, "confidence": 140, "comment": "Overshoot."}'
            )),
        ]);

        $question = $this->question();
        $result = $this->marker()->mark($question, 'Something.');

        $this->assertSame($question->max_mark, $result['mark']);
        $this->assertSame(100, $result['confidence']);
    }

    public function test_a_failed_request_marks_nothing(): void
    {
        Http::fake(['*/chat/completions' => Http::response(['error' => 'nope'], 500)]);

        $this->assertNull($this->marker()->mark($this->question(), 'An answer.'));
    }

    public function test_a_reply_that_is_not_json_marks_nothing(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response($this->reply('I would rather not mark this.')),
        ]);

        $this->assertNull($this->marker()->mark($this->question(), 'An answer.'));
    }

    /** JSON wrapped in prose or a fence is common enough to be worth tolerating. */
    public function test_json_wrapped_in_prose_is_still_read(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response($this->reply(
                "Here is my assessment:\n```json\n{\"mark\": 2, \"confidence\": 88, \"comment\": \"Good.\"}\n```"
            )),
        ]);

        $result = $this->marker()->mark($this->question(), 'An answer.');

        $this->assertSame(2, $result['mark']);
        $this->assertSame(88, $result['confidence']);
    }

    /** A blank answer is decided locally. It must never cost a request. */
    public function test_a_blank_answer_never_reaches_the_provider(): void
    {
        Http::fake();

        $this->assertNull($this->marker()->mark($this->question(), '   '));

        Http::assertNothingSent();
    }

    public function test_an_unconfigured_marker_never_reaches_the_provider(): void
    {
        Http::fake();

        $marker = new OpenAiMarker(key: null, baseUrl: self::URL, model: 'gpt-4o-mini');

        $this->assertFalse($marker->isConfigured());
        $this->assertNull($marker->mark($this->question(), 'A real answer.'));

        Http::assertNothingSent();
    }

    /* ------------------------------------------------------------- tutor */

    public function test_the_tutor_returns_the_reply_text(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response($this->reply('A logarithm asks "what power?".')),
        ]);

        $this->assertSame(
            'A logarithm asks "what power?".',
            $this->tutor()->answer('Explain logarithms.', ['subject' => 'Mathematics'])
        );
    }

    /** Pidgin is a first-class locale here, not a translation applied afterwards. */
    public function test_asking_in_pidgin_instructs_the_model_in_pidgin(): void
    {
        Http::fake(['*/chat/completions' => Http::response($this->reply('Log na power.'))]);

        $this->tutor()->answer('Explain logarithms.', [
            'subject' => 'Mathematics',
            'topic' => 'Logarithms',
            'locale' => 'pcm',
        ]);

        Http::assertSent(function (Request $request) {
            $system = $request->data()['messages'][0]['content'];

            $this->assertStringContainsString('Nigerian Pidgin', $system);
            $this->assertStringContainsString('Logarithms', $system);

            return true;
        });
    }

    public function test_a_failed_tutor_call_degrades_to_the_on_device_tutor(): void
    {
        Http::fake(['*/chat/completions' => Http::response('', 429)]);

        // null is the signal the app falls back on. An exception here would put
        // an error in a student's face instead of the offline tutor's answer.
        $this->assertNull($this->tutor()->answer('Explain logarithms.'));
    }

    public function test_an_empty_reply_counts_as_no_answer(): void
    {
        Http::fake(['*/chat/completions' => Http::response($this->reply('   '))]);

        $this->assertNull($this->tutor()->answer('Explain logarithms.'));
    }

    /** The same question on the same topic is asked constantly; it must key alike. */
    public function test_the_cache_key_ignores_spacing_and_case(): void
    {
        $tutor = $this->tutor();
        $context = ['topic' => 'Logarithms', 'subject' => 'Mathematics', 'locale' => 'en'];

        $this->assertSame(
            $tutor->cacheKey('What  is a LOGARITHM?', $context),
            $tutor->cacheKey('what is a logarithm?', $context)
        );

        $this->assertNotSame(
            $tutor->cacheKey('What is a logarithm?', $context),
            $tutor->cacheKey('What is a logarithm?', ['topic' => 'Indices'] + $context)
        );
    }
}
