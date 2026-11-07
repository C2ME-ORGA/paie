<?php

test('the application uses the French regional settings', function () {
    expect(config('app.timezone'))->toBe('Europe/Paris')
        ->and(config('app.locale'))->toBe('fr')
        ->and(config('app.fallback_locale'))->toBe('en')
        ->and(config('app.faker_locale'))->toBe('fr_FR');
});

test('the test environment is isolated from external services', function () {
    expect(config('app.env'))->toBe('testing')
        ->and(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:')
        ->and(config('cache.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('queue.default'))->toBe('sync')
        ->and(config('mail.default'))->toBe('array');
});
