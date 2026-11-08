<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Functional classification of audit events.
 *
 * Event values remain stable. The category determines the log channel for new
 * rows; historical rows keep the channel they were written with.
 */
enum AuditEventCategory: string
{
    case Business = 'business';
    case Security = 'security';
    case System = 'system';
}
