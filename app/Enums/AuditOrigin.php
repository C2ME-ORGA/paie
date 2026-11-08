<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Origin of an audited operation.
 *
 * A human actor must never be attached to a system or automated operation.
 */
enum AuditOrigin: string
{
    case User = 'user';
    case System = 'system';
    case Automated = 'automated';
}
