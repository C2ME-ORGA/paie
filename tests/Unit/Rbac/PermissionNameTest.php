<?php

use App\Enums\PermissionName;

test('defines unique, namespaced permission names', function () {
    $names = array_map(
        static fn (PermissionName $permission): string => $permission->value,
        PermissionName::cases(),
    );

    expect($names)->toBe([
        'administration.panel.access',
        'audit.view',
    ])
        ->and(array_unique($names))->toHaveCount(count($names));
});

test('does not use a Laravel ability name or a future business permission', function () {
    $laravelAbilities = [
        'view',
        'viewAny',
        'create',
        'update',
        'delete',
        'deleteAny',
        'restore',
        'restoreAny',
        'forceDelete',
        'forceDeleteAny',
        'replicate',
        'reorder',
    ];

    foreach (PermissionName::cases() as $permission) {
        expect($permission->value)
            ->not->toBeIn($laravelAbilities)
            ->not->toMatch('/^(employees|contracts|worktime|leave|sites|expenses|payroll|payslips|declarations|cibtp|documents|reports|portal|integrations)\./');
    }
});
