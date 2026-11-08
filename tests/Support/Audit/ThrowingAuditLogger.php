<?php

declare(strict_types=1);

namespace Tests\Support\Audit;

use App\Contracts\AuditLogger;
use App\Enums\AuditEventType;
use App\Exceptions\AuditLoggingException;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Test double that fails only for the listed audit events.
 *
 * Every other event is accepted without persistence so that upstream flows
 * (invitation creation, login, ...) can reach the event under test.
 */
final class ThrowingAuditLogger implements AuditLogger
{
    /**
     * @var list<string>
     */
    public array $recorded = [];

    /**
     * @param  list<AuditEventType>  $failingEvents
     */
    public function __construct(
        private readonly array $failingEvents,
    ) {}

    public static function bind(AuditEventType ...$events): self
    {
        $logger = new self(array_values($events));

        app()->forgetScopedInstances();
        app()->instance(AuditLogger::class, $logger);

        return $logger;
    }

    public function record(
        AuditEventType $event,
        Model $subject,
        array $oldValues,
        array $newValues,
        AuditContext $context,
    ): AuditResult {
        $this->recorded[] = $event->value;

        if (in_array($event, $this->failingEvents, true)) {
            throw AuditLoggingException::forEvent($event);
        }

        return new AuditResult(
            activityId: 0,
            event: $event,
            subjectType: $subject->getMorphClass(),
            subjectId: $subject->getKey() ?? 0,
            logName: $event->logName(),
        );
    }
}
