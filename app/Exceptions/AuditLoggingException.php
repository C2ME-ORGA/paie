<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\AuditEventType;
use RuntimeException;
use Throwable;

/**
 * Raised when an audit record cannot be persisted safely.
 *
 * Messages never include audited values, secrets or raw payloads.
 */
final class AuditLoggingException extends RuntimeException
{
    public static function forEvent(AuditEventType $event, ?Throwable $previous = null): self
    {
        return new self("Unable to persist audit event [{$event->value}].", 0, $previous);
    }

    public static function invalidSubject(AuditEventType $event): self
    {
        return new self("Audit event [{$event->value}] requires a persisted subject.");
    }

    public static function invalidActor(AuditEventType $event): self
    {
        return new self("Audit event [{$event->value}] requires a persisted actor.");
    }

    public static function notPersisted(AuditEventType $event): self
    {
        return new self("Audit event [{$event->value}] was not persisted.");
    }
}
