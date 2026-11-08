<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/**
 * Raised when a successful authentication cannot be audited.
 *
 * A dedicated type lets the exception renderer attach the queued cookie
 * deletions to the 503 response: the normal cookie middleware does not run
 * when an exception interrupts the pipeline.
 */
final class AuditLoginUnavailableException extends ServiceUnavailableHttpException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(
            retryAfter: 60,
            message: 'Authentication is temporarily unavailable.',
            previous: $previous,
        );
    }
}
