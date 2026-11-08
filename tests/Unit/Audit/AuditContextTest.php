<?php

use App\Enums\AuditOrigin;
use App\Models\User;
use App\Services\Audit\AuditContext;

test('creates a user context with its actor and optional data', function () {
    $user = new User(['name' => 'Test User']);

    $context = AuditContext::user(
        actor: $user,
        correlationId: 'correlation-1',
        reason: 'Test reason',
        metadata: ['source' => 'test'],
    );

    expect($context->origin)->toBe(AuditOrigin::User)
        ->and($context->actor)->toBe($user)
        ->and($context->correlationId)->toBe('correlation-1')
        ->and($context->reason)->toBe('Test reason')
        ->and($context->metadata)->toBe(['source' => 'test']);
});

test('creates system and automated contexts without a human actor', function () {
    $system = AuditContext::system(correlationId: 'system-1');
    $automated = AuditContext::automated(correlationId: 'automated-1');

    expect($system->origin)->toBe(AuditOrigin::System)
        ->and($system->actor)->toBeNull()
        ->and($automated->origin)->toBe(AuditOrigin::Automated)
        ->and($automated->actor)->toBeNull();
});

test('rejects a user context without an actor', function () {
    expect(fn () => new AuditContext(AuditOrigin::User))
        ->toThrow(InvalidArgumentException::class);
});

test('rejects a human actor on a system or automated context', function () {
    $user = new User(['name' => 'Test User']);

    expect(fn () => new AuditContext(AuditOrigin::System, $user))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new AuditContext(AuditOrigin::Automated, $user))
        ->toThrow(InvalidArgumentException::class);
});

test('rejects empty correlation identifiers and reasons', function () {
    expect(fn () => AuditContext::system(correlationId: '   '))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => AuditContext::system(reason: '   '))
        ->toThrow(InvalidArgumentException::class);
});

test('adds metadata without mutating the original context', function () {
    $context = AuditContext::system(metadata: ['source' => 'console']);

    $updated = $context->withMetadata(['role' => 'administrator']);

    expect($updated->metadata)->toBe([
        'source' => 'console',
        'role' => 'administrator',
    ])
        ->and($context->metadata)->toBe(['source' => 'console']);
});

test('adds or replaces a correlation identifier without changing identity', function () {
    $user = new User(['name' => 'Test User']);
    $context = AuditContext::user($user, reason: 'Reason');

    $updated = $context->withCorrelationId('correlation-2');

    expect($updated)->not->toBe($context)
        ->and($updated->correlationId)->toBe('correlation-2')
        ->and($updated->actor)->toBe($user)
        ->and($updated->origin)->toBe(AuditOrigin::User)
        ->and($updated->reason)->toBe('Reason');
});
