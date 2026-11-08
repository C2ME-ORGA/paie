<?php

declare(strict_types=1);

use App\Contracts\AuditLogger;
use App\Enums\AuditEventType;
use App\Exceptions\AuditLoggingException;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\Support\Audit\ThrowingAuditLogger;

uses(RefreshDatabase::class);

function bindFailingAuditLogger(AuditEventType ...$events): ThrowingAuditLogger
{
    $logger = new ThrowingAuditLogger(array_values($events));

    app()->forgetScopedInstances();
    app()->instance(AuditLogger::class, $logger);

    return $logger;
}

test('a login that cannot be audited leaves no authenticated session', function () {
    $logger = bindFailingAuditLogger(AuditEventType::SecurityLoginSucceeded);

    Log::shouldReceive('channel')->once()->with('audit_fallback')->andReturnSelf();
    Log::shouldReceive('critical')->once();

    $user = User::factory()->create(['password' => Hash::make('password')]);

    try {
        Auth::attempt(['email' => $user->email, 'password' => 'password']);

        $this->fail('A failed audit write must abort the login.');
    } catch (ServiceUnavailableHttpException $exception) {
        expect($exception->getStatusCode())->toBe(503);
    }

    expect(Auth::check())->toBeFalse()
        ->and(session()->has('login_web_'.sha1(SessionGuard::class)))->toBeFalse()
        ->and($logger->recorded)->toBe([AuditEventType::SecurityLoginSucceeded->value]);
});

test('a logout completes even when the audit write fails', function () {
    $logger = bindFailingAuditLogger(AuditEventType::SecurityLogout);

    Log::shouldReceive('channel')->once()->with('audit_fallback')->andReturnSelf();
    Log::shouldReceive('critical')->once();

    $user = User::factory()->create();
    Auth::login($user);

    Auth::logout();

    expect(Auth::check())->toBeFalse()
        ->and($logger->recorded)->toBe([
            AuditEventType::SecurityLoginSucceeded->value,
            AuditEventType::SecurityLogout->value,
        ]);
});

test('a failed login remains a normal refusal when the audit write fails', function () {
    $logger = bindFailingAuditLogger(AuditEventType::SecurityLoginFailed);

    $password = 'wrong-password';
    $user = User::factory()->create(['password' => Hash::make('password')]);

    Log::shouldReceive('channel')->once()->with('audit_fallback')->andReturnSelf();
    Log::shouldReceive('critical')->once()->withArgs(
        /**
         * @param  array<string, mixed>  $context
         */
        static function (string $message, array $context) use ($user, $password): bool {
            $encoded = (string) json_encode($context);

            return $message === 'Audit journal write failed.'
                && $context['event'] === AuditEventType::SecurityLoginFailed->value
                && is_string($context['correlation_id'])
                && $context['exception'] === AuditLoggingException::class
                && ! str_contains($encoded, $user->email)
                && ! str_contains($encoded, $password);
        },
    );

    $result = Auth::attempt([
        'email' => $user->email,
        'password' => $password,
    ]);

    expect($result)->toBeFalse()
        ->and($logger->recorded)->toBe([AuditEventType::SecurityLoginFailed->value]);
});

test('an unknown account is not audited even when the journal is unavailable', function () {
    $logger = bindFailingAuditLogger(AuditEventType::SecurityLoginFailed);

    $result = Auth::attempt([
        'email' => 'unknown@example.test',
        'password' => 'wrong-password',
    ]);

    expect($result)->toBeFalse()
        ->and($logger->recorded)->toBe([]);
});

test('a failed login audit invalidates a pre-existing remember token', function () {
    bindFailingAuditLogger(AuditEventType::SecurityLoginSucceeded);

    Log::shouldReceive('channel')->once()->with('audit_fallback')->andReturnSelf();
    Log::shouldReceive('critical')->once();

    $user = User::factory()->create(['password' => Hash::make('password')]);
    $user->forceFill(['remember_token' => 'existing-remember-token'])->save();

    try {
        Auth::attempt(['email' => $user->email, 'password' => 'password']);
    } catch (ServiceUnavailableHttpException) {
        // Expected: the login is aborted because the audit write failed.
    }

    expect($user->fresh()->remember_token)->toBeNull();
});
