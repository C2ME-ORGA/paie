<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Contracts\AuditLogger;
use App\Enums\AuditEventType;
use App\Exceptions\AuditLoggingException;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Spatie Activitylog implementation of the audit write contract.
 *
 * This is the only class in the application layer that may write to Activitylog
 * directly. Domain code depends on AuditManager or the AuditLogger contract.
 */
final class SpatieAuditLogger implements AuditLogger
{
    public function __construct(
        private readonly AuditSanitizer $sanitizer,
    ) {}

    public function record(
        AuditEventType $event,
        Model $subject,
        array $oldValues,
        array $newValues,
        AuditContext $context,
    ): AuditResult {
        $subjectId = $subject->getKey();

        // A deleted subject no longer exists but keeps its key. Deletion events
        // must still reference it, so only the key is mandatory.
        if (! is_int($subjectId) && ! is_string($subjectId)) {
            throw AuditLoggingException::invalidSubject($event);
        }

        if ($context->actor !== null
            && (! $context->actor->exists || $context->actor->getKey() === null)) {
            throw AuditLoggingException::invalidActor($event);
        }

        try {
            $changes = $this->sanitizer->sanitizeChanges(
                $subject::class,
                $event,
                $oldValues,
                $newValues,
            );

            $metadata = $context->metadata;
            $metadata['origin'] = $context->origin->value;

            if ($context->correlationId !== null) {
                $metadata['correlation_id'] = $context->correlationId;
            }

            if ($context->reason !== null) {
                $metadata['reason'] = $context->reason;
            }

            $properties = $this->sanitizer->sanitizeMetadata($event, $metadata);

            $logger = activity()
                ->useLog($event->logName())
                ->event($event->value)
                ->performedOn($subject)
                ->withChanges($changes)
                ->withProperties($properties);

            if ($context->actor !== null) {
                $logger->causedBy($context->actor);
            } else {
                $logger->causedByAnonymous();
            }

            $activity = $logger->log($event->description());

            if (! $activity instanceof Activity || $activity->getKey() === null) {
                throw AuditLoggingException::notPersisted($event);
            }

            return new AuditResult(
                activityId: (int) $activity->getKey(),
                event: $event,
                subjectType: $subject->getMorphClass(),
                subjectId: $subjectId,
                logName: $event->logName(),
            );
        } catch (AuditLoggingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw AuditLoggingException::forEvent($event, $exception);
        }
    }
}
