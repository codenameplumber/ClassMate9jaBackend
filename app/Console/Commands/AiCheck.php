<?php

namespace App\Console\Commands;

use App\Models\AssignmentQuestion;
use App\Models\Topic;
use App\Services\Ai\CourseWriter;
use App\Services\Ai\Marker;
use App\Services\Ai\NullMarker;
use App\Services\Ai\Tutor;
use Illuminate\Console\Command;

/**
 * Exercises every AI path against the real provider.
 *
 * The platform is built so that no model call is ever required — objective
 * marking runs in PHP and every failure path returns null. The cost of that
 * design is that the AI layer can be completely broken and every screen still
 * looks fine, because a dead key is indistinguishable from "no key set".
 *
 * This command is the difference. It makes real calls and prints what came
 * back, so a wrong key, a wrong model name or a blocked egress is found here
 * rather than by a teacher wondering why nothing is ever machine-marked.
 *
 * The two marking checks are also a first, tiny agreement test: a model answer
 * should score well with high confidence, and a vague one should fall below
 * the floor. A marker that is confident on both is worse than no marker.
 */
class AiCheck extends Command
{
    protected $signature = 'ai:check {--tutor-only : only exercise the tutor}';

    protected $description = 'Make a real call down every AI path and report what came back';

    public function handle(Marker $marker, Tutor $tutor, CourseWriter $writer): int
    {
        $floor = (int) config('ai.confidence_floor', 80);

        $this->line('');
        $this->line('  driver      : ' . (config('ai.driver') ?: 'none'));
        $this->line('  model       : ' . config('ai.model'));
        $this->line('  tutor model : ' . (config('ai.tutor_model') ?: config('ai.model')));
        $this->line('  timeout     : ' . config('ai.timeout') . 's');
        $this->line('  floor       : ' . $floor);
        $this->line('  marker      : ' . class_basename($marker));
        $this->line('  writer      : ' . class_basename($writer));
        $this->line('');

        if ($marker instanceof NullMarker && ! $tutor->isConfigured()) {
            $this->error('  No provider is active — the key for AI_DRIVER=' . config('ai.driver') . ' is empty.');
            $this->line('');
            $this->line('  This is a supported state, not a crash: objective marking still runs');
            $this->line('  in PHP, written answers wait for a teacher, and the tutor answers from');
            $this->line('  the on-device rule set. But nothing below can be checked.');
            $this->line('');

            return self::FAILURE;
        }

        $passed = 0;
        $failed = 0;

        $attempt = function (string $label, callable $run) use (&$passed, &$failed) {
            $started = microtime(true);

            try {
                $note = $run();
                $ms = (int) round((microtime(true) - $started) * 1000);

                if ($note === false) {
                    $this->error(sprintf('  x %-24s returned nothing after %dms', $label, $ms));
                    $failed++;

                    return;
                }

                $this->info(sprintf('  v %-24s %5dms  %s', $label, $ms, is_string($note) ? $note : ''));
                $passed++;
            } catch (\Throwable $e) {
                $this->error(sprintf('  x %-24s threw: %s', $label, $e->getMessage()));
                $failed++;
            }
        };

        $preview = fn (string $text) => $this->line(
            '      ' . str($text)->replaceMatches('/\s+/', ' ')->limit(70)
        );

        /* --- 1. The tutor, which talks to a student with no scheme to lean on --- */
        $attempt('tutor, english', function () use ($tutor, $preview) {
            $reply = $tutor->answer('Explain logarithms simply.', [
                'subject' => 'Mathematics',
                'topic' => 'Logarithms',
                'level' => 'SS2',
                'locale' => 'en',
            ]);

            if ($reply === null) {
                return false;
            }

            $preview($reply);

            return str_word_count($reply) . ' words';
        });

        /* --- 2. The same question in Pidgin, which is a product requirement --- */
        $attempt('tutor, pidgin', function () use ($tutor, $preview) {
            $reply = $tutor->answer('Explain logarithms simply.', [
                'subject' => 'Mathematics',
                'topic' => 'Logarithms',
                'level' => 'SS2',
                'locale' => 'pcm',
            ]);

            if ($reply === null) {
                return false;
            }

            $preview($reply);

            return str_word_count($reply) . ' words';
        });

        if ($this->option('tutor-only')) {
            return $this->summary($passed, $failed);
        }

        /* --- 3. Marking, against a real question out of the database --- */
        $question = AssignmentQuestion::where('kind', 'written')
            ->whereNotNull('expected')
            ->first();

        if (! $question) {
            $this->warn('  - marking                 skipped: no written question is seeded');
        } else {
            $this->line('');
            $this->line('  marking: ' . str($question->prompt)->limit(60) . ' (' . $question->max_mark . ' marks)');
            $this->line('');

            $attempt('marks a good answer', function () use ($marker, $question, $floor) {
                $result = $marker->mark($question, (string) $question->expected);

                if ($result === null) {
                    return false;
                }

                return sprintf(
                    '%d/%d, confidence %d (%s)',
                    $result['mark'],
                    $question->max_mark,
                    $result['confidence'],
                    $result['confidence'] >= $floor ? 'confident' : 'FLAGGED'
                );
            });

            // If this returns full marks and high confidence, the floor is
            // decorative and the review queue will never see anything.
            $attempt('marks a weak answer', function () use ($marker, $question, $floor) {
                $result = $marker->mark(
                    $question,
                    'I think say na something about the topic but I no fit remember am well.'
                );

                if ($result === null) {
                    return false;
                }

                return sprintf(
                    '%d/%d, confidence %d (%s)',
                    $result['mark'],
                    $question->max_mark,
                    $result['confidence'],
                    $result['confidence'] < $floor ? 'flagged, correctly' : 'CONFIDENT - check this'
                );
            });

            // A blank answer must never cost a request.
            $attempt('declines a blank answer', function () use ($marker, $question) {
                return $marker->mark($question, '   ') === null
                    ? 'null, without calling out'
                    : false;
            });
        }

        /* --- 4. Writing curriculum, which the admin course screen leans on --- */
        $attempt('writes a scheme', function () use ($writer) {
            $scheme = $writer->scheme(
                'Basic Science',
                'JSS1',
                'Twelve weeks of introductory material following the NERDC scheme, '
                . 'starting with measurement.',
                3,
            );

            if (! $scheme) {
                return false;
            }

            $topics = collect($scheme)->sum(fn ($week) => count($week['topics']));

            return count($scheme) . ' weeks, ' . $topics . ' topics';
        });

        $attempt('writes a pidgin lesson', function () use ($writer, $preview) {
            $lesson = $writer->lesson('Mathematics', 'SS2', 'Logarithms', 'Laws of logarithms', 'pcm');

            if (! $lesson) {
                return false;
            }

            $first = collect($lesson['body'])->firstWhere('t', 'p')['text'] ?? '';
            if ($first) {
                $preview($first);
            }

            return count($lesson['body']) . ' blocks, ' . count($lesson['objectives']) . ' objectives';
        });

        $attempt('writes practice questions', function () use ($writer) {
            $questions = $writer->questions('Mathematics', 'SS2', 'Logarithms', 4);

            if (! $questions) {
                return false;
            }

            // Every one that survives has an answer key pointing at a real
            // option -- the cleaner drops the rest before they reach here.
            $usable = collect($questions)
                ->filter(fn ($q) => isset($q['options'][$q['answer_index']]))
                ->count();

            return $usable . '/' . count($questions) . ' with a usable answer key';
        });

        /* --- 5. Drafting, which the assignment screen leans on --- */
        $topic = Topic::with('subject')->first();

        if (! $topic) {
            $this->warn('  - drafting                skipped: no topic is seeded');
        } else {
            $attempt('drafts questions', function () use ($marker, $topic) {
                $drafted = $marker->draftQuestions($topic->subject->name, $topic->title, 'SS2', 3);

                if (! $drafted) {
                    return false;
                }

                $written = collect($drafted)->where('kind', 'written')->count();

                return count($drafted) . ' questions, ' . $written . ' written';
            });
        }

        return $this->summary($passed, $failed);
    }

    private function summary(int $passed, int $failed): int
    {
        $this->line('');
        $this->line(sprintf('  %d passed, %d failed', $passed, $failed));
        $this->line('');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
