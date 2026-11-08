<?php

use App\Enums\AuditEventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['filament-activity-log.integrity.key' => 'test-only-integrity-key']);
});

test('records a successful login with the authenticated user as actor', function () {
    $password = 'Login-Secret-Password-9!';
    $user = User::factory()->create([
        'password' => Hash::make($password),
    ]);

    expect(Auth::attempt([
        'email' => $user->email,
        'password' => $password,
    ]))->toBeTrue();

    $activity = Activity::query()
        ->where('event', AuditEventType::SecurityLoginSucceeded->value)
        ->sole();

    expect($activity->log_name)->toBe('security')
        ->and($activity->subject_id)->toBe($user->getKey())
        ->and($activity->causer_id)->toBe($user->getKey())
        ->and($activity->properties->toArray())->toMatchArray([
            'source' => 'auth',
            'channel' => 'session',
            'guard' => 'web',
        ])
        ->and(json_encode($activity->getAttributes()))->not->toContain($password);
});

test('records a logout with the user who initiated it', function () {
    $user = User::factory()->create();

    Auth::attempt([
        'email' => $user->email,
        'password' => 'password',
    ]);

    Auth::logout();

    $activity = Activity::query()
        ->where('event', AuditEventType::SecurityLogout->value)
        ->sole();

    expect($activity->subject_id)->toBe($user->getKey())
        ->and($activity->causer_id)->toBe($user->getKey())
        ->and($activity->properties->get('guard'))->toBe('web');
});

test('records a failed login for a known account without credentials', function () {
    $user = User::factory()->create();
    $wrongPassword = 'Wrong-Secret-Password-9!';

    expect(Auth::attempt([
        'email' => $user->email,
        'password' => $wrongPassword,
    ]))->toBeFalse();

    $activity = Activity::query()
        ->where('event', AuditEventType::SecurityLoginFailed->value)
        ->sole();

    expect($activity->subject_id)->toBe($user->getKey())
        ->and($activity->causer_id)->toBeNull()
        ->and($activity->properties->get('source'))->toBe('auth')
        ->and($activity->properties->get('guard'))->toBe('web')
        ->and(json_encode($activity->getAttributes()))->not->toContain($wrongPassword)
        ->and(json_encode($activity->getAttributes()))->not->toContain($user->email);
});

test('does not record a failed login for an unknown account', function () {
    expect(Auth::attempt([
        'email' => 'unknown@example.test',
        'password' => 'Wrong-Secret-Password-9!',
    ]))->toBeFalse();

    expect(Activity::query()
        ->where('event', AuditEventType::SecurityLoginFailed->value)
        ->count())->toBe(0);
});

test('records exactly one authentication event per attempt', function () {
    $user = User::factory()->create();

    Auth::attempt([
        'email' => $user->email,
        'password' => 'password',
    ]);

    Auth::logout();

    expect(Activity::query()
        ->where('event', AuditEventType::SecurityLoginSucceeded->value)
        ->count())->toBe(1)
        ->and(Activity::query()
            ->where('event', AuditEventType::SecurityLogout->value)
            ->count())->toBe(1);
});
