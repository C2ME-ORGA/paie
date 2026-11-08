<?php

declare(strict_types=1);

use App\Enums\AuditEventType;
use App\Enums\RbacRole;
use App\Models\User;
use App\Support\Rbac\RbacSynchronizer;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\Support\Audit\ThrowingAuditLogger;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['filament-activity-log.integrity.key' => 'test-only-integrity-key']);

    app(RbacSynchronizer::class)->synchronize();

    Filament::setCurrentPanel('admin');
});

function panelLoginUser(): User
{
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $user->assignRole(RbacRole::Administrator->value);

    return $user;
}

/**
 * @return array{0: string, 1: string}
 */
function loginRecaller(User $user): array
{
    $guard = Auth::guard('web');
    $name = $guard->getRecallerName();
    $value = $user->getAuthIdentifier()
        .'|'.$user->getRememberToken()
        .'|'.$guard->hashPasswordForCookie($user->getAuthPassword());

    return [$name, $value];
}

function submitFilamentLogin(User $user, string $password, bool $remember): mixed
{
    return Livewire::test(Login::class)
        ->fillForm([
            'email' => $user->email,
            'password' => $password,
            'remember' => $remember,
        ], 'form')
        ->call('authenticate');
}

test('a Filament login that cannot be audited returns 503 without an authenticated session', function () {
    ThrowingAuditLogger::bind(AuditEventType::SecurityLoginSucceeded);

    Log::shouldReceive('channel')->once()->with('audit_fallback')->andReturnSelf();
    Log::shouldReceive('critical')->once();

    $user = panelLoginUser();

    $component = submitFilamentLogin($user, 'password', false);

    $component->assertStatus(503)
        ->assertDontSee('AuditLoggingException')
        ->assertDontSee('activity_log')
        ->assertDontSee('password');

    expect(Auth::check())->toBeFalse()
        ->and(session()->has('login_web_'.sha1(SessionGuard::class)))->toBeFalse();
});

test('a Filament login with remember me leaves no valid recaller when the audit fails', function () {
    ThrowingAuditLogger::bind(AuditEventType::SecurityLoginSucceeded);

    Log::shouldReceive('channel')->once()->with('audit_fallback')->andReturnSelf();
    Log::shouldReceive('critical')->once();

    $user = panelLoginUser();

    $component = submitFilamentLogin($user, 'password', true);

    $component->assertStatus(503)
        ->assertCookieExpired('remember_web_'.sha1(SessionGuard::class));

    expect($user->fresh()->remember_token)->toBeNull()
        ->and(Auth::check())->toBeFalse();
});

test('a recaller cookie authenticates a panel user before the audit failure', function () {
    $user = panelLoginUser();
    $user->forceFill(['remember_token' => 'pre-existing-remember-token'])->save();

    [$recallerName, $recallerValue] = loginRecaller($user);

    Auth::forgetGuards();

    $this->withCookie($recallerName, $recallerValue)
        ->get('/admin')
        ->assertOk();
});

test('a pre-existing recaller no longer authenticates after a failed login audit', function () {
    $user = panelLoginUser();
    $user->forceFill(['remember_token' => 'pre-existing-remember-token'])->save();

    [$recallerName, $recallerValue] = loginRecaller($user);

    ThrowingAuditLogger::bind(AuditEventType::SecurityLoginSucceeded);

    Log::shouldReceive('channel')->once()->with('audit_fallback')->andReturnSelf();
    Log::shouldReceive('critical')->once();

    submitFilamentLogin($user, 'password', false)->assertStatus(503);

    expect($user->fresh()->remember_token)->toBeNull()
        ->and(Auth::check())->toBeFalse();

    Auth::forgetGuards();

    $this->withCookie($recallerName, $recallerValue)
        ->get('/admin')
        ->assertRedirect('/admin/login');
});

test('a normal Filament login remains unchanged when the audit succeeds', function () {
    $user = panelLoginUser();

    $component = submitFilamentLogin($user, 'password', false);

    $component->assertHasNoErrors();

    expect(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($user->getKey());
});
