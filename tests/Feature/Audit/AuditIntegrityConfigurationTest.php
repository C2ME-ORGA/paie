<?php

use App\Support\Audit\AuditIntegrityConfiguration;
use RuntimeException;

test('allows the fallback in local', function () {
    config([
        'filament-activity-log.integrity.enabled' => true,
        'filament-activity-log.integrity.key' => null,
    ]);

    expect(fn () => AuditIntegrityConfiguration::assertConfigured('local'))
        ->not->toThrow(RuntimeException::class);
});

test('requires a dedicated integrity key in production', function () {
    config([
        'filament-activity-log.integrity.enabled' => true,
        'filament-activity-log.integrity.key' => null,
    ]);

    expect(fn () => AuditIntegrityConfiguration::assertConfigured('production'))
        ->toThrow(RuntimeException::class, 'ACTIVITY_LOG_INTEGRITY_KEY');
});

test('rejects a short integrity key in production', function () {
    config([
        'filament-activity-log.integrity.enabled' => true,
        'filament-activity-log.integrity.key' => 'too-short',
    ]);

    expect(fn () => AuditIntegrityConfiguration::assertConfigured('production'))
        ->toThrow(RuntimeException::class, 'at least 32 characters');
});

test('rejects an integrity key identical to APP_KEY in production', function () {
    $sharedKey = str_repeat('a', 32);

    config([
        'app.key' => $sharedKey,
        'filament-activity-log.integrity.enabled' => true,
        'filament-activity-log.integrity.key' => $sharedKey,
    ]);

    expect(fn () => AuditIntegrityConfiguration::assertConfigured('production'))
        ->toThrow(RuntimeException::class, 'must differ from APP_KEY');
});

test('rejects disabled integrity checks in production', function () {
    config([
        'filament-activity-log.integrity.enabled' => false,
        'filament-activity-log.integrity.key' => str_repeat('a', 32),
    ]);

    expect(fn () => AuditIntegrityConfiguration::assertConfigured('production'))
        ->toThrow(RuntimeException::class, 'must remain enabled');
});

test('accepts a dedicated key of sufficient length in production', function () {
    config([
        'app.key' => str_repeat('b', 32),
        'filament-activity-log.integrity.enabled' => true,
        'filament-activity-log.integrity.key' => str_repeat('a', 32),
    ]);

    expect(fn () => AuditIntegrityConfiguration::assertConfigured('production'))
        ->not->toThrow(RuntimeException::class);
});
