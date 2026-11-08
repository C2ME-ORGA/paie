<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\AuditEventType;

/**
 * Explicit result of a successful audit write.
 *
 * The Spatie activity model is intentionally not exposed to domain code.
 */
final readonly class AuditResult
{
    public function __construct(
        public int $activityId,
        public AuditEventType $event,
        public string $subjectType,
        public int|string $subjectId,
        public string $logName,
    ) {}
}
