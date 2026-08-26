<?php

namespace App\Support;

/**
 * The six years of Nigerian secondary school, in one place.
 *
 * Levels were previously a loose string on the user and nothing else, which is
 * why a JSS1 student and an SS3 student were served identical lessons. Now
 * that content is scoped by level, "which levels exist" and "is this one
 * junior" are questions asked from the seeder, the API, the admin screens and
 * the generator alike — so they get one answer, here.
 */
class Levels
{
    public const JUNIOR = ['JSS1', 'JSS2', 'JSS3'];

    public const SENIOR = ['SS1', 'SS2', 'SS3'];

    public const ALL = ['JSS1', 'JSS2', 'JSS3', 'SS1', 'SS2', 'SS3'];

    public static function isValid(?string $level): bool
    {
        return in_array($level, self::ALL, true);
    }

    public static function isJunior(?string $level): bool
    {
        return in_array($level, self::JUNIOR, true);
    }

    public static function isSenior(?string $level): bool
    {
        return in_array($level, self::SENIOR, true);
    }

    /** The validation rule every level field uses. */
    public static function rule(): string
    {
        return 'in:' . implode(',', self::ALL);
    }

    /**
     * A band name for a subject offered at a run of levels, for the admin list.
     * "JSS1-JSS3" reads better in a table than six chips.
     */
    public static function describe(array $levels): string
    {
        $ordered = array_values(array_intersect(self::ALL, $levels));

        if (! $ordered) {
            return 'none';
        }

        if ($ordered === self::ALL) {
            return 'JSS1-SS3';
        }

        if ($ordered === self::JUNIOR) {
            return 'Junior';
        }

        if ($ordered === self::SENIOR) {
            return 'Senior';
        }

        return count($ordered) === 1
            ? $ordered[0]
            : $ordered[0] . '-' . end($ordered);
    }
}
