<?php

use App\Enums\RbacRole;

test('defines the seven canonical roles with stable codes', function () {
    $codes = array_map(
        static fn (RbacRole $role): string => $role->value,
        RbacRole::cases(),
    );

    expect($codes)->toBe([
        'administrator',
        'management',
        'payroll_manager',
        'hr_manager',
        'employee',
        'site_manager',
        'external_accountant',
    ]);
});

test('provides a French label for every role', function () {
    $labels = [];

    foreach (RbacRole::cases() as $role) {
        $labels[$role->value] = $role->label();
    }

    expect($labels)->toBe([
        'administrator' => 'Administrateur',
        'management' => 'Direction',
        'payroll_manager' => 'Comptable / Gestionnaire de paie',
        'hr_manager' => 'Responsable RH',
        'employee' => 'Salarié',
        'site_manager' => "Chef d'équipe / Responsable chantier",
        'external_accountant' => 'Expert-comptable externe',
    ]);
});
