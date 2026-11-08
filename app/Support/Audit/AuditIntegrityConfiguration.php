<?php

declare(strict_types=1);

namespace App\Support\Audit;

use RuntimeException;

/**
 * Prevents a silent integrity downgrade in production.
 *
 * Local and test environments may still rely on the package fallback to
 * APP_KEY. Production must provide a dedicated, stable key.
 */
final class AuditIntegrityConfiguration
{
    private const MIN_KEY_LENGTH = 32;

    public static function assertConfigured(?bool $isProduction = null): void
    {
        $isProduction ??= app()->isProduction();

        if (! $isProduction) {
            return;
        }

        if (! (bool) config('filament-activity-log.integrity.enabled', true)) {
            throw new RuntimeException('Audit integrity must remain enabled in production.');
        }

        $key = config('filament-activity-log.integrity.key');

        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('ACTIVITY_LOG_INTEGRITY_KEY must be configured in production.');
        }

        if (strlen(trim($key)) < self::MIN_KEY_LENGTH) {
            throw new RuntimeException('ACTIVITY_LOG_INTEGRITY_KEY must be at least 32 characters in production.');
        }

        $appKey = config('app.key');

        if (is_string($appKey)
            && trim($appKey) !== ''
            && hash_equals(trim($appKey), trim($key))) {
            throw new RuntimeException('ACTIVITY_LOG_INTEGRITY_KEY must differ from APP_KEY in production.');
        }
    }
}
