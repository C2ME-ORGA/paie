<?php

use App\Enums\AuditEventType;
use App\Models\AdministratorInvitation;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditManager;
use App\Support\Rbac\RbacSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['filament-activity-log.integrity.key' => 'test-only-integrity-key']);
});

test('records effective permission assignments during the first RBAC synchronization', function () {
    app(RbacSynchronizer::class)->synchronize();

    $events = Activity::query()
        ->where('event', AuditEventType::RbacPermissionAssigned->value)
        ->get();

    expect($events)->toHaveCount(6)
        ->and($events->every(
            static fn (Activity $activity): bool => $activity->log_name === 'security'
                && $activity->causer_id === null
                && in_array($activity->properties->get('permission'), [
                    'administration.panel.access',
                    'audit.view',
                ], true)
                && $activity->properties->get('guard') === 'web'
                && is_string($activity->properties->get('role')),
        ))->toBeTrue()
        ->and($events->every(
            static fn (Activity $activity): bool => $activity->properties->get('origin') === 'system'
                && ! $activity->properties->has('email')
                && ! $activity->properties->has('token_hash')
                && ! $activity->properties->has('password'),
        ))->toBeTrue()
        ->and($events->unique(
            static fn (Activity $activity): string => $activity->properties->get('role')
                .'|'.$activity->properties->get('permission'),
        ))->toHaveCount(6)
        ->and($events->map(
            static fn (Activity $activity): mixed => $activity->properties->get('correlation_id'),
        )->unique())->toHaveCount(1);
});

test('does not record anything when a new synchronization has no effect', function () {
    app(RbacSynchronizer::class)->synchronize();

    $before = Activity::query()
        ->whereIn('event', [
            AuditEventType::RbacPermissionAssigned->value,
            AuditEventType::RbacPermissionRevoked->value,
        ])
        ->count();

    app(RbacSynchronizer::class)->synchronize();

    $after = Activity::query()
        ->whereIn('event', [
            AuditEventType::RbacPermissionAssigned->value,
            AuditEventType::RbacPermissionRevoked->value,
        ])
        ->count();

    expect($before)->toBe(6)
        ->and($after)->toBe($before);
});

test('records the removal of an extra permission from a managed role', function () {
    app(RbacSynchronizer::class)->synchronize();

    $temporaryPermission = Permission::create([
        'name' => 'temporary.extra',
        'guard_name' => 'web',
    ]);

    $administrator = Role::findByName('administrator', 'web');
    $administrator->givePermissionTo($temporaryPermission);

    app(RbacSynchronizer::class)->synchronize();

    $activity = Activity::query()
        ->where('event', AuditEventType::RbacPermissionRevoked->value)
        ->sole();

    expect($activity->subject_id)->toBe($administrator->getKey())
        ->and($activity->properties->get('role'))->toBe('administrator')
        ->and($activity->properties->get('permission'))->toBe('temporary.extra')
        ->and($activity->properties->get('guard'))->toBe('web')
        ->and($activity->causer_id)->toBeNull();
});

test('records a role removal through the audit API', function () {
    app(RbacSynchronizer::class)->synchronize();

    $user = User::factory()->create();
    $role = Role::findByName('administrator', 'web');
    $user->assignRole($role);
    $user->removeRole($role);

    $result = app(AuditManager::class)->record(
        event: AuditEventType::RbacRoleRemoved,
        subject: $user,
        context: AuditContext::system(
            correlationId: 'role-removal',
            reason: 'Retrait du rôle administrateur',
            metadata: [
                'role' => 'administrator',
                'guard' => 'web',
            ],
        ),
    );

    $activity = Activity::query()->where('id', $result->activityId)->firstOrFail();

    expect($activity->event)->toBe(AuditEventType::RbacRoleRemoved->value)
        ->and($activity->subject_id)->toBe($user->getKey())
        ->and($activity->properties->get('role'))->toBe('administrator')
        ->and($activity->properties->get('guard'))->toBe('web')
        ->and($activity->causer_id)->toBeNull();
});

test('preserves an automated context and its source during RBAC synchronization', function () {
    $manager = app(AuditManager::class);

    $manager->withContext(
        AuditContext::automated(
            correlationId: 'automated-rbac',
            metadata: [
                'source' => 'queue',
                'job' => 'TestRbacSynchronizationJob',
            ],
        ),
        fn () => app(RbacSynchronizer::class)->synchronize(),
    );

    $events = Activity::query()
        ->where('event', AuditEventType::RbacPermissionAssigned->value)
        ->get();

    expect($events)->toHaveCount(6)
        ->and($events->every(
            static fn (Activity $activity): bool => $activity->causer_id === null
                && $activity->properties->get('origin') === 'automated'
                && $activity->properties->get('source') === 'queue'
                && $activity->properties->get('job') === 'TestRbacSynchronizationJob',
        ))->toBeTrue();
});

test('does not rely on Spatie permission events and therefore cannot duplicate them', function () {
    expect(config('permission.events_enabled'))->toBeFalse();
});

test('records exactly one role assignment through the invitation acceptance entry point', function () {
    app(RbacSynchronizer::class)->synchronize();
    Notification::fake();

    $token = str_repeat('c', 64);
    $password = 'Correct-Horse-Battery-9!';
    $email = 'rbac-entry@example.com';

    AdministratorInvitation::factory()
        ->withToken($token)
        ->create([
            'name' => 'Rbac Entry',
            'email' => $email,
        ]);

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertRedirect(route('filament.admin.auth.login'));

    $user = User::query()->sole();
    $activity = Activity::query()
        ->where('event', AuditEventType::RbacRoleAssigned->value)
        ->sole();

    expect($activity->log_name)->toBe('security')
        ->and($activity->subject_type)->toBe($user->getMorphClass())
        ->and($activity->subject_id)->toBe($user->getKey())
        ->and($activity->causer_id)->toBeNull()
        ->and($activity->attribute_changes->toArray())->toBe(['old' => [], 'attributes' => []])
        ->and($activity->properties->get('role'))->toBe('administrator')
        ->and($activity->properties->get('guard'))->toBe('web')
        ->and($activity->properties->get('origin'))->toBe('system')
        ->and($activity->properties->get('source'))->toBe('http')
        ->and($activity->properties->has('email'))->toBeFalse()
        ->and(json_encode($activity->getAttributes()))->not->toContain($email)
        ->and(json_encode($activity->getAttributes()))->not->toContain($password);

    $this->post(route('administrator-invitations.accept', ['token' => $token]), [
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertNotFound();

    expect(Activity::query()
        ->where('event', AuditEventType::RbacRoleAssigned->value)
        ->count())->toBe(1);
});

test('records a real permission assignment when a managed role regains it', function () {
    $synchronizer = app(RbacSynchronizer::class);
    $synchronizer->synchronize();

    $administrator = Role::findByName('administrator', 'web');
    $auditView = Permission::findByName('audit.view', 'web');
    $administrator->revokePermissionTo($auditView);

    expect(Activity::query()
        ->where('event', AuditEventType::RbacPermissionAssigned->value)
        ->count())->toBe(6);

    $synchronizer->synchronize();

    $activity = Activity::query()
        ->where('event', AuditEventType::RbacPermissionAssigned->value)
        ->orderByDesc('id')
        ->firstOrFail();

    expect($activity->properties->get('role'))->toBe('administrator')
        ->and($activity->properties->get('permission'))->toBe('audit.view')
        ->and($activity->properties->get('guard'))->toBe('web')
        ->and($activity->causer_id)->toBeNull()
        ->and($activity->attribute_changes->toArray())->toBe(['old' => [], 'attributes' => []]);

    expect(Activity::query()
        ->where('event', AuditEventType::RbacPermissionAssigned->value)
        ->count())->toBe(7);
});

test('rolls back RBAC audit events with the synchronization transaction', function () {
    try {
        DB::transaction(function (): void {
            app(RbacSynchronizer::class)->synchronize();

            throw new RuntimeException('force transaction rollback');
        });
    } catch (RuntimeException) {
        // Expected: the whole synchronization is cancelled.
    }

    expect(Activity::query()
        ->whereIn('event', [
            AuditEventType::RbacPermissionAssigned->value,
            AuditEventType::RbacPermissionRevoked->value,
        ])
        ->count())->toBe(0)
        ->and(Role::query()->count())->toBe(0)
        ->and(Permission::query()->count())->toBe(0);
});
