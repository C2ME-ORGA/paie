<?php

use App\Contracts\AuditLogger;
use App\Enums\AuditEventType;
use App\Exceptions\AuditLoggingException;
use App\Models\AdministratorInvitation;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditManager;
use App\Services\Audit\SpatieAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['filament-activity-log.integrity.key' => 'test-only-integrity-key']);
});

test('binds the audit logger contract to the Spatie implementation', function () {
    expect(get_class(app(AuditLogger::class)))->toBe(SpatieAuditLogger::class);
});

test('records a sanitized user creation event with actor and context', function () {
    $user = User::factory()->create([
        'name' => 'Alice Martin',
        'email' => 'alice@example.test',
    ]);

    $result = app(AuditManager::class)->record(
        event: AuditEventType::UserCreated,
        subject: $user,
        newValues: [
            'name' => 'Alice Martin',
            'email' => 'alice@example.test',
            'password' => 'plain-secret-value',
            'not_allowed' => 'dropped',
        ],
        context: AuditContext::user(
            actor: $user,
            correlationId: 'correlation-1',
            reason: 'Création d’un utilisateur',
            metadata: [
                'source' => 'http',
                'authorization' => 'Bearer plain-secret-value',
                'unknown' => 'dropped',
            ],
        ),
    );

    $activity = Activity::query()->findOrFail($result->activityId);
    $changes = $activity->attribute_changes->toArray();
    $properties = $activity->properties->toArray();

    expect($result->event)->toBe(AuditEventType::UserCreated)
        ->and($result->subjectType)->toBe($user->getMorphClass())
        ->and($result->subjectId)->toBe($user->getKey())
        ->and($result->logName)->toBe('audit')
        ->and($activity->log_name)->toBe('audit')
        ->and($activity->event)->toBe('user.created')
        ->and($activity->description)->toBe(AuditEventType::UserCreated->description())
        ->and($activity->causer_id)->toBe($user->getKey())
        ->and($activity->getAttribute('integrity_hash'))->not->toBeNull()
        ->and($changes['attributes'])->toBe([
            'name' => 'Alice Martin',
            'email' => 'alice@example.test',
        ])
        ->and($properties)->toMatchArray([
            'origin' => 'user',
            'correlation_id' => 'correlation-1',
            'reason' => 'Création d’un utilisateur',
            'source' => 'http',
        ])
        ->and($properties)->not->toHaveKey('authorization')
        ->and($properties)->not->toHaveKey('unknown')
        ->and(json_encode($changes))->not->toContain('plain-secret-value')
        ->and(json_encode($properties))->not->toContain('plain-secret-value');
});

test('records a deleted subject using its retained key', function () {
    $user = User::factory()->create([
        'name' => 'Deleted User',
        'email' => 'deleted@example.test',
    ]);
    $subjectId = $user->getKey();

    $user->delete();

    $result = app(AuditManager::class)->record(
        event: AuditEventType::UserDeleted,
        subject: $user,
        oldValues: ['name' => 'Deleted User', 'email' => 'deleted@example.test'],
        context: AuditContext::system(correlationId: 'deleted-subject'),
    );

    $activity = Activity::query()->where('id', $result->activityId)->firstOrFail();

    expect($activity->subject_id)->toBe($subjectId)
        ->and($activity->attribute_changes->toArray()['old'])->toBe([
            'name' => 'Deleted User',
            'email' => 'deleted@example.test',
        ])
        ->and($activity->attribute_changes->toArray()['attributes'])->toBe([]);
});

test('drops an invalid free-form reason without failing the audit', function () {
    $user = User::factory()->create();

    $result = app(AuditManager::class)->record(
        event: AuditEventType::UserUpdated,
        subject: $user,
        oldValues: ['name' => 'Old'],
        newValues: ['name' => 'New'],
        context: AuditContext::system(
            correlationId: 'invalid-reason',
            reason: 'Motif libre contenant token=plain-secret-value',
        ),
    );

    $activity = Activity::query()->where('id', $result->activityId)->firstOrFail();

    expect($activity->properties->has('reason'))->toBeFalse()
        ->and(json_encode($activity->getAttributes()))->not->toContain('plain-secret-value')
        ->and(json_encode($activity->getAttributes()))->not->toContain('token=');
});

test('records system and automated events without a human causer', function () {
    $user = User::factory()->create();
    $manager = app(AuditManager::class);

    $system = $manager->record(
        event: AuditEventType::UserUpdated,
        subject: $user,
        oldValues: ['name' => 'Old name'],
        newValues: ['name' => 'System name'],
        context: AuditContext::system(correlationId: 'system-correlation'),
    );

    $automated = $manager->record(
        event: AuditEventType::UserUpdated,
        subject: $user,
        oldValues: ['name' => 'System name'],
        newValues: ['name' => 'Automated name'],
        context: AuditContext::automated(correlationId: 'automated-correlation'),
    );

    $systemActivity = Activity::query()->findOrFail($system->activityId);
    $automatedActivity = Activity::query()->findOrFail($automated->activityId);

    expect($systemActivity->causer_id)->toBeNull()
        ->and($systemActivity->properties->get('origin'))->toBe('system')
        ->and($automatedActivity->causer_id)->toBeNull()
        ->and($automatedActivity->properties->get('origin'))->toBe('automated');
});

test('uses the dedicated channel for technical events', function () {
    $user = User::factory()->create();

    $result = app(AuditManager::class)->record(
        event: AuditEventType::SecurityAccessDenied,
        subject: $user,
        context: AuditContext::system(correlationId: 'security-correlation'),
    );

    $activity = Activity::query()->findOrFail($result->activityId);

    expect($activity->log_name)->toBe('security')
        ->and($activity->event)->toBe('security.access_denied');
});

test('refuses to record an event for an unsaved subject', function () {
    $user = new User(['name' => 'Unsaved User']);

    expect(fn () => app(AuditManager::class)->record(
        event: AuditEventType::UserCreated,
        subject: $user,
        newValues: ['name' => 'Unsaved User'],
        context: AuditContext::system(),
    ))->toThrow(AuditLoggingException::class);

    expect(Activity::query()->count())->toBe(0);
});

test('refuses an unsaved actor instead of silently losing the causer', function () {
    $subject = User::factory()->create();
    $actor = new User(['name' => 'Unsaved actor']);

    expect(fn () => app(AuditManager::class)->record(
        event: AuditEventType::UserUpdated,
        subject: $subject,
        oldValues: ['name' => 'Old'],
        newValues: ['name' => 'New'],
        context: AuditContext::user($actor, correlationId: 'unsaved-actor'),
    ))->toThrow(AuditLoggingException::class);

    expect(Activity::query()->count())->toBe(0);
});

test('fails closed when the sanitizer encounters an unsupported value', function () {
    $user = User::factory()->create();

    expect(fn () => app(AuditManager::class)->record(
        event: AuditEventType::UserUpdated,
        subject: $user,
        oldValues: ['name' => 'Old'],
        newValues: ['name' => new stdClass],
        context: AuditContext::system(),
    ))->toThrow(AuditLoggingException::class);

    expect(Activity::query()->count())->toBe(0);
});

test('the application audit configuration allow-lists only expected user fields', function () {
    $fields = config('audit.fields');
    $allowed = $fields[User::class][AuditEventType::UserUpdated->value] ?? [];
    $invitationAllowed = $fields[AdministratorInvitation::class][AuditEventType::AdministratorInvitationCreated->value] ?? [];

    expect($allowed)->toContain('name')
        ->and($allowed)->toContain('email')
        ->and($allowed)->toContain('email_verified_at')
        ->and($allowed)->not->toContain('password')
        ->and($allowed)->not->toContain('remember_token')
        ->and($allowed)->not->toContain('token_hash')
        ->and($invitationAllowed)->toContain('name')
        ->and($invitationAllowed)->not->toContain('token_hash');
});
