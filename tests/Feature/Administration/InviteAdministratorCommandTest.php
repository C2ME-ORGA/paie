<?php

use App\Enums\PermissionName;
use App\Models\AdministratorInvitation;
use App\Models\User;
use App\Notifications\AdministratorInvitationNotification;
use App\Support\Rbac\RbacSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    app(RbacSynchronizer::class)->synchronize();
});

test('the command does not expose password or token options', function () {
    $commands = Artisan::all();
    $command = $commands['batipaie:admin-invite'] ?? null;

    if ($command === null) {
        $this->fail('The administrator invitation command is not registered.');
    }

    $definition = $command->getDefinition();

    expect($definition->hasOption('password'))->toBeFalse()
        ->and($definition->hasOption('token'))->toBeFalse()
        ->and($definition->hasOption('name'))->toBeTrue()
        ->and($definition->hasOption('email'))->toBeTrue();
});

test('creates an invitation and sends the notification without creating a user', function () {
    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'ADMIN@Example.com',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and(User::query()->count())->toBe(0)
        ->and(AdministratorInvitation::query()->count())->toBe(1);

    $invitation = AdministratorInvitation::query()->sole();

    expect($invitation->name)->toBe('Alice Admin')
        ->and($invitation->email)->toBe('admin@example.com')
        ->and($invitation->token_hash)->toHaveLength(64)
        ->and($invitation->consumed_at)->toBeNull()
        ->and($invitation->revoked_at)->toBeNull();

    $sent = null;

    Notification::assertSentOnDemand(
        AdministratorInvitationNotification::class,
        function (AdministratorInvitationNotification $notification) use (&$sent): bool {
            $sent = $notification;

            return true;
        },
    );

    if (! $sent instanceof AdministratorInvitationNotification) {
        $this->fail('The administrator invitation notification was not sent.');
    }

    $token = basename((string) parse_url($sent->acceptanceUrl, PHP_URL_PATH));

    expect($token)->not->toBe('')
        ->and($invitation->token_hash)->toBe(AdministratorInvitation::hashToken($token))
        ->and($output)->not->toContain($token);

    $mail = $sent->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toContain('Invitation')
        ->and($mail->actionUrl)->toBe($sent->acceptanceUrl);

    Notification::assertSentOnDemandTimes(AdministratorInvitationNotification::class, 1);
});

test('refuses bootstrap when an administrator already exists', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');

    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Second Admin',
        '--email' => 'second@example.com',
    ]);

    expect($exitCode)->toBe(1)
        ->and(AdministratorInvitation::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(1);

    Notification::assertNothingSent();
});

test('refuses a second bootstrap while a valid invitation is pending', function () {
    $firstExitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    $secondExitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    expect($firstExitCode)->toBe(0)
        ->and($secondExitCode)->toBe(1)
        ->and(AdministratorInvitation::query()->count())->toBe(1)
        ->and(User::query()->count())->toBe(0);

    Notification::assertSentOnDemandTimes(AdministratorInvitationNotification::class, 1);
});

test('replaces an expired invitation after revoking it', function () {
    $expiredToken = str_repeat('a', 64);

    $expired = AdministratorInvitation::factory()
        ->withToken($expiredToken)
        ->expired()
        ->create();

    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    expect($exitCode)->toBe(0)
        ->and(AdministratorInvitation::query()->count())->toBe(2)
        ->and($expired->fresh()->revoked_at)->not->toBeNull()
        ->and($expired->fresh()->consumed_at)->toBeNull()
        ->and(AdministratorInvitation::query()->whereNull('revoked_at')->count())->toBe(1);

    Notification::assertSentOnDemandTimes(AdministratorInvitationNotification::class, 1);
});

test('fails closed when a consumed invitation exists without an administrator', function () {
    AdministratorInvitation::factory()->consumed()->create();

    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    expect($exitCode)->toBe(1)
        ->and(AdministratorInvitation::query()->count())->toBe(1)
        ->and(User::query()->count())->toBe(0);

    Notification::assertNothingSent();
});

test('refuses an invitation for an email already associated with a user', function () {
    User::factory()->create(['email' => 'admin@example.com']);

    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    expect($exitCode)->toBe(1)
        ->and(AdministratorInvitation::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(1);

    Notification::assertNothingSent();
});

test('refuses an insecure mailer in production', function () {
    app()['env'] = 'production';
    config(['mail.default' => 'log']);

    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    expect($exitCode)->toBe(1)
        ->and(AdministratorInvitation::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);

    Notification::assertNothingSent();
});

test('fails closed when the RBAC administrator role is missing', function () {
    Role::query()->delete();

    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    expect($exitCode)->toBe(1)
        ->and(AdministratorInvitation::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);

    Notification::assertNothingSent();
});

test('fails closed when the administrator role lacks the panel permission', function () {
    Role::findByName('administrator', 'web')
        ->revokePermissionTo(PermissionName::AdministrationPanelAccess->value);

    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    expect($exitCode)->toBe(1)
        ->and(AdministratorInvitation::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);

    Notification::assertNothingSent();
});
