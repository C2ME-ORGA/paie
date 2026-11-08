<?php

declare(strict_types=1);

use AlizHarb\ActivityLog\Exceptions\IntegrityException;
use AlizHarb\ActivityLog\Support\ActivityIntegrity;
use Spatie\Activitylog\Models\Activity;

function integrityActivity(): Activity
{
    return new Activity([
        'log_name' => 'audit',
        'description' => 'Test event',
        'event' => 'user.created',
        'properties' => [],
        'attribute_changes' => [],
    ]);
}

test('signs with APP_KEY when no dedicated key is configured', function () {
    config([
        'filament-activity-log.integrity.key' => null,
        'app.key' => 'base64:'.str_repeat('a', 32),
    ]);

    $activity = integrityActivity();
    $integrity = app(ActivityIntegrity::class);
    $hash = $integrity->hash($activity);

    expect($hash)->toHaveLength(64);

    $activity->setAttribute('integrity_hash', $hash);

    expect($integrity->verify($activity))->toBeTrue();
});

test('uses the dedicated key when both keys are configured', function () {
    config([
        'filament-activity-log.integrity.key' => str_repeat('d', 32),
        'app.key' => 'base64:'.str_repeat('a', 32),
    ]);

    $activity = integrityActivity();
    $integrity = app(ActivityIntegrity::class);
    $hash = $integrity->hash($activity);

    config(['app.key' => 'base64:'.str_repeat('z', 32)]);

    expect($integrity->hash($activity))->toBe($hash);
});

test('throws when neither the dedicated key nor APP_KEY is configured', function () {
    config([
        'filament-activity-log.integrity.key' => null,
        'app.key' => '',
    ]);

    expect(fn () => app(ActivityIntegrity::class)->hash(integrityActivity()))
        ->toThrow(IntegrityException::class);
});
