<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AuditEventType;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditManager;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Records the authentication events that are relevant to the audit journal.
 *
 * Credentials, passwords and raw login payloads are never read or stored.
 */
final class AuditAuthenticationEvents
{
    public function __construct(
        private readonly AuditManager $audit,
    ) {}

    public function login(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->audit->record(
            event: AuditEventType::SecurityLoginSucceeded,
            subject: $event->user,
            context: AuditContext::user(
                actor: $event->user,
                metadata: $this->contextMetadata($event->guard),
            ),
        );
    }

    public function logout(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->audit->record(
            event: AuditEventType::SecurityLogout,
            subject: $event->user,
            context: AuditContext::user(
                actor: $event->user,
                metadata: $this->contextMetadata($event->guard),
            ),
        );
    }

    public function failed(Failed $event): void
    {
        // Unknown accounts have no persisted subject and are deliberately not
        // logged: the audit table must not become an account enumeration tool.
        if (! $event->user instanceof User) {
            return;
        }

        $this->audit->record(
            event: AuditEventType::SecurityLoginFailed,
            subject: $event->user,
            context: AuditContext::system(
                reason: 'Échec d’authentification',
                metadata: $this->contextMetadata($event->guard),
            ),
        );
    }

    /**
     * @return array<string, string>
     */
    private function contextMetadata(string $guard): array
    {
        return [
            'source' => 'auth',
            'channel' => 'session',
            'guard' => $guard,
        ];
    }
}
