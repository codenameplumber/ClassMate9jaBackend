<?php

namespace App\Services\Ai;

/**
 * Writing curriculum, as opposed to marking it.
 *
 * A second seam alongside Marker, and separate from it on purpose: marking is
 * a short call against a scheme with a right answer, and writing a course is a
 * long one with none. They fail differently, cost differently and want
 * different models, so they get different interfaces.
 *
 * Every method returns null on any failure. A course that half-wrote is a
 * course an admin can look at and re-run; a course that threw halfway leaves a
 * subject in `generating` forever.
 */
interface CourseWriter
{
    /**
     * The scheme of work: twelve weeks, each with its topics.
     *
     * @return array<int, array{week:int, week_title:string, topics:array<int, array{title:string, minutes:int}>}>|null
     */
    public function scheme(string $subject, string $level, string $brief, int $weeks = 12): ?array;

    /**
     * One lesson, in one locale.
     *
     * @param  string  $locale  en | pcm
     * @return array{title:string, objectives:string[], body:array}|null
     */
    public function lesson(
        string $subject,
        string $level,
        string $weekTitle,
        string $topic,
        string $locale,
    ): ?array;

    /**
     * Objective questions checking one topic was understood.
     *
     * @return array<int, array{prompt:string, options:string[], answer_index:int, explanation:string}>|null
     */
    public function classwork(string $subject, string $level, string $topic, int $count = 2): ?array;

    /**
     * Exam-style past questions for the practice bank.
     *
     * @return array<int, array{prompt:string, options:string[], answer_index:int, explanation:string, exam_body:string, year:int, topic:string}>|null
     */
    public function questions(string $subject, string $level, string $topic, int $count = 10): ?array;

    public function isConfigured(): bool;
}
