<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\AuditEventType;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Official write contract for the audit journal.
 *
 * Implementations must always sanitize values and must never expose a way to
 * inject raw Spatie properties or to update/delete an existing activity.
 */
interface AuditLogger
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        AuditEventType $event,
        Model $subject,
        array $oldValues,
        array $newValues,
        AuditContext $context,
    ): AuditResult;
}
