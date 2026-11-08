<?php

use App\Enums\AuditEventType;

const AUDIT_MOJIBAKE_SEQUENCES = [
    '├®',
    '├¿',
    '├á',
    '├┤',
    '├â',
    '├ë',
    'ÔÇ',
    'Ã©',
    'Ã¨',
    "\u{FFFD}",
];

function assertCleanAuditText(string $text): void
{
    if (! mb_check_encoding($text, 'UTF-8')) {
        throw new RuntimeException('Audit text is not valid UTF-8.');
    }

    foreach (AUDIT_MOJIBAKE_SEQUENCES as $sequence) {
        if (str_contains($text, $sequence)) {
            throw new RuntimeException('Audit text contains a mojibake sequence.');
        }
    }
}

/**
 * @return list<string>
 */
function auditSourceFiles(): array
{
    $documentation = glob(base_path('docs/business-rules/bp-192-audit-*.md')) ?: [];

    return array_merge([
        base_path('app/Enums/AuditEventType.php'),
        base_path('app/Actions/Administration/AcceptAdministratorInvitation.php'),
        base_path('app/Actions/Administration/InviteAdministrator.php'),
        base_path('app/Support/Rbac/RbacSynchronizer.php'),
        base_path('app/Listeners/AuditAuthenticationEvents.php'),
        base_path('config/audit.php'),
        base_path('config/filament-activity-log.php'),
        base_path('config/activitylog.php'),
        base_path('.env.example'),
        base_path('.env.production.example'),
        base_path('docs/security/RBAC.md'),
    ], $documentation);
}

test('audit descriptions and configured reasons are clean UTF-8', function () {
    $strings = array_map(
        static fn (AuditEventType $event): string => $event->description(),
        AuditEventType::cases(),
    );

    $strings = array_merge($strings, (array) config('audit.reasons'));

    expect($strings)->not->toBeEmpty();

    foreach ($strings as $string) {
        assertCleanAuditText($string);
    }
});

test('event descriptions are exactly the expected French text', function () {
    expect(AuditEventType::UserCreated->description())->toBe('Création d’un utilisateur')
        ->and(AuditEventType::UserUpdated->description())->toBe('Modification d’un utilisateur')
        ->and(AuditEventType::UserDeleted->description())->toBe('Suppression d’un utilisateur')
        ->and(AuditEventType::AdministratorInvitationCreated->description())->toBe('Création d’une invitation administrateur')
        ->and(AuditEventType::AdministratorInvitationConsumed->description())->toBe('Consommation d’une invitation administrateur')
        ->and(AuditEventType::AdministratorInvitationRevoked->description())->toBe('Révocation d’une invitation administrateur')
        ->and(AuditEventType::AdministratorInvitationDeliveryFailed->description())->toBe('Échec de livraison d’une invitation administrateur')
        ->and(AuditEventType::RbacRoleAssigned->description())->toBe('Attribution d’un rôle')
        ->and(AuditEventType::RbacRoleRemoved->description())->toBe('Retrait d’un rôle')
        ->and(AuditEventType::RbacPermissionAssigned->description())->toBe('Attribution d’une permission')
        ->and(AuditEventType::RbacPermissionRevoked->description())->toBe('Retrait d’une permission')
        ->and(AuditEventType::SecurityAccessDenied->description())->toBe('Accès refusé')
        ->and(AuditEventType::SecurityLoginSucceeded->description())->toBe('Connexion réussie')
        ->and(AuditEventType::SecurityLoginFailed->description())->toBe('Échec de connexion')
        ->and(AuditEventType::SecurityLogout->description())->toBe('Déconnexion')
        ->and(AuditEventType::SystemCommandCompleted->description())->toBe('Commande système terminée');
});

test('configured reasons contain the exact production motifs', function () {
    $reasons = config('audit.reasons');

    expect($reasons)->toContain('Acceptation d’une invitation administrateur')
        ->and($reasons)->toContain('Révocation d’une invitation en attente')
        ->and($reasons)->toContain('Création d’une invitation administrateur')
        ->and($reasons)->toContain('Échec d’envoi de l’invitation administrateur')
        ->and($reasons)->toContain('Révocation après échec d’envoi de l’invitation administrateur')
        ->and($reasons)->toContain('Synchronisation du référentiel RBAC')
        ->and($reasons)->toContain('Échec d’authentification')
        ->and($reasons)->toContain('Retrait du rôle administrateur')
        ->and($reasons)->toContain('Création d’un utilisateur')
        ->and($reasons)->toContain('Mise à jour d’un utilisateur')
        ->and($reasons)->toContain('Suppression d’un utilisateur');
});

test('audit source files and documentation contain no mojibake sequence', function () {
    foreach (auditSourceFiles() as $file) {
        $content = file_get_contents($file);

        expect($content)->toBeString();

        if (! is_string($content)) {
            $this->fail("Unable to read audit source file [{$file}].");
        }

        assertCleanAuditText($content);
    }
});

test('the encoding guard fails deliberately on an injected mojibake string', function () {
    expect(fn () => assertCleanAuditText('Cr├®ation dÔÇÖun utilisateur'))
        ->toThrow(RuntimeException::class);
});
