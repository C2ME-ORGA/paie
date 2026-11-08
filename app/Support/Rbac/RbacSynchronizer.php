<?php

namespace App\Support\Rbac;

use App\Enums\AuditEventType;
use App\Enums\PermissionName;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditManager;
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
        private readonly AuditManager $audit,
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

                    $desiredPermissions = RbacMatrix::permissionsFor($role);
                    $desiredPermissionNames = array_map(
                        static fn (PermissionName $permission): string => $permission->value,
                        $desiredPermissions,
                    );
                    $currentPermissionNames = $roleModel->permissions()->pluck('name')->all();
                    $addedPermissionNames = array_values(array_diff($desiredPermissionNames, $currentPermissionNames));
                    $removedPermissionNames = array_values(array_diff($currentPermissionNames, $desiredPermissionNames));

                    $roleModel->syncPermissions(
                        array_map(
                            static fn (PermissionName $permission): Permission => $permissions[$permission->value],
                            $desiredPermissions,
                        ),
                    );

                    $context = $this->auditContext();

                    foreach ($addedPermissionNames as $permissionName) {
                        $this->audit->record(
                            event: AuditEventType::RbacPermissionAssigned,
                            subject: $roleModel,
                            context: $this->permissionContext($context, $roleModel, $permissionName),
                        );
                    }

                    foreach ($removedPermissionNames as $permissionName) {
                        $this->audit->record(
                            event: AuditEventType::RbacPermissionRevoked,
                            subject: $roleModel,
                            context: $this->permissionContext($context, $roleModel, $permissionName),
                        );
                    }
                }
            });
        } finally {
            // Role and Permission model events already flush the cache, but an
            // explicit flush also covers an empty or unchanged matrix.
            $this->permissionRegistrar->forgetCachedPermissions();
        }
    }

    private function auditContext(): AuditContext
    {
        $context = $this->audit->currentContext();
        $metadata = [
            'channel' => 'rbac_synchronization',
            'reason' => 'Synchronisation du référentiel RBAC',
        ];

        // Preserve an explicit source supplied by a job/automated context.
        if (! array_key_exists('source', $context->metadata)) {
            $metadata['source'] = app()->runningInConsole() ? 'console' : 'http';
        }

        return $context->withMetadata($metadata);
    }

    private function permissionContext(
        AuditContext $context,
        Role $role,
        string $permissionName,
    ): AuditContext {
        return $context->withMetadata([
            'role' => $role->name,
            'permission' => $permissionName,
            'guard' => self::GUARD,
        ]);
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
