<?php

use App\Enums\PermissionName;
use App\Models\AdministratorInvitation;
use App\Models\User;
use App\Support\Rbac\RbacSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RbacSynchronizer::class)->synchronize();
});

test('stores only a hash and finds an invitation by its raw token', function () {
    $token = str_repeat('b', 64);
    $invitation = AdministratorInvitation::factory()
        ->withToken($token)
        ->create(['email' => 'admin@example.com']);

    expect($invitation->token_hash)->toBe(AdministratorInvitation::hashToken($token))
        ->and($invitation->token_hash)->not->toBe($token)
        ->and($invitation->toArray())->not->toHaveKey('token_hash')
        ->and(Schema::hasColumn('administrator_invitations', 'token'))->toBeFalse()
        ->and(AdministratorInvitation::findByToken($token)?->is($invitation))->toBeTrue()
        ->and(AdministratorInvitation::findByToken('invalid-token'))->toBeNull();
});

test('only accepts 64-character lowercase hexadecimal tokens in the route', function () {
    $this->get('/admin/invitation/'.str_repeat('A', 64))->assertNotFound();
    $this->get('/admin/invitation/'.str_repeat('g', 64))->assertNotFound();
    $this->get('/admin/invitation/short')->assertNotFound();
});

test('shows the acceptance form without consuming the invitation', function () {
    $token = str_repeat('c', 64);
    $invitation = AdministratorInvitation::factory()
        ->withToken($token)
        ->create();

    $response = $this->get(route('administrator-invitations.show', ['token' => $token]));

    $response->assertOk()
        ->assertSee('Créer le compte administrateur')
        ->assertSee('name="_token"', false)
        ->assertSee('method="POST"', false)
        ->assertSee(route('administrator-invitations.accept', ['token' => $token], false), false)
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($invitation->fresh()->consumed_at)->toBeNull();
});

test('registers distinct GET and POST routes for the invitation flow', function () {
    $show = app('router')->getRoutes()->getByName('administrator-invitations.show');
    $accept = app('router')->getRoutes()->getByName('administrator-invitations.accept');

    expect($show)->not->toBeNull()
        ->and($show?->methods())->toContain('GET')
        ->and($accept)->not->toBeNull()
        ->and($accept?->methods())->toContain('POST');
});

test('returns a generic not found page for invalid, expired, consumed or revoked invitations', function () {
    $token = str_repeat('d', 64);

    $this->get(route('administrator-invitations.show', ['token' => 'unknown-token']))
        ->assertNotFound();

    $expired = AdministratorInvitation::factory()
        ->withToken($token)
        ->expired()
        ->create();

    $this->get(route('administrator-invitations.show', ['token' => $token]))
        ->assertNotFound();

    $expired->forceFill(['consumed_at' => now()])->save();

    $this->get(route('administrator-invitations.show', ['token' => $token]))
        ->assertNotFound();

    $expired->forceFill([
        'consumed_at' => null,
        'revoked_at' => now(),
        'expires_at' => now()->addHour(),
    ])->save();

    $this->get(route('administrator-invitations.show', ['token' => $token]))
        ->assertNotFound();
});

test('creates the administrator account with the exclusive administrator role', function () {
    $token = str_repeat('e', 64);
    $password = 'Correct-Horse-Battery-9!';

    $invitation = AdministratorInvitation::factory()
        ->withToken($token)
        ->create([
            'name' => 'Alice Admin',
            'email' => 'admin@example.com',
        ]);

    $response = $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    $response->assertRedirect(route('filament.admin.auth.login'));

    expect(User::query()->count())->toBe(1);

    $user = User::query()->sole();

    expect($user->name)->toBe('Alice Admin')
        ->and($user->email)->toBe('admin@example.com')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check($password, $user->password))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe(['administrator'])
        ->and($user->roles()->where('guard_name', 'web')->exists())->toBeTrue()
        ->and($invitation->fresh()->consumed_at)->not->toBeNull();

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertNotFound();

    expect(User::query()->count())->toBe(1);
});

test('refuses acceptance when an administrator already exists', function () {
    $token = str_repeat('1', 64);
    $password = 'Correct-Horse-Battery-9!';
    $invitation = AdministratorInvitation::factory()
        ->withToken($token)
        ->create();

    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertNotFound();

    expect(User::query()->count())->toBe(1)
        ->and($invitation->fresh()->consumed_at)->toBeNull();
});

test('refuses an invitation whose email already belongs to a user without overwriting it', function () {
    $token = str_repeat('f', 64);
    $password = 'Correct-Horse-Battery-9!';
    $originalPassword = 'Original-Password-9!';

    $invitation = AdministratorInvitation::factory()
        ->withToken($token)
        ->create(['email' => 'admin@example.com']);

    $existing = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => $originalPassword,
    ]);

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(1)
        ->and(Hash::check($originalPassword, $existing->fresh()->password))->toBeTrue()
        ->and($existing->fresh()->hasRole('administrator'))->toBeFalse()
        ->and($invitation->fresh()->consumed_at)->toBeNull();
});

test('rejects a password that does not satisfy the administrator policy', function () {
    $token = str_repeat('2', 64);
    $invitation = AdministratorInvitation::factory()
        ->withToken($token)
        ->create();

    $response = $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors('password')
        ->assertSessionMissing('_old_input.password')
        ->assertSessionMissing('_old_input.password_confirmation');

    expect(User::query()->count())->toBe(0)
        ->and($invitation->fresh()->consumed_at)->toBeNull();
});

test('rejects invalid and revoked tokens on submission', function () {
    $password = 'Correct-Horse-Battery-9!';

    $this->post(route('administrator-invitations.accept', ['token' => 'unknown-token']), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertNotFound();

    $token = str_repeat('3', 64);
    $revoked = AdministratorInvitation::factory()
        ->withToken($token)
        ->revoked()
        ->create();

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertNotFound();

    expect(User::query()->count())->toBe(0)
        ->and($revoked->fresh()->consumed_at)->toBeNull();
});

test('rejects an expired invitation on submission', function () {
    $token = str_repeat('4', 64);
    $password = 'Correct-Horse-Battery-9!';
    $invitation = AdministratorInvitation::factory()
        ->withToken($token)
        ->expired()
        ->create();

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertNotFound();

    expect(User::query()->count())->toBe(0)
        ->and($invitation->fresh()->consumed_at)->toBeNull();
});

test('rate limits invitation submissions', function () {
    $token = str_repeat('5', 64);
    AdministratorInvitation::factory()->withToken($token)->create();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10']);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('administrator-invitations.accept', ['token' => $token]), [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(302);
    }

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertStatus(429);

    expect(User::query()->count())->toBe(0);
});

test('uses the web middleware group so CSRF protection applies', function () {
    $route = app('router')->getRoutes()->getByName('administrator-invitations.accept');

    expect($route)->not->toBeNull()
        ->and($route?->gatherMiddleware())->toContain('web');
});

test('fails closed if the administrator role lacks the panel permission during acceptance', function () {
    $token = str_repeat('6', 64);
    $password = 'Correct-Horse-Battery-9!';
    $invitation = AdministratorInvitation::factory()
        ->withToken($token)
        ->create();

    Role::findByName('administrator', 'web')
        ->revokePermissionTo(PermissionName::AdministrationPanelAccess->value);

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertStatus(503);

    expect(User::query()->count())->toBe(0)
        ->and($invitation->fresh()->consumed_at)->toBeNull();
});

test('fails closed if the administrator role is missing during acceptance', function () {
    $token = str_repeat('7', 64);
    $password = 'Correct-Horse-Battery-9!';
    $invitation = AdministratorInvitation::factory()
        ->withToken($token)
        ->create();

    Role::query()->delete();

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertStatus(503);

    expect(User::query()->count())->toBe(0)
        ->and($invitation->fresh()->consumed_at)->toBeNull();
});
