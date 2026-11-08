<?php

namespace App\Enums;

/**
 * Canonical application permissions.
 *
 * Only permissions whose feature exists may be added here. Future platform
 * and business permissions are documented in docs/security/RBAC.md until
 * their corresponding feature is implemented.
 */
enum PermissionName: string
{
    case AdministrationPanelAccess = 'administration.panel.access';
}
