<?php

namespace App\Enums;

/**
 * Canonical technical role identifiers.
 *
 * The values are persisted in the Spatie "roles" table and must remain stable.
 * Human-readable labels are provided by label() and may be translated later.
 */
enum RbacRole: string
{
    case Administrator = 'administrator';
    case Management = 'management';
    case PayrollManager = 'payroll_manager';
    case HrManager = 'hr_manager';
    case Employee = 'employee';
    case SiteManager = 'site_manager';
    case ExternalAccountant = 'external_accountant';

    /**
     * French label displayed in the application.
     */
    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrateur',
            self::Management => 'Direction',
            self::PayrollManager => 'Comptable / Gestionnaire de paie',
            self::HrManager => 'Responsable RH',
            self::Employee => 'Salarié',
            self::SiteManager => "Chef d'équipe / Responsable chantier",
            self::ExternalAccountant => 'Expert-comptable externe',
        };
    }
}
