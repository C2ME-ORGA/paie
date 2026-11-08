<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Safe, user-facing failure for administrator bootstrap and invitation flows.
 *
 * Messages never contain the raw token, the password or the invited e-mail.
 */
final class AdministratorInvitationException extends RuntimeException
{
    public const REASON_ADMINISTRATOR_EXISTS = 'administrator_exists';

    public const REASON_INVITATION_PENDING = 'invitation_pending';

    public const REASON_EMAIL_ALREADY_USED = 'email_already_used';

    public const REASON_RBAC_MISSING = 'rbac_missing';

    public const REASON_INCONSISTENT_STATE = 'inconsistent_state';

    public const REASON_INVALID_INVITATION = 'invalid_invitation';

    public const REASON_EMAIL_DELIVERY_FAILED = 'email_delivery_failed';

    public const REASON_INSECURE_MAIL_CONFIGURATION = 'insecure_mail_configuration';

    private function __construct(
        string $message,
        public readonly string $reason,
    ) {
        parent::__construct($message);
    }

    public static function administratorExists(): self
    {
        return new self(
            'Un administrateur existe déjà. Le bootstrap est refusé.',
            self::REASON_ADMINISTRATOR_EXISTS,
        );
    }

    public static function invitationPending(): self
    {
        return new self(
            'Une invitation administrateur valide est déjà en attente.',
            self::REASON_INVITATION_PENDING,
        );
    }

    public static function emailAlreadyUsed(): self
    {
        return new self(
            'Cette adresse e-mail est déjà associée à un compte.',
            self::REASON_EMAIL_ALREADY_USED,
        );
    }

    public static function rbacMissing(): self
    {
        return new self(
            'Le référentiel RBAC est incomplet. Exécutez le seeder RBAC avant de continuer.',
            self::REASON_RBAC_MISSING,
        );
    }

    public static function inconsistentState(): self
    {
        return new self(
            "L'état des invitations ne permet pas de garantir l'unicité du bootstrap. Une intervention technique est requise.",
            self::REASON_INCONSISTENT_STATE,
        );
    }

    public static function invalidInvitation(): self
    {
        return new self(
            "Cette invitation n'est pas valide ou n'est plus disponible.",
            self::REASON_INVALID_INVITATION,
        );
    }

    public static function emailDeliveryFailed(): self
    {
        return new self(
            "L'invitation a été annulée car l'e-mail n'a pas pu être envoyé. Réessayez.",
            self::REASON_EMAIL_DELIVERY_FAILED,
        );
    }

    public static function insecureMailConfiguration(): self
    {
        return new self(
            "La configuration e-mail de production n'est pas sécurisée. Utilisez un transport réel, jamais les mailers log ou array.",
            self::REASON_INSECURE_MAIL_CONFIGURATION,
        );
    }
}
