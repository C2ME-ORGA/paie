<?php

declare(strict_types=1);

use App\Enums\AuditEventType;
use App\Models\AdministratorInvitation;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed attribute fields
    |--------------------------------------------------------------------------
    |
    | The allow-list is the primary confidentiality control. A model/event pair
    | that is absent from this array logs no attribute values at all. Adding a
    | field here is an explicit security decision, not a convenience default.
    |
    */
    'fields' => [
        User::class => [
            AuditEventType::UserCreated->value => ['name', 'email', 'email_verified_at'],
            AuditEventType::UserUpdated->value => ['name', 'email', 'email_verified_at'],
            AuditEventType::UserDeleted->value => ['name', 'email'],
            AuditEventType::RbacRoleAssigned->value => ['name'],
            AuditEventType::RbacRoleRemoved->value => ['name'],
        ],

        AdministratorInvitation::class => [
            AuditEventType::AdministratorInvitationCreated->value => ['name', 'email', 'expires_at'],
            AuditEventType::AdministratorInvitationConsumed->value => ['consumed_at'],
            AuditEventType::AdministratorInvitationRevoked->value => ['revoked_at'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed metadata keys
    |--------------------------------------------------------------------------
    |
    | Metadata is also allow-listed. Free-form keys are dropped unless they are
    | explicitly listed here. All values remain subject to the sensitive-key
    | blacklist below.
    |
    */
    'metadata' => [
        '*' => [
            'source',
            'channel',
            'command',
            'job',
            'request_id',
            'correlation_id',
            'origin',
            'reason',
            'actor_type',
            'initiated_by',
            'guard',
            'role',
            'permission',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Controlled technical metadata values
    |--------------------------------------------------------------------------
    |
    | These keys only accept the values listed here. Any other value is
    | discarded by AuditSanitizer and never reaches Activitylog.
    |
    */
    'metadata_values' => [
        'source' => ['http', 'console', 'queue', 'scheduler', 'test', 'auth'],
        'channel' => ['administrator_invitation', 'rbac_synchronization', 'session'],
        'origin' => ['user', 'system', 'automated'],
        'guard' => ['web'],
        'actor_type' => ['user', 'system', 'automated', 'invitation_recipient'],
        'initiated_by' => ['invitation_recipient'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Technical identifiers
    |--------------------------------------------------------------------------
    |
    | Correlation/request identifiers are opaque safe identifiers. Generated
    | correlations are UUIDs; the pattern also accepts existing test/import
    | identifiers without allowing spaces, paths or payload fragments.
    |
    */
    'metadata_identifier_pattern' => '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$/',

    /*
    | Command, job, role and permission names are technical identifiers, not
    | free text. This pattern excludes whitespace and payload punctuation.
    */
    'metadata_technical_identifier_pattern' => '/^[A-Za-z0-9][A-Za-z0-9_.:\\-]{0,199}$/',

    /*
    |--------------------------------------------------------------------------
    | Descriptive reason values
    |--------------------------------------------------------------------------
    |
    | "reason" is deliberately an allow-list. A free-text reason could
    | accidentally contain a token, password fragment or bank data. Unknown,
    | too long, structured or non-string reasons are discarded. New business
    | reasons must be added here explicitly.
    |
    */
    'reason_max_length' => 200,
    'reasons' => [
        'Acceptation d’une invitation administrateur',
        'Révocation d’une invitation en attente',
        'Création d’une invitation administrateur',
        'Échec d’envoi de l’invitation administrateur',
        'Révocation après échec d’envoi de l’invitation administrateur',
        'Synchronisation du référentiel RBAC',
        'Échec d’authentification',
        'Retrait du rôle administrateur',
        'Création d’un utilisateur',
        'Mise à jour d’un utilisateur',
        'Suppression d’un utilisateur',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive keys
    |--------------------------------------------------------------------------
    |
    | Exact normalized keys that must never be persisted, even if a developer
    | accidentally adds them to an allow-list.
    |
    */
    'sensitive_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'passwd',
        'pwd',
        'passphrase',
        'remember_token',
        'token',
        'token_hash',
        'api_token',
        'access_token',
        'refresh_token',
        'secret',
        'api_key',
        'private_key',
        'authorization',
        'cookie',
        'session',
        'credential',
        'credentials',
        'iban',
        'bic',
        'bank_account',
        'rib',
        'account_number',
        'routing_number',
        'nir',
        'social_security_number',
        'ssn',
        'medical_data',
        'health_data',
        'diagnosis',
        'sick_leave',
        'card_number',
        'cvv',
        'cvc',
        'pin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive key patterns
    |--------------------------------------------------------------------------
    |
    | Applied to a normalized key: lower case, separators replaced by "_".
    | Patterns are intentionally broad because false negatives are more costly
    | than a few false positives in an audit trail.
    |
    */
    'sensitive_patterns' => [
        '/password|passwd|passphrase/i',
        '/token|secret|api_?key|private_?key|authorization|cookie|session|credential/i',
        '/iban|bic|bank_?account|rib|account_?number|routing_?number|nir|ssn|social_?security/i',
        '/medical|health|diagnosis|sick_?leave|card_?number|cvv|cvc/i',
    ],

    /*
    |--------------------------------------------------------------------------
    | Recursion limit
    |--------------------------------------------------------------------------
    |
    | Prevents pathological or cyclic structures from exhausting the worker.
    |
    */
    'max_depth' => 8,

];
