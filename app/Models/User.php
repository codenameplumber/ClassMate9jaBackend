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

    protected $fillable = [
        'name', 'email', 'password', 'role', 'phone', 'school_id', 'level',
        'locale', 'goal', 'daily_goal', 'subject_slug', 'onboarded_at',
    ];

    protected $hidden = ['password', 'remember_token', 'api_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'last_seen_at' => 'datetime',
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

    /** Where this account belongs after logging in. */
    public function homeRoute(): string
    {
        return match ($this->role) {
            'teacher' => route('teacher.dashboard'),
            'parent' => route('parent.dashboard'),
            'admin' => route('admin.dashboard'),
            default => $this->onboarded_at ? '/app/student/dashboard.html' : route('onboarding'),
        };
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
