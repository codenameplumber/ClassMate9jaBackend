<?php

namespace App\Support;

use App\Models\Submission;
use Illuminate\Support\Facades\Auth;

/**
 * The sidebar and mobile tab bar, defined once per role.
 *
 * This is the server-side twin of the PWA's shell.js: same groups, same
 * labels, same order, so a teacher moving between the two never has to
 * relearn where anything lives.
 */
class Navigation
{
    /*
    | The emoji below are in double quotes on purpose.
    |
    | PHP only interprets \u{...} inside a double-quoted string. In single
    | quotes it is the literal nine characters, which is exactly what every
    | sidebar and tab bar was rendering: `\u{1F3E0}Dashboard`.
    */

    public static function forRole(string $role): array
    {
        return match ($role) {
            'teacher' => [
                ['title' => 'Class', 'items' => [
                    ['route' => 'teacher.dashboard', 'label' => 'Dashboard', 'emoji' => "\u{1F3E0}"],
                    ['route' => 'teacher.classes', 'label' => 'My classes', 'emoji' => "\u{1F465}"],
                    ['route' => 'teacher.assignments', 'label' => 'Assignments', 'emoji' => "\u{1F4CB}"],
                ]],
                ['title' => 'Marking', 'items' => [
                    ['route' => 'teacher.marking', 'label' => 'Marking queue', 'emoji' => "\u{270D}\u{FE0F}",
                     'badge' => static::pendingCount()],
                    ['route' => 'teacher.reports', 'label' => 'Reports', 'emoji' => "\u{1F4CA}"],
                ]],
            ],
            'parent' => [
                ['title' => 'My child', 'items' => [
                    ['route' => 'parent.dashboard', 'label' => 'Dashboard', 'emoji' => "\u{1F3E0}"],
                    ['route' => 'parent.report', 'label' => 'Weekly report', 'emoji' => "\u{1F4C4}"],
                ]],
            ],
            'admin' => [
                ['title' => 'Platform', 'items' => [
                    ['route' => 'admin.dashboard', 'label' => 'Overview', 'emoji' => "\u{1F3E0}"],
                ]],
                ['title' => 'Curriculum', 'items' => [
                    ['route' => 'admin.courses', 'label' => 'Courses', 'emoji' => "\u{1F4D8}"],
                    ['route' => 'admin.content', 'label' => 'Lessons', 'emoji' => "\u{1F4DA}"],
                ]],
                ['title' => 'People', 'items' => [
                    ['route' => 'admin.teachers', 'label' => 'Teachers', 'emoji' => "\u{1F9D1}\u{200D}\u{1F3EB}",
                     'badge' => static::pendingTeacherCount()],
                    ['route' => 'admin.students', 'label' => 'Students', 'emoji' => "\u{1F393}"],
                    ['route' => 'admin.people', 'label' => 'Everyone', 'emoji' => "\u{1F465}"],
                ]],
            ],
            default => [
                ['title' => 'Learn', 'items' => [
                    ['route' => 'learn.screen', 'params' => 'dashboard', 'label' => 'Open my app', 'emoji' => "\u{1F4DA}"],
                ]],
            ],
        };
    }

    public static function tabsForRole(string $role): array
    {
        return match ($role) {
            'teacher' => [
                ['route' => 'teacher.dashboard', 'label' => 'Home', 'emoji' => "\u{1F3E0}"],
                ['route' => 'teacher.classes', 'label' => 'Classes', 'emoji' => "\u{1F465}"],
                ['route' => 'teacher.marking', 'label' => 'Marking', 'emoji' => "\u{270D}\u{FE0F}"],
                ['route' => 'teacher.assignments', 'label' => 'Work', 'emoji' => "\u{1F4CB}"],
            ],
            'parent' => [
                ['route' => 'parent.dashboard', 'label' => 'Home', 'emoji' => "\u{1F3E0}"],
                ['route' => 'parent.report', 'label' => 'Report', 'emoji' => "\u{1F4C4}"],
            ],
            'admin' => [
                ['route' => 'admin.dashboard', 'label' => 'Home', 'emoji' => "\u{1F3E0}"],
                ['route' => 'admin.courses', 'label' => 'Courses', 'emoji' => "\u{1F4D8}"],
                ['route' => 'admin.teachers', 'label' => 'Teachers', 'emoji' => "\u{1F9D1}\u{200D}\u{1F3EB}"],
                ['route' => 'admin.students', 'label' => 'Students', 'emoji' => "\u{1F393}"],
            ],
            default => [],
        };
    }

    /** Teacher accounts an admin has not decided on yet. */
    public static function pendingTeacherCount(): ?int
    {
        $user = Auth::user();

        if (! $user || ! $user->isAdmin()) {
            return null;
        }

        $count = \App\Models\User::pendingTeachers()->count();

        return $count ?: null;
    }

    /** Scripts waiting on the signed-in teacher, for the sidebar badge. */
    public static function pendingCount(): ?int
    {
        $user = Auth::user();
        if (! $user || ! $user->isTeacher()) {
            return null;
        }

        $count = Submission::whereHas('assignment.classroom', fn ($q) => $q->where('teacher_id', $user->id))
            ->where('status', '!=', 'approved')
            ->count();

        return $count ?: null;
    }
}
