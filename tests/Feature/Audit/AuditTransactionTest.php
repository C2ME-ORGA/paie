<?php

use App\Enums\AuditEventType;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['filament-activity-log.integrity.key' => 'test-only-integrity-key']);
});

test('keeps the audit record when the business transaction commits', function () {
    $user = User::factory()->create(['name' => 'Before commit']);
    $manager = app(AuditManager::class);

    DB::transaction(function () use ($manager, $user): void {
        $oldName = $user->name;

        $user->update(['name' => 'After commit']);

        $manager->record(
            event: AuditEventType::UserUpdated,
            subject: $user,
            oldValues: ['name' => $oldName],
            newValues: ['name' => 'After commit'],
            context: AuditContext::system(correlationId: 'commit-correlation'),
        );
    });

    expect($user->fresh()->name)->toBe('After commit')
        ->and(Activity::query()->count())->toBe(1);
});

test('rolls the audit record back with the business transaction', function () {
    $user = User::factory()->create(['name' => 'Before rollback']);
    $manager = app(AuditManager::class);

    try {
        DB::transaction(function () use ($manager, $user): void {
            $oldName = $user->name;

            $user->update(['name' => 'After rollback']);

            $manager->record(
                event: AuditEventType::UserUpdated,
                subject: $user,
                oldValues: ['name' => $oldName],
                newValues: ['name' => 'After rollback'],
                context: AuditContext::system(correlationId: 'rollback-correlation'),
            );

            throw new RuntimeException('force transaction rollback');
        });
    } catch (RuntimeException) {
        // Expected: the business operation is cancelled.
    }

    expect($user->fresh()->name)->toBe('Before rollback')
        ->and(Activity::query()->count())->toBe(0);
});
