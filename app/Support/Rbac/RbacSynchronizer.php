<?php

namespace App\Support\Rbac;

use App\Enums\PermissionName;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Synchronise le référentiel RBAC actif avec Spatie.
 *
 * The synchronizer is additive for roles and permissions: it never deletes
 * rows that are not part of the matrix. It does replace the permissions of
 * each managed role so the matrix remains the source of truth.
 */
final class RbacSynchronizer
{
    public const GUARD = 'web';

    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    public function synchronize(): void
    {
        try {
            DB::transaction(function (): void {
                $permissions = $this->ensurePermissions();

                foreach (RbacMatrix::roles() as $role) {
                    $roleModel = Role::query()->firstOrCreate([
                        'name' => $role->value,
                        'guard_name' => self::GUARD,
                    ]);

                    $roleModel->syncPermissions(
                        array_map(
                            static fn (PermissionName $permission): Permission => $permissions[$permission->value],
                            RbacMatrix::permissionsFor($role),
                        ),
                    );
                }
            });
        } finally {
            // Role and Permission model events already flush the cache, but an
            // explicit flush also covers an empty or unchanged matrix.
            $this->permissionRegistrar->forgetCachedPermissions();
        }
    }

    /**
     * @return array<string, Permission>
     */
    private function ensurePermissions(): array
    {
        $permissions = [];

        foreach (RbacMatrix::allPermissions() as $permission) {
            $permissions[$permission->value] = Permission::query()->firstOrCreate([
                'name' => $permission->value,
                'guard_name' => self::GUARD,
            ]);
        }

        return $permissions;
    }
}
