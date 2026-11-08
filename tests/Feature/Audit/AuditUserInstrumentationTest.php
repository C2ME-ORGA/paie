<?php

use App\Enums\AuditEventType;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['filament-activity-log.integrity.key' => 'test-only-integrity-key']);
});

test('records a user update with allowed fields only', function () {
    $user = User::factory()->create([
        'name' => 'Before update',
        'email' => 'user@example.test',
    ]);

    $oldValues = $user->only(['name', 'email']);
    $user->update(['name' => 'After update']);

    $result = app(AuditManager::class)->record(
        event: AuditEventType::UserUpdated,
        subject: $user,
        oldValues: $oldValues,
        newValues: [
            'name' => 'After update',
            'email' => 'user@example.test',
            'password' => 'plain-secret-value',
            'remember_token' => 'remember-secret',
        ],
        context: AuditContext::system(correlationId: 'user-update'),
    );

    $activity = Activity::query()->where('id', $result->activityId)->firstOrFail();
    $changes = $activity->attribute_changes->toArray();

    expect($activity->event)->toBe(AuditEventType::UserUpdated->value)
        ->and($changes['old'])->toBe([
            'name' => 'Before update',
            'email' => 'user@example.test',
        ])
        ->and($changes['attributes'])->toBe([
            'name' => 'After update',
            'email' => 'user@example.test',
        ])
        ->and(json_encode($changes))->not->toContain('plain-secret-value')
        ->and(json_encode($changes))->not->toContain('remember-secret');
});

test('records a user deletion with the retained subject identifier', function () {
    $user = User::factory()->create([
        'name' => 'Deleted user',
        'email' => 'deleted@example.test',
    ]);
    $subjectId = $user->getKey();
    $oldValues = $user->only(['name', 'email']);

    $user->delete();

    $result = app(AuditManager::class)->record(
        event: AuditEventType::UserDeleted,
        subject: $user,
        oldValues: $oldValues,
        context: AuditContext::system(correlationId: 'user-delete'),
    );

    $activity = Activity::query()->where('id', $result->activityId)->firstOrFail();

    expect($activity->event)->toBe(AuditEventType::UserDeleted->value)
        ->and($activity->subject_id)->toBe($subjectId)
        ->and($activity->attribute_changes->toArray()['old'])->toBe([
            'name' => 'Deleted user',
            'email' => 'deleted@example.test',
        ])
        ->and($activity->attribute_changes->toArray()['attributes'])->toBe([]);
});
