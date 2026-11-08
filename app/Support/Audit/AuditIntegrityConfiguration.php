<?php

declare(strict_types=1);

namespace App\Support\Audit;

use RuntimeException;

/**
 * Prevents a silent integrity downgrade outside local and testing.
 *
 * The APP_KEY fallback is only tolerated in local and testing environments.
 * Every other environment (staging, preproduction, production, custom names)
 * must provide a dedicated, stable key.
 */
final class AuditIntegrityConfiguration
{
    private const MIN_KEY_LENGTH = 32;

    /**
     * @var list<string>
     */
    private const FALLBACK_ENVIRONMENTS = ['local', 'testing'];

    public static function assertConfigured(?string $environment = null): void
    {
        $environment ??= app()->environment();

        if (in_array($environment, self::FALLBACK_ENVIRONMENTS, true)) {
            return;
        }

        if (! (bool) config('filament-activity-log.integrity.enabled', true)) {
            throw new RuntimeException('Audit integrity must remain enabled outside local and testing environments.');
        }

        $key = config('filament-activity-log.integrity.key');

        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('ACTIVITY_LOG_INTEGRITY_KEY must be configured outside local and testing environments.');
        }

        if (strlen(trim($key)) < self::MIN_KEY_LENGTH) {
            throw new RuntimeException('ACTIVITY_LOG_INTEGRITY_KEY must be at least 32 characters outside local and testing environments.');
        }

        $appKey = config('app.key');

        if (is_string($appKey)
            && trim($appKey) !== ''
            && hash_equals(trim($appKey), trim($key))) {
            throw new RuntimeException('ACTIVITY_LOG_INTEGRITY_KEY must differ from APP_KEY outside local and testing environments.');
        }
    }
}
