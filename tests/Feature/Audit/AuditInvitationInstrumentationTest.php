<?php

use App\Contracts\AuditLogger;
use App\Enums\AuditEventType;
use App\Models\AdministratorInvitation;
use App\Models\User;
use App\Support\Rbac\RbacSynchronizer;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Audit\ThrowingAuditLogger;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['filament-activity-log.integrity.key' => 'test-only-integrity-key']);
    Notification::fake();
    app(RbacSynchronizer::class)->synchronize();
});

test('records an invitation creation from the console command without the token hash', function () {
    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    expect($exitCode)->toBe(0);

    $invitation = AdministratorInvitation::query()->sole();
    $activity = Activity::query()
        ->where('event', AuditEventType::AdministratorInvitationCreated->value)
        ->sole();
    $changes = $activity->attribute_changes->toArray();
    $properties = $activity->properties->toArray();

    expect($activity->log_name)->toBe('audit')
        ->and($activity->subject_id)->toBe($invitation->getKey())
        ->and($activity->causer_id)->toBeNull()
        ->and($properties)->toMatchArray([
            'source' => 'console',
            'channel' => 'administrator_invitation',
            'command' => 'batipaie:admin-invite',
        ])
        ->and($changes['attributes'])->toHaveKeys(['name', 'email', 'expires_at'])
        ->and($changes['attributes'])->not->toHaveKey('token_hash')
        ->and(json_encode($activity->getAttributes()))->not->toContain($invitation->token_hash)
        ->and(json_encode($changes))->not->toContain($invitation->token_hash);
});

test('records user creation, role assignment and invitation consumption once', function () {
    $token = str_repeat('a', 64);
    $password = 'Correct-Horse-Battery-9!';

    $invitation = AdministratorInvitation::factory()
        ->withToken($token)
        ->create([
            'name' => 'Alice Admin',
            'email' => 'admin@example.com',
        ]);

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertRedirect(route('filament.admin.auth.login'));

    $user = User::query()->sole();

    $activities = Activity::query()
        ->whereIn('event', [
            AuditEventType::UserCreated->value,
            AuditEventType::RbacRoleAssigned->value,
            AuditEventType::AdministratorInvitationConsumed->value,
        ])
        ->get()
        ->keyBy('event');

    expect($activities)->toHaveCount(3);

    $userCreated = $activities[AuditEventType::UserCreated->value];
    $roleAssigned = $activities[AuditEventType::RbacRoleAssigned->value];
    $invitationConsumed = $activities[AuditEventType::AdministratorInvitationConsumed->value];

    $userChanges = $userCreated->attribute_changes->toArray();
    $userProperties = $userCreated->properties->toArray();

    expect($userCreated->log_name)->toBe('audit')
        ->and($userCreated->event)->toBe(AuditEventType::UserCreated->value)
        ->and($userCreated->subject_id)->toBe($user->getKey())
        ->and($userCreated->causer_type)->toBeNull()
        ->and($userCreated->causer_id)->toBeNull()
        ->and($userChanges['old'])->toBe([])
        ->and($userChanges['attributes'])->toHaveKeys(['name', 'email', 'email_verified_at'])
        ->and($userChanges['attributes']['name'])->toBe('Alice Admin')
        ->and($userChanges['attributes']['email'])->toBe('admin@example.com')
        ->and($userChanges['attributes'])->not->toHaveKey('password')
        ->and($userProperties)->toMatchArray([
            'origin' => 'system',
            'source' => 'http',
            'channel' => 'administrator_invitation',
            'initiated_by' => 'invitation_recipient',
        ])
        ->and($userProperties)->not->toHaveKeys([
            'password',
            'password_confirmation',
            'remember_token',
            'token',
            'token_hash',
            'secret',
        ])
        ->and($roleAssigned->subject_id)->toBe($user->getKey())
        ->and($roleAssigned->causer_id)->toBeNull()
        ->and($roleAssigned->properties->get('role'))->toBe('administrator')
        ->and($roleAssigned->properties->get('guard'))->toBe('web')
        ->and($invitationConsumed->subject_id)->toBe($invitation->getKey())
        ->and($invitationConsumed->attribute_changes->toArray()['old'])->toBe(['consumed_at' => null])
        ->and($invitationConsumed->attribute_changes->toArray()['attributes']['consumed_at'])->not->toBeNull();

    $correlations = $activities
        ->map(static fn (Activity $activity): mixed => $activity->properties->get('correlation_id'))
        ->unique();

    expect($correlations)->toHaveCount(1)
        ->and($userCreated->properties->get('initiated_by'))->toBe('invitation_recipient')
        ->and($roleAssigned->properties->get('initiated_by'))->toBe('invitation_recipient')
        ->and($invitationConsumed->properties->get('initiated_by'))->toBe('invitation_recipient');

    $serialized = $activities
        ->map(static fn (Activity $activity): array => $activity->getAttributes())
        ->toJson();

    expect($serialized)->not->toContain($password)
        ->and($serialized)->not->toContain($invitation->token_hash);

    expect(Activity::query()
        ->where('event', AuditEventType::UserCreated->value)
        ->count())->toBe(1);

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertNotFound();

    expect(Activity::query()
        ->whereIn('event', [
            AuditEventType::UserCreated->value,
            AuditEventType::RbacRoleAssigned->value,
            AuditEventType::AdministratorInvitationConsumed->value,
        ])
        ->count())->toBe(3);
});

test('records no audit event when invitation acceptance fails validation', function () {
    $token = str_repeat('b', 64);

    AdministratorInvitation::factory()
        ->withToken($token)
        ->create();

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    expect(User::query()->count())->toBe(0)
        ->and(Activity::query()
            ->whereIn('event', [
                AuditEventType::UserCreated->value,
                AuditEventType::RbacRoleAssigned->value,
                AuditEventType::AdministratorInvitationConsumed->value,
            ])
            ->count())->toBe(0);
});

test('records the revocation of an expired pending invitation before creating a new one', function () {
    $expired = AdministratorInvitation::factory()->expired()->create();

    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    expect($exitCode)->toBe(0);

    $created = Activity::query()
        ->where('event', AuditEventType::AdministratorInvitationCreated->value)
        ->sole();
    $revoked = Activity::query()
        ->where('event', AuditEventType::AdministratorInvitationRevoked->value)
        ->sole();

    expect($revoked->subject_id)->toBe($expired->getKey())
        ->and($revoked->causer_id)->toBeNull()
        ->and($revoked->attribute_changes->toArray()['old'])->toBe(['revoked_at' => null])
        ->and($revoked->attribute_changes->toArray()['attributes']['revoked_at'])->not->toBeNull()
        ->and($revoked->properties->get('reason'))->toContain('invitation en attente')
        ->and($created->subject_id)->not->toBe($expired->getKey());
});

test('rolls back pending revocations and their audit events when the command fails', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole('administrator');

    $expired = AdministratorInvitation::factory()->expired()->create();

    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    expect($exitCode)->toBe(1)
        ->and($expired->fresh()->revoked_at)->toBeNull()
        ->and(Activity::query()
            ->where('event', AuditEventType::AdministratorInvitationRevoked->value)
            ->count())->toBe(0)
        ->and(Activity::query()
            ->where('event', AuditEventType::AdministratorInvitationCreated->value)
            ->count())->toBe(0);
});

test('records the revocation caused by a mail delivery failure with the same correlation', function () {
    $this->app->instance(Dispatcher::class, new class implements Dispatcher
    {
        public function send($notifiables, $notification): void
        {
            throw new RuntimeException('SMTP transport failure');
        }

        /**
         * @param  array<int, string>|null  $channels
         */
        public function sendNow($notifiables, $notification, ?array $channels = null): void
        {
            throw new RuntimeException('SMTP transport failure');
        }
    });

    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    expect($exitCode)->toBe(1);

    $invitation = AdministratorInvitation::query()->sole();
    $created = Activity::query()
        ->where('event', AuditEventType::AdministratorInvitationCreated->value)
        ->sole();
    $deliveryFailed = Activity::query()
        ->where('event', AuditEventType::AdministratorInvitationDeliveryFailed->value)
        ->sole();
    $revoked = Activity::query()
        ->where('event', AuditEventType::AdministratorInvitationRevoked->value)
        ->sole();

    expect($created->subject_id)->toBe($invitation->getKey())
        ->and($deliveryFailed->subject_id)->toBe($invitation->getKey())
        ->and($revoked->subject_id)->toBe($invitation->getKey())
        ->and($deliveryFailed->properties->get('reason'))->toContain('Échec d’envoi')
        ->and($revoked->properties->get('reason'))->toContain('Révocation après échec')
        ->and($deliveryFailed->properties->get('correlation_id'))
        ->toBe($created->properties->get('correlation_id'))
        ->and($revoked->properties->get('correlation_id'))
        ->toBe($created->properties->get('correlation_id'))
        ->and(json_encode($revoked->getAttributes()))->not->toContain($invitation->token_hash);
});

test('keeps the invitation revoked when the delivery failure audit cannot be written', function () {
    $logger = new ThrowingAuditLogger([AuditEventType::AdministratorInvitationDeliveryFailed]);
    app()->forgetScopedInstances();
    app()->instance(AuditLogger::class, $logger);

    Log::shouldReceive('channel')->once()->with('audit_fallback')->andReturnSelf();
    Log::shouldReceive('critical')->once();
    Log::shouldReceive('error')->zeroOrMoreTimes();

    $this->app->instance(Dispatcher::class, new class implements Dispatcher
    {
        public function send($notifiables, $notification): void
        {
            throw new RuntimeException('SMTP transport failure');
        }

        /**
         * @param  array<int, string>|null  $channels
         */
        public function sendNow($notifiables, $notification, ?array $channels = null): void
        {
            throw new RuntimeException('SMTP transport failure');
        }
    });

    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    $invitation = AdministratorInvitation::query()->sole();

    expect($exitCode)->toBe(1)
        ->and($invitation->revoked_at)->not->toBeNull()
        ->and($logger->recorded)->toBe([
            AuditEventType::AdministratorInvitationCreated->value,
            AuditEventType::AdministratorInvitationDeliveryFailed->value,
        ])
        ->and(Activity::query()
            ->where('event', AuditEventType::AdministratorInvitationDeliveryFailed->value)
            ->count())->toBe(0);
});
