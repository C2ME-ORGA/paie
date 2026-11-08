<?php

declare(strict_types=1);

namespace App\Enums;

use InvalidArgumentException;

/**
 * Central catalogue of auditable events.
 *
 * New domains add a case here before instrumenting their operations. The enum
 * intentionally rejects arbitrary strings so event names remain searchable and
 * stable. Technical events use a dedicated prefix and log channel.
 */
enum AuditEventType: string
{
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeleted = 'user.deleted';

    case AdministratorInvitationCreated = 'administrator_invitation.created';
    case AdministratorInvitationConsumed = 'administrator_invitation.consumed';
    case AdministratorInvitationRevoked = 'administrator_invitation.revoked';
    case AdministratorInvitationDeliveryFailed = 'administrator_invitation.delivery_failed';

    case RbacRoleAssigned = 'rbac.role_assigned';
    case RbacRoleRemoved = 'rbac.role_removed';
    case RbacPermissionAssigned = 'rbac.permission_assigned';
    case RbacPermissionRevoked = 'rbac.permission_revoked';

    case SecurityAccessDenied = 'security.access_denied';
    case SecurityLoginSucceeded = 'security.login_succeeded';
    case SecurityLoginFailed = 'security.login_failed';
    case SecurityLogout = 'security.logout';
    case SystemCommandCompleted = 'system.command_completed';

    public static function fromValue(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Audit event type cannot be empty.');
        }

        if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $value)) {
            throw new InvalidArgumentException("Invalid audit event type [{$value}].");
        }

        return self::tryFrom($value)
            ?? throw new InvalidArgumentException("Unknown audit event type [{$value}].");
    }

    public function category(): AuditEventCategory
    {
        return match (true) {
            str_starts_with($this->value, 'security.'),
            str_starts_with($this->value, 'rbac.') => AuditEventCategory::Security,
            str_starts_with($this->value, 'system.') => AuditEventCategory::System,
            default => AuditEventCategory::Business,
        };
    }

    public function logName(): string
    {
        return match ($this->category()) {
            AuditEventCategory::Business => 'audit',
            AuditEventCategory::Security => 'security',
            AuditEventCategory::System => 'system',
        };
    }

    public function isBusiness(): bool
    {
        return $this->category() === AuditEventCategory::Business;
    }

    public function isTechnical(): bool
    {
        return ! $this->isBusiness();
    }

    public function description(): string
    {
        return match ($this) {
            self::UserCreated => 'Création d’un utilisateur',
            self::UserUpdated => 'Modification d’un utilisateur',
            self::UserDeleted => 'Suppression d’un utilisateur',
            self::AdministratorInvitationCreated => 'Création d’une invitation administrateur',
            self::AdministratorInvitationConsumed => 'Consommation d’une invitation administrateur',
            self::AdministratorInvitationRevoked => 'Révocation d’une invitation administrateur',
            self::AdministratorInvitationDeliveryFailed => 'Échec de livraison d’une invitation administrateur',
            self::RbacRoleAssigned => 'Attribution d’un rôle',
            self::RbacRoleRemoved => 'Retrait d’un rôle',
            self::RbacPermissionAssigned => 'Attribution d’une permission',
            self::RbacPermissionRevoked => 'Retrait d’une permission',
            self::SecurityAccessDenied => 'Accès refusé',
            self::SecurityLoginSucceeded => 'Connexion réussie',
            self::SecurityLoginFailed => 'Échec de connexion',
            self::SecurityLogout => 'Déconnexion',
            self::SystemCommandCompleted => 'Commande système terminée',
        };
    }
}
