<?php

use App\Enums\AuditEventType;
use App\Enums\AuditOrigin;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['filament-activity-log.integrity.key' => 'test-only-integrity-key']);
});

test('resolves one scoped manager instance per request scope', function () {
    $manager = app(AuditManager::class);

    expect(app(AuditManager::class))->toBe($manager);

    app()->forgetScopedInstances();

    expect(app(AuditManager::class))->not->toBe($manager);
});

test('uses an authenticated user as the default context', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $context = app(AuditManager::class)->currentContext();

    expect($context->origin)->toBe(AuditOrigin::User)
        ->and($context->actor?->is($user))->toBeTrue()
        ->and($context->correlationId)->not->toBeNull();
});

test('uses a system context when no user is authenticated', function () {
    $context = app(AuditManager::class)->currentContext();

    expect($context->origin)->toBe(AuditOrigin::System)
        ->and($context->actor)->toBeNull()
        ->and($context->correlationId)->not->toBeNull();
});

test('restores the previous context after a temporary context', function () {
    $manager = app(AuditManager::class);
    $previous = $manager->currentContext();

    $temporary = AuditContext::automated(correlationId: 'temporary-correlation');
    $observed = $manager->withContext($temporary, fn (): AuditContext => $manager->currentContext());

    expect($observed->origin)->toBe(AuditOrigin::Automated)
        ->and($observed->correlationId)->toBe('temporary-correlation')
        ->and($manager->currentContext()->correlationId)->toBe($previous->correlationId);
});

test('restores the previous context when the callback throws', function () {
    $manager = app(AuditManager::class);
    $previous = $manager->currentContext();
    $temporary = AuditContext::system(correlationId: 'exception-correlation');

    expect(fn () => $manager->withContext($temporary, function (): never {
        throw new RuntimeException('audit callback failure');
    }))->toThrow(RuntimeException::class);

    expect($manager->currentContext()->correlationId)->toBe($previous->correlationId);
});

test('assigns a correlation id to a temporary context that has none', function () {
    $manager = app(AuditManager::class);
    $observed = $manager->withContext(
        AuditContext::system(),
        fn (): AuditContext => $manager->currentContext(),
    );

    expect($observed->correlationId)->not->toBeNull();
});

test('resetContext forces a fresh default context', function () {
    $manager = app(AuditManager::class);
    $first = $manager->currentContext();

    $manager->resetContext();
    $second = $manager->currentContext();

    expect($second->correlationId)->not->toBe($first->correlationId);
});

test('the scoped manager does not leak context across simulated request or job boundaries', function () {
    $manager = app(AuditManager::class);
    $manager->withContext(
        AuditContext::system(correlationId: 'system-operation'),
        fn (): null => null,
    );

    app()->forgetScopedInstances();

    $user = User::factory()->create();
    $this->actingAs($user);

    $freshManager = app(AuditManager::class);

    expect($freshManager)->not->toBe($manager)
        ->and($freshManager->currentContext()->origin)->toBe(AuditOrigin::User)
        ->and($freshManager->currentContext()->correlationId)->not->toBe('system-operation');
});

test('records with the authenticated user context by default', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $result = app(AuditManager::class)->record(
        event: AuditEventType::UserUpdated,
        subject: $user,
        oldValues: ['name' => 'Old'],
        newValues: ['name' => 'New'],
    );

    $activity = Activity::query()->where('id', $result->activityId)->firstOrFail();

    expect($activity->causer_id)->toBe($user->getKey())
        ->and($activity->properties->get('origin'))->toBe('user')
        ->and($activity->properties->get('correlation_id'))->not->toBeNull();
});

test('records through the current temporary context', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $manager = app(AuditManager::class);

    $result = $manager->withContext(
        AuditContext::automated(correlationId: 'automated-operation'),
        fn () => $manager->record(
            event: AuditEventType::UserUpdated,
            subject: $user,
            oldValues: ['name' => 'Old'],
            newValues: ['name' => 'New'],
        ),
    );

    $activity = Activity::query()->where('id', $result->activityId)->firstOrFail();

    expect($activity->causer_id)->toBeNull()
        ->and($activity->properties->get('origin'))->toBe('automated')
        ->and($activity->properties->get('correlation_id'))->toBe('automated-operation');
});

test('an explicit context overrides the current context for one record', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $result = app(AuditManager::class)->record(
        event: AuditEventType::UserUpdated,
        subject: $user,
        oldValues: ['name' => 'Old'],
        newValues: ['name' => 'New'],
        context: AuditContext::system(correlationId: 'override-correlation'),
    );

    $activity = Activity::query()->where('id', $result->activityId)->firstOrFail();

    expect($activity->causer_id)->toBeNull()
        ->and($activity->properties->get('origin'))->toBe('system')
        ->and($activity->properties->get('correlation_id'))->toBe('override-correlation');
});
