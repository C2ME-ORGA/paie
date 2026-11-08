<?php

declare(strict_types=1);

use App\Support\Audit\AuditIntegrityConfiguration;
use RuntimeException;

beforeEach(function () {
    config([
        'filament-activity-log.integrity.enabled' => true,
        'filament-activity-log.integrity.key' => null,
        'app.key' => 'base64:'.str_repeat('c', 32),
    ]);
});

test('allows the APP_KEY fallback in local', function () {
    expect(fn () => AuditIntegrityConfiguration::assertConfigured('local'))
        ->not->toThrow(RuntimeException::class);
});

test('allows the APP_KEY fallback in testing', function () {
    expect(fn () => AuditIntegrityConfiguration::assertConfigured('testing'))
        ->not->toThrow(RuntimeException::class);
});

test('uses the application environment when no explicit environment is given', function () {
    expect(app()->environment())->toBe('testing')
        ->and(fn () => AuditIntegrityConfiguration::assertConfigured())
        ->not->toThrow(RuntimeException::class);
});

test('requires a dedicated key in every environment outside local and testing', function (string $environment) {
    expect(fn () => AuditIntegrityConfiguration::assertConfigured($environment))
        ->toThrow(RuntimeException::class, 'ACTIVITY_LOG_INTEGRITY_KEY');
})->with([
    'staging',
    'preproduction',
    'production',
    'prod',
    'custom-environment',
]);

test('rejects a dedicated key shorter than 32 characters', function () {
    config(['filament-activity-log.integrity.key' => 'too-short']);

    expect(fn () => AuditIntegrityConfiguration::assertConfigured('staging'))
        ->toThrow(RuntimeException::class, 'at least 32 characters');
});

test('rejects a dedicated key identical to APP_KEY', function () {
    $sharedKey = str_repeat('a', 32);

    config([
        'app.key' => $sharedKey,
        'filament-activity-log.integrity.key' => $sharedKey,
    ]);

    expect(fn () => AuditIntegrityConfiguration::assertConfigured('preproduction'))
        ->toThrow(RuntimeException::class, 'must differ from APP_KEY');
});

test('rejects disabled integrity checks outside local and testing', function () {
    config([
        'filament-activity-log.integrity.enabled' => false,
        'filament-activity-log.integrity.key' => str_repeat('a', 32),
    ]);

    expect(fn () => AuditIntegrityConfiguration::assertConfigured('production'))
        ->toThrow(RuntimeException::class, 'must remain enabled');
});

test('accepts a dedicated key of sufficient length', function () {
    config([
        'app.key' => str_repeat('b', 32),
        'filament-activity-log.integrity.key' => str_repeat('a', 32),
    ]);

    expect(fn () => AuditIntegrityConfiguration::assertConfigured('production'))
        ->not->toThrow(RuntimeException::class);
});
