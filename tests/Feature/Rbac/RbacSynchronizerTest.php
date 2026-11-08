<?php

use App\Enums\PermissionName;
use App\Enums\RbacRole;
use App\Models\User;
use App\Support\Rbac\RbacSynchronizer;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RbacSynchronizer::class)->synchronize();
});

test('creates the seven roles and the active platform and audit permissions', function () {
    expect(Role::query()->where('guard_name', 'web')->count())->toBe(7)
        ->and(Permission::query()->where('guard_name', 'web')->count())->toBe(2)
        ->and(Permission::query()->pluck('name')->all())
        ->toBe([
            PermissionName::AdministrationPanelAccess->value,
            PermissionName::AuditView->value,
        ])
        ->and(Permission::query()
            ->where('name', PermissionName::AdministrationPanelAccess->value)
            ->where('guard_name', 'web')
            ->exists())->toBeTrue();

    $administrator = Role::findByName(RbacRole::Administrator->value, 'web');

    expect($administrator->hasPermissionTo(PermissionName::AdministrationPanelAccess->value))->toBeTrue()
        ->and($administrator->hasPermissionTo(PermissionName::AuditView->value))->toBeTrue();
});

test('grants the panel permission only to internal back-office roles', function () {
    $rolesWithPanelAccess = [
        RbacRole::Administrator,
        RbacRole::Management,
        RbacRole::PayrollManager,
        RbacRole::HrManager,
        RbacRole::SiteManager,
    ];

    foreach ($rolesWithPanelAccess as $role) {
        expect(Role::findByName($role->value, 'web')
            ->hasPermissionTo(PermissionName::AdministrationPanelAccess->value))->toBeTrue();
    }

    $rolesWithoutPermission = [
        RbacRole::Employee,
        RbacRole::ExternalAccountant,
    ];

    foreach ($rolesWithoutPermission as $role) {
        expect(Role::findByName($role->value, 'web')->permissions()->count())->toBe(0);
    }
});

test('can be run repeatedly without creating duplicates', function () {
    app(RbacSynchronizer::class)->synchronize();

    expect(Role::query()->count())->toBe(7)
        ->and(Permission::query()->count())->toBe(2)
        ->and(DB::table('role_has_permissions')->count())->toBe(6);
});

test('removes an extra permission from a managed role during synchronization', function () {
    $temporaryPermission = Permission::create([
        'name' => 'temporary.extra',
        'guard_name' => 'web',
    ]);

    $administrator = Role::findByName(RbacRole::Administrator->value, 'web');
    $administrator->givePermissionTo($temporaryPermission);

    app(RbacSynchronizer::class)->synchronize();

    expect($administrator->fresh()->permissions()->pluck('name')->all())
        ->toBe([
            PermissionName::AdministrationPanelAccess->value,
            PermissionName::AuditView->value,
        ]);
});

test('preserves roles and permissions that are not managed by the matrix', function () {
    $legacyRole = Role::create(['name' => 'legacy_role', 'guard_name' => 'web']);
    $legacyPermission = Permission::create(['name' => 'legacy.view', 'guard_name' => 'web']);
    $legacyRole->givePermissionTo($legacyPermission);

    app(RbacSynchronizer::class)->synchronize();

    expect(Role::query()->where('name', 'legacy_role')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'legacy.view')->exists())->toBeTrue()
        ->and($legacyRole->fresh()->hasPermissionTo('legacy.view'))->toBeTrue();
});

test('forgets the Spatie permission cache', function () {
    $cacheKey = (string) config('permission.cache.key');

    Cache::put($cacheKey, ['stale' => true], 3600);
    expect(Cache::has($cacheKey))->toBeTrue();

    app(RbacSynchronizer::class)->synchronize();

    expect(Cache::has($cacheKey))->toBeFalse();
});

test('never creates a user', function () {
    expect(User::query()->count())->toBe(0);
});

test('the RBAC seeder is idempotent and does not create a user', function () {
    $this->seed(RbacSeeder::class);

    expect(Role::query()->count())->toBe(7)
        ->and(Permission::query()->count())->toBe(2)
        ->and(User::query()->count())->toBe(0);
});

test('the database seeder seeds RBAC without creating an administrator', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Role::query()->count())->toBe(7)
        ->and(Permission::query()->count())->toBe(2)
        ->and(User::query()->count())->toBe(0);
});
