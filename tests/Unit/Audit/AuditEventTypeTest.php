<?php

use App\Enums\AuditEventCategory;
use App\Enums\AuditEventType;

test('exposes stable values for known business events', function () {
    expect(AuditEventType::UserCreated->value)->toBe('user.created')
        ->and(AuditEventType::UserUpdated->value)->toBe('user.updated')
        ->and(AuditEventType::UserDeleted->value)->toBe('user.deleted')
        ->and(AuditEventType::AdministratorInvitationCreated->value)->toBe('administrator_invitation.created')
        ->and(AuditEventType::AdministratorInvitationConsumed->value)->toBe('administrator_invitation.consumed')
        ->and(AuditEventType::AdministratorInvitationRevoked->value)->toBe('administrator_invitation.revoked')
        ->and(AuditEventType::RbacRoleAssigned->value)->toBe('rbac.role_assigned')
        ->and(AuditEventType::RbacRoleRemoved->value)->toBe('rbac.role_removed');
});

test('accepts a known event value', function () {
    expect(AuditEventType::fromValue('user.created'))->toBe(AuditEventType::UserCreated)
        ->and(AuditEventType::fromValue(' user.created '))->toBe(AuditEventType::UserCreated);
});

test('rejects empty malformed or unknown event values', function (string $value) {
    expect(fn () => AuditEventType::fromValue($value))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'empty' => '',
    'spaces' => '   ',
    'uppercase' => 'User.Created',
    'without separator' => 'userCreated',
    'double separator' => 'user..created',
    'invalid characters' => 'user.created!',
    'unknown event' => 'payroll.closed',
]);

test('classifies business, RBAC/security and system events explicitly', function () {
    expect(AuditEventType::UserCreated->category())->toBe(AuditEventCategory::Business)
        ->and(AuditEventType::UserCreated->isBusiness())->toBeTrue()
        ->and(AuditEventType::UserCreated->isTechnical())->toBeFalse()
        ->and(AuditEventType::UserCreated->logName())->toBe('audit')
        ->and(AuditEventType::RbacPermissionAssigned->category())->toBe(AuditEventCategory::Security)
        ->and(AuditEventType::RbacPermissionAssigned->isBusiness())->toBeFalse()
        ->and(AuditEventType::RbacPermissionAssigned->isTechnical())->toBeTrue()
        ->and(AuditEventType::RbacPermissionAssigned->logName())->toBe('security')
        ->and(AuditEventType::SecurityAccessDenied->category())->toBe(AuditEventCategory::Security)
        ->and(AuditEventType::SecurityAccessDenied->isTechnical())->toBeTrue()
        ->and(AuditEventType::SecurityAccessDenied->logName())->toBe('security')
        ->and(AuditEventType::SystemCommandCompleted->category())->toBe(AuditEventCategory::System)
        ->and(AuditEventType::SystemCommandCompleted->isTechnical())->toBeTrue()
        ->and(AuditEventType::SystemCommandCompleted->logName())->toBe('system');
});

test('defines unique and well formed event values', function () {
    $values = array_map(
        static fn (AuditEventType $event): string => $event->value,
        AuditEventType::cases(),
    );

    expect($values)->toBe(array_values(array_unique($values)))
        ->and($values)->each->toMatch('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/');
});
