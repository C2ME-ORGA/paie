<?php

use App\Models\AdministratorInvitation;
use App\Models\User;
use App\Support\Rbac\RbacSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RbacSynchronizer::class)->synchronize();
});

/**
 * SQLite serializes writers. These tests demonstrate the application-level
 * guarantees and the refusal of a second administrator; they do not prove
 * InnoDB SELECT ... FOR UPDATE concurrency. See docs/security/ADMIN-BOOTSTRAP.md.
 */
test('only one acceptance succeeds for the same invitation', function () {
    $token = str_repeat('1', 64);
    $password = 'Correct-Horse-Battery-9!';

    $invitation = AdministratorInvitation::factory()
        ->withToken($token)
        ->create(['email' => 'first@example.com']);

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertRedirect(route('filament.admin.auth.login'));

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertNotFound();

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->role('administrator', 'web')->count())->toBe(1)
        ->and($invitation->fresh()->consumed_at)->not->toBeNull();
});

test('two distinct invitations cannot create two administrators', function () {
    $firstToken = str_repeat('2', 64);
    $secondToken = str_repeat('3', 64);
    $password = 'Correct-Horse-Battery-9!';

    $firstInvitation = AdministratorInvitation::factory()
        ->withToken($firstToken)
        ->create(['email' => 'first@example.com']);

    $secondInvitation = AdministratorInvitation::factory()
        ->withToken($secondToken)
        ->create(['email' => 'second@example.com']);

    expect($firstInvitation->isUsableAt())->toBeTrue()
        ->and($secondInvitation->isUsableAt())->toBeTrue();

    $this->post(route('administrator-invitations.accept', ['token' => $firstToken]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertRedirect(route('filament.admin.auth.login'));

    $this->post(route('administrator-invitations.accept', ['token' => $secondToken]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertNotFound();

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->role('administrator', 'web')->count())->toBe(1)
        ->and($firstInvitation->fresh()->consumed_at)->not->toBeNull()
        ->and($secondInvitation->fresh()->consumed_at)->toBeNull();
});
