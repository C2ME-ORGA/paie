<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AuditEventType;
use App\Exceptions\AuditLoggingException;
use App\Exceptions\AuditLoginUnavailableException;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditManager;
use App\Support\Audit\AuditFallbackLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Throwable;

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

        $context = $this->userContext($event->user, $event->guard);

        try {
            $this->audit->record(
                event: AuditEventType::SecurityLoginSucceeded,
                subject: $event->user,
                context: $context,
            );
        } catch (AuditLoggingException $exception) {
            $this->abortAuthenticatedSession(
                $event->user,
                $event->guard,
                $context->correlationId,
                $exception,
            );

            throw new AuditLoginUnavailableException($exception);
        }
    }

    public function logout(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $context = $this->userContext($event->user, $event->guard);

        try {
            $this->audit->record(
                event: AuditEventType::SecurityLogout,
                subject: $event->user,
                context: $context,
            );
        } catch (AuditLoggingException $exception) {
            AuditFallbackLogger::report(
                AuditEventType::SecurityLogout,
                $context->correlationId,
                $exception,
            );
        }
    }

    public function failed(Failed $event): void
    {
        // Unknown accounts have no persisted subject and are deliberately not
        // logged: the audit table must not become an account enumeration tool.
        if (! $event->user instanceof User) {
            return;
        }

        $context = AuditContext::system(
            correlationId: (string) Str::uuid(),
            reason: 'Échec d’authentification',
            metadata: $this->contextMetadata($event->guard),
        );

        try {
            $this->audit->record(
                event: AuditEventType::SecurityLoginFailed,
                subject: $event->user,
                context: $context,
            );
        } catch (AuditLoggingException $exception) {
            AuditFallbackLogger::report(
                AuditEventType::SecurityLoginFailed,
                $context->correlationId,
                $exception,
            );
        }
    }

    private function userContext(User $user, string $guard): AuditContext
    {
        return AuditContext::user(
            actor: $user,
            correlationId: (string) Str::uuid(),
            metadata: $this->contextMetadata($guard),
        );
    }

    /**
     * Invalidate the session written by SessionGuard::login() before the Login
     * event is dispatched. Auth::logout() is deliberately not used: it would
     * dispatch a Logout event and re-enter the audit listener that just failed.
     */
    private function abortAuthenticatedSession(
        User $user,
        string $guard,
        ?string $correlationId,
        AuditLoggingException $exception,
    ): void {
        $guardInstance = Auth::guard($guard);

        if ($guardInstance instanceof SessionGuard) {
            Cookie::queue(Cookie::forget($guardInstance->getRecallerName()));
        }

        $session = app('session.store');
        $session->invalidate();
        $session->regenerateToken();

        Auth::forgetGuards();

        $this->invalidateRememberToken($user, $correlationId);

        AuditFallbackLogger::report(
            AuditEventType::SecurityLoginSucceeded,
            $correlationId,
            $exception,
        );
    }

    /**
     * The recaller cookie may never reach the browser when the 503 response is
     * rendered outside the cookie middleware. Invalidating the token server
     * side makes any pre-existing recaller unusable even without the cookie.
     */
    private function invalidateRememberToken(User $user, ?string $correlationId): void
    {
        try {
            $user->setRememberToken(null);
            $user->save();
        } catch (Throwable $exception) {
            // When the database is unavailable, the recaller cannot be
            // validated either. The session and guard are already reset.
            AuditFallbackLogger::report(
                AuditEventType::SecurityLoginSucceeded,
                $correlationId,
                $exception,
            );
        }
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
