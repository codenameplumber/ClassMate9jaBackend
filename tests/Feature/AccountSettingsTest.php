<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Settings that actually stick.
 *
 * Every one of these covers a real regression. When identity moved from local
 * storage to the server, the screens kept writing to the device only — so a
 * student added a subject, saw it appear, and found it gone on the next page
 * load, because the page re-injects the server's account every time it
 * renders. A change that looks saved and is not is worse than one that fails.
 */
class AccountSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private function student(): User
    {
        return User::where('email', 'student@classmate9ja.ng')->firstOrFail();
    }

    private function token(): string
    {
        return $this->postJson('/api/login', [
            'email' => 'student@classmate9ja.ng',
            'password' => 'password',
        ])->json('token');
    }

    /* ------------------------------------------------------------ subjects */

    public function test_adding_a_subject_survives_a_reload(): void
    {
        $student = $this->student();
        $token = $this->token();

        $existing = $student->subjects()->pluck('slug')->all();
        $extra = Subject::whereNotIn('slug', $existing)->value('slug');

        $this->assertNotNull($extra, 'Every subject is already taken; nothing to add.');

        $this->withToken($token)
            ->patchJson('/api/me', ['subjects' => [...$existing, $extra]])
            ->assertOk();

        // The reload: a fresh read of the account, exactly as the page does.
        $reloaded = $this->withToken($token)->getJson('/api/me')->json('user.subjects');

        $this->assertContains($extra, $reloaded);
        $this->assertCount(count($existing) + 1, $reloaded);
    }

    public function test_removing_a_subject_survives_a_reload(): void
    {
        $token = $this->token();
        $keep = $this->student()->subjects()->pluck('slug')->take(2)->values()->all();

        $this->withToken($token)->patchJson('/api/me', ['subjects' => $keep])->assertOk();

        $this->assertSame(
            $keep,
            $this->withToken($token)->getJson('/api/me')->json('user.subjects')
        );
    }

    /* ------------------------------------------------------------- profile */

    public function test_name_level_and_daily_goal_persist(): void
    {
        $token = $this->token();

        $this->withToken($token)->patchJson('/api/me', [
            'name' => 'Chidera Renamed',
            'level' => 'SS3',
            'dailyGoal' => 60,
        ])->assertOk();

        $user = $this->withToken($token)->getJson('/api/me')->json('user');

        $this->assertSame('Chidera Renamed', $user['name']);
        $this->assertSame('SS3', $user['level']);
        $this->assertSame(60, $user['dailyGoal']);
    }

    /** The settings screen sends a school name; it used to be silently dropped. */
    public function test_the_school_can_be_set_by_name(): void
    {
        $token = $this->token();
        $name = 'Test Grammar School ' . Str::random(5);

        $this->withToken($token)->patchJson('/api/me', ['school' => $name])->assertOk();

        $this->assertSame($name, $this->withToken($token)->getJson('/api/me')->json('user.school'));
    }

    /** Three students typing the same school must not make three schools. */
    public function test_the_same_school_name_is_not_duplicated(): void
    {
        $name = 'Shared School ' . Str::random(5);

        $this->withToken($this->token())->patchJson('/api/me', ['school' => $name])->assertOk();

        $other = User::create([
            'name' => 'Other Student ' . Str::random(4),
            'email' => 'other-' . Str::random(8) . '@test.ng',
            'password' => 'password',
            'role' => 'student',
            'level' => 'SS2',
            'onboarded_at' => now(),
        ]);

        $this->withToken($other->issueApiToken())
            ->patchJson('/api/me', ['school' => $name])
            ->assertOk();

        $this->assertSame(1, School::where('name', $name)->count());
    }

    public function test_the_locale_the_tutor_speaks_persists(): void
    {
        $token = $this->token();

        $this->withToken($token)->patchJson('/api/me', ['locale' => 'pcm'])->assertOk();

        $this->assertSame('pcm', $this->withToken($token)->getJson('/api/me')->json('user.locale'));
    }

    public function test_a_level_that_is_not_a_level_is_refused(): void
    {
        $this->withToken($this->token())
            ->patchJson('/api/me', ['level' => 'SS9'])
            ->assertStatus(422);
    }

    public function test_a_subject_that_does_not_exist_is_refused(): void
    {
        $this->withToken($this->token())
            ->patchJson('/api/me', ['subjects' => ['not-a-subject']])
            ->assertStatus(422);
    }

    /* ------------------------------------------------------------ password */

    public function test_a_student_can_change_their_password(): void
    {
        $token = $this->token();

        $this->withToken($token)->postJson('/api/password', [
            'current' => 'password',
            'password' => 'a-much-better-one',
            'password_confirmation' => 'a-much-better-one',
        ])->assertOk();

        $this->assertTrue(Hash::check('a-much-better-one', $this->student()->fresh()->password));
    }

    /**
     * The old screen compared the current password against a copy in local
     * storage, which anyone could edit. The server checks it now.
     */
    public function test_the_current_password_must_be_right(): void
    {
        $this->withToken($this->token())->postJson('/api/password', [
            'current' => 'not-the-password',
            'password' => 'a-much-better-one',
            'password_confirmation' => 'a-much-better-one',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('password', $this->student()->fresh()->password));
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->withToken($this->token())->postJson('/api/password', [
            'current' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422);
    }

    /** Changing a password must not leave old device tokens working. */
    public function test_changing_the_password_rotates_the_device_token(): void
    {
        $old = $this->token();

        $new = $this->withToken($old)->postJson('/api/password', [
            'current' => 'password',
            'password' => 'a-much-better-one',
            'password_confirmation' => 'a-much-better-one',
        ])->json('token');

        $this->assertNotSame($old, $new);
        $this->withToken($old)->getJson('/api/me')->assertStatus(401);
        $this->withToken($new)->getJson('/api/me')->assertOk();
    }

    public function test_a_signed_out_device_cannot_change_a_password(): void
    {
        $this->postJson('/api/password', [
            'current' => 'password',
            'password' => 'a-much-better-one',
            'password_confirmation' => 'a-much-better-one',
        ])->assertStatus(401);
    }
}
