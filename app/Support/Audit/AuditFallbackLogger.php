<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Enums\AuditEventType;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Best-effort signal used when the audit journal cannot be written.
 *
 * The channel is intentionally independent from the audit database. It only
 * receives technical data: the event identifier, an optional correlation id
 * and the exception class. No audited value, credential or personal data is
 * ever forwarded.
 *
 * The fallback is deliberately non-throwing: if the fallback channel is also
 * unavailable, the caller's own failure policy still applies and the original
 * exception is never replaced.
 */
final class AuditFallbackLogger
{
    public static function report(
        AuditEventType $event,
        ?string $correlationId,
        Throwable $exception,
    ): void {
        try {
            Log::channel((string) config('audit.fallback_channel', 'audit_fallback'))
                ->critical('Audit journal write failed.', [
                    'event' => $event->value,
                    'correlation_id' => $correlationId,
                    'exception' => $exception::class,
                ]);
        } catch (Throwable) {
            // Last-resort logger: never mask the original failure.
        }
    }
}
