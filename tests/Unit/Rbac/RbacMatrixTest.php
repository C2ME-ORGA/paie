<?php

use App\Enums\PermissionName;
use App\Enums\RbacRole;
use App\Support\Rbac\RbacMatrix;

test('covers every role and only references canonical permissions', function () {
    expect(RbacMatrix::roles())->toHaveCount(7)
        ->and(RbacMatrix::allPermissions())->toBe(PermissionName::cases());

    foreach (RbacMatrix::roles() as $role) {
        foreach (RbacMatrix::permissionsFor($role) as $permission) {
            expect(RbacMatrix::allPermissions())->toContain($permission);
        }
    }
});

test('grants only the panel permission to the internal back-office roles', function () {
    $rolesWithPanelAccess = [
        RbacRole::Administrator,
        RbacRole::Management,
        RbacRole::PayrollManager,
        RbacRole::HrManager,
        RbacRole::SiteManager,
    ];

    foreach ($rolesWithPanelAccess as $role) {
        expect(RbacMatrix::permissionsFor($role))
            ->toBe([PermissionName::AdministrationPanelAccess]);
    }

    $rolesWithoutPermission = [
        RbacRole::Employee,
        RbacRole::ExternalAccountant,
    ];

    foreach ($rolesWithoutPermission as $role) {
        expect(RbacMatrix::permissionsFor($role))->toBe([]);
    }
});

test('does not reference the same permission twice for a role', function () {
    foreach (RbacMatrix::roles() as $role) {
        $permissionNames = array_map(
            static fn (PermissionName $permission): string => $permission->value,
            RbacMatrix::permissionsFor($role),
        );

        expect(array_unique($permissionNames))->toHaveCount(count($permissionNames));
    }
});
