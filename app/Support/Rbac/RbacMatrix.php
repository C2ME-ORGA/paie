<?php

namespace App\Support\Rbac;

use App\Enums\PermissionName;
use App\Enums\RbacRole;

/**
 * Single source of truth for the role-to-permission matrix.
 *
 * The matrix only references permissions that are actually active. A role
 * without active permission is intentionally kept with an empty list: this
 * makes the least-privilege decision explicit and prevents accidental grants.
 */
final class RbacMatrix
{
    /**
     * @var array<string, list<PermissionName>>
     */
    private const MATRIX = [
        RbacRole::Administrator->value => [
            PermissionName::AdministrationPanelAccess,
            PermissionName::AuditView,
        ],
        RbacRole::Management->value => [
            PermissionName::AdministrationPanelAccess,
        ],
        RbacRole::PayrollManager->value => [
            PermissionName::AdministrationPanelAccess,
        ],
        RbacRole::HrManager->value => [
            PermissionName::AdministrationPanelAccess,
        ],
        RbacRole::Employee->value => [],
        RbacRole::SiteManager->value => [
            PermissionName::AdministrationPanelAccess,
        ],
        RbacRole::ExternalAccountant->value => [],
    ];

    /**
     * @return list<RbacRole>
     */
    public static function roles(): array
    {
        return RbacRole::cases();
    }

    /**
     * @return list<PermissionName>
     */
    public static function allPermissions(): array
    {
        return PermissionName::cases();
    }

    /**
     * @return list<PermissionName>
     */
    public static function permissionsFor(RbacRole $role): array
    {
        return self::MATRIX[$role->value];
    }
}
