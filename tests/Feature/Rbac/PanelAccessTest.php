<?php

use App\Enums\PermissionName;
use App\Enums\RbacRole;
use App\Models\User;
use App\Support\Rbac\RbacSynchronizer;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RbacSynchronizer::class)->synchronize();
});

test('guests are redirected to the Filament login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('an authenticated user without the panel permission is forbidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('denies access when the panel permission does not exist', function () {
    Permission::query()->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = User::factory()->create();

    expect($user->can(PermissionName::AdministrationPanelAccess->value))->toBeFalse();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('a user with the panel permission can access the panel', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(
        Permission::findByName(PermissionName::AdministrationPanelAccess->value, 'web'),
    );

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
});

test('the administrator role grants access through its panel permission', function () {
    $user = User::factory()->create();
    $user->assignRole(RbacRole::Administrator->value);

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
});

test('the internal back-office roles can access the panel', function () {
    $roles = [
        RbacRole::Management,
        RbacRole::PayrollManager,
        RbacRole::HrManager,
        RbacRole::SiteManager,
    ];

    foreach ($roles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }
});

test('the permission remains authoritative when it is removed from the administrator role', function () {
    $user = User::factory()->create();
    $user->assignRole(RbacRole::Administrator->value);

    Role::findByName(RbacRole::Administrator->value, 'web')
        ->revokePermissionTo(PermissionName::AdministrationPanelAccess->value);

    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect($user->can(PermissionName::AdministrationPanelAccess->value))->toBeFalse();

    $this->actingAs($user->refresh())
        ->get('/admin')
        ->assertForbidden();
});

test('the employee role does not grant panel access', function () {
    $user = User::factory()->create();
    $user->assignRole(RbacRole::Employee->value);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('the external accountant role receives no permission', function () {
    $user = User::factory()->create();
    $user->assignRole(RbacRole::ExternalAccountant->value);

    expect($user->getAllPermissions())->toBeEmpty()
        ->and($user->can(PermissionName::AdministrationPanelAccess->value))->toBeFalse();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('the administrator receives no implicit business permission', function () {
    Permission::create(['name' => 'payroll.view', 'guard_name' => 'web']);

    $administrator = User::factory()->create();
    $administrator->assignRole(RbacRole::Administrator->value);

    expect($administrator->can('payroll.view'))->toBeFalse()
        ->and($administrator->getAllPermissions()->pluck('name')->all())
        ->toBe([PermissionName::AdministrationPanelAccess->value]);
});

test('the user model implements the Filament contract', function () {
    expect(class_implements(User::class))
        ->toBeArray()
        ->toHaveKey(FilamentUser::class);
});
