<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    public const ROLES = ['student', 'teacher', 'parent', 'admin'];

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'name', 'email', 'password', 'role', 'phone', 'school_id', 'level',
        'locale', 'goal', 'daily_goal', 'subject_slug', 'onboarded_at',
        'status', 'approved_at', 'approved_by', 'rejected_reason',
    ];

    protected $hidden = ['password', 'remember_token', 'api_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'approved_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /* ---------------------------------------------------------------
     | Roles
     ---------------------------------------------------------------- */

    public function isStudent(): bool { return $this->role === 'student'; }
    public function isTeacher(): bool { return $this->role === 'teacher'; }
    public function isParent(): bool { return $this->role === 'parent'; }
    public function isAdmin(): bool { return $this->role === 'admin'; }

    /* ---------------------------------------------------------------
     | Approval
     |
     | Only teachers are ever held: they mark other people's children, so
     | somebody has to vouch for them first. Students and parents are approved
     | on creation, which keeps this one column meaningful for every role
     | instead of being a teacher-shaped special case.
     ---------------------------------------------------------------- */

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
    public function isApproved(): bool { return $this->status === 'approved'; }

    /** Cleared to use the part of the platform their role is for. */
    public function isActive(): bool
    {
        return ! $this->isTeacher() || $this->isApproved();
    }

    public function approve(?User $by = null): void
    {
        $this->forceFill([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $by?->id,
            'rejected_reason' => null,
        ])->save();
    }

    public function reject(?string $reason = null, ?User $by = null): void
    {
        $this->forceFill([
            'status' => 'rejected',
            'approved_at' => null,
            'approved_by' => $by?->id,
            'rejected_reason' => $reason,
        ])->save();
    }

    public function scopePendingTeachers($query)
    {
        return $query->where('role', 'teacher')->where('status', 'pending');
    }

    /** Where this account belongs after logging in. */
    public function homeRoute(): string
    {
        if ($this->isTeacher()) {
            // Tell us what you teach first: an admin cannot sensibly approve an
            // account that has not said what it is for.
            if (! $this->onboarded_at) {
                return route('teacher.setup');
            }

            // Then wait for a human. An honest holding page, rather than a
            // dashboard with no classes on it that merely looks broken.
            if (! $this->isApproved()) {
                return route('teacher.pending');
            }
        }

        return match ($this->role) {
            'teacher' => route('teacher.dashboard'),
            'parent' => route('parent.dashboard'),
            'admin' => route('admin.dashboard'),
            default => $this->onboarded_at ? route('learn.screen', 'dashboard') : route('onboarding'),
        };
    }

    /**
     * The account as every client sees it.
     *
     * One shape, used by the API, by the login response and by the injected
     * blob the offline app boots from. It lived in two controllers as a
     * private method and drifting copies of a profile shape is exactly how a
     * field ends up present on one screen and missing on another.
     */
    public function apiProfile(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'level' => $this->level,
            'locale' => $this->locale,
            'goal' => $this->goal,
            'dailyGoal' => $this->daily_goal,
            'onboarded' => (bool) $this->onboarded_at,
            'school' => $this->school?->name,
            'linkCode' => $this->linkCode(),
            'subjects' => $this->subjects()->pluck('slug'),
        ];
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->take(2)
            ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0] ?? $this->name;
    }

    /* ---------------------------------------------------------------
     | API access for the offline student app
     ---------------------------------------------------------------- */

    /**
     * Issue a device token. Deliberately not Sanctum: one column and one
     * middleware covers a single-device-per-student product, and swapping in
     * Sanctum later touches only this method and the middleware.
     */
    public function issueApiToken(): string
    {
        $plain = Str::random(64);
        $this->forceFill(['api_token' => hash('sha256', $plain)])->save();

        return $plain;
    }

    public static function findByApiToken(?string $plain): ?self
    {
        if (! $plain) {
            return null;
        }

        return static::where('api_token', hash('sha256', $plain))->first();
    }

    /* ---------------------------------------------------------------
     | Relations
     ---------------------------------------------------------------- */

    public function school(): BelongsTo { return $this->belongsTo(School::class); }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'enrollments')->withTimestamps();
    }

    public function state(): HasOne { return $this->hasOne(StudentState::class); }
    public function attempts(): HasMany { return $this->hasMany(Attempt::class)->latest('submitted_at'); }
    public function studySessions(): HasMany { return $this->hasMany(StudySession::class); }
    public function lessonCompletions(): HasMany { return $this->hasMany(LessonCompletion::class); }
    public function submissions(): HasMany { return $this->hasMany(Submission::class); }

    /** Classes this teacher runs. */
    public function classrooms(): HasMany { return $this->hasMany(Classroom::class, 'teacher_id'); }

    /**
     * What this teacher teaches, and at which levels.
     *
     * Replaces the users.subject_slug column, which held one string and which
     * nothing ever wrote to. A teacher takes several subjects and rarely at
     * every level, so the level rides on the pivot.
     */
    public function teachingSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'teacher_subject')
            ->withPivot('level')
            ->withTimestamps();
    }

    /**
     * Replace what this teacher teaches.
     *
     * @param  array<int, string[]>  $map  subject id => levels
     *
     * Not syncWithoutDetaching(): that pivot keys on the related id, so a
     * second level of the same subject overwrites the first instead of adding
     * a row. Teaching Maths at both SS1 and SS2 is two rows, and it is the
     * normal case rather than the exception.
     */
    public function syncTeaching(array $map): void
    {
        $now = now();
        $rows = [];

        foreach ($map as $subjectId => $levels) {
            foreach (array_unique((array) $levels) as $level) {
                if (! \App\Support\Levels::isValid($level)) {
                    continue;
                }

                $rows[] = [
                    'user_id' => $this->id,
                    'subject_id' => (int) $subjectId,
                    'level' => $level,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        \Illuminate\Support\Facades\DB::table('teacher_subject')
            ->where('user_id', $this->id)
            ->delete();

        if ($rows) {
            \Illuminate\Support\Facades\DB::table('teacher_subject')->insert($rows);
        }

        $this->unsetRelation('teachingSubjects');
    }

    /** The distinct subjects, with the levels collapsed onto each. */
    public function teachingSummary(): array
    {
        return $this->teachingSubjects
            ->groupBy('id')
            ->map(fn ($rows) => [
                'subject' => $rows->first(),
                'levels' => $rows->pluck('pivot.level')->unique()->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** Classes this student sits in. */
    public function enrolledClassrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_student')->withTimestamps();
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_links', 'parent_id', 'student_id')
            ->withPivot('report_day', 'verified_at')
            ->withTimestamps();
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_links', 'student_id', 'parent_id')
            ->withTimestamps();
    }

    /** The code a student reads out so a parent can link to them. */
    public function linkCode(): string
    {
        return Str::upper(Str::substr($this->name, 0, 3)) . '-' . str_pad((string) (($this->id * 37) % 900 + 100), 3, '0', STR_PAD_LEFT);
    }
}
