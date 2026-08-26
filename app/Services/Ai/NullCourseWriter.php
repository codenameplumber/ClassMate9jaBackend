<?php

namespace App\Services\Ai;

/**
 * The no-key course writer.
 *
 * Unlike marking, there is no useful fallback here: a course cannot be
 * assembled out of nothing the way an assignment can be built from the
 * question bank. So this declines, the job records why, and the admin sees
 * "no AI provider configured" against their request rather than a subject
 * that sat in `generating` and never moved.
 */
class NullCourseWriter implements CourseWriter
{
    public function scheme(string $subject, string $level, string $brief, int $weeks = 12): ?array
    {
        return null;
    }

    public function lesson(
        string $subject,
        string $level,
        string $weekTitle,
        string $topic,
        string $locale,
    ): ?array {
        return null;
    }

    public function classwork(string $subject, string $level, string $topic, int $count = 2): ?array
    {
        return null;
    }

    public function questions(string $subject, string $level, string $topic, int $count = 10): ?array
    {
        return null;
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
