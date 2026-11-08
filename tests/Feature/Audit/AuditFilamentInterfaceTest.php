<?php

use AlizHarb\ActivityLog\Resources\ActivityLogs\ActivityLogResource;
use AlizHarb\ActivityLog\Resources\ActivityLogs\Pages\ListActivityLogs;
use AlizHarb\ActivityLog\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Enums\AuditEventType;
use App\Enums\RbacRole;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditManager;
use App\Support\Rbac\RbacSynchronizer;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['filament-activity-log.integrity.key' => 'test-only-integrity-key']);

    app(RbacSynchronizer::class)->synchronize();
    Activity::query()->delete();

    Filament::setCurrentPanel('admin');
});

function auditInterfaceAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(RbacRole::Administrator->value);

    return $user;
}

function auditInterfacePanelUserWithoutAudit(): User
{
    $user = User::factory()->create();
    $user->assignRole(RbacRole::Management->value);

    return $user;
}

/**
 * @param  array<string, mixed>  $oldValues
 * @param  array<string, mixed>  $newValues
 */
function auditInterfaceRecord(
    AuditEventType $event,
    User $subject,
    array $oldValues = [],
    array $newValues = [],
    ?AuditContext $context = null,
): Activity {
    $result = app(AuditManager::class)->record(
        event: $event,
        subject: $subject,
        oldValues: $oldValues,
        newValues: $newValues,
        context: $context ?? AuditContext::system(correlationId: 'fixture'),
    );

    return Activity::query()->findOrFail($result->activityId);
}

test('an administrator with audit.view can access the audit list', function () {
    $admin = auditInterfaceAdmin();

    $this->actingAs($admin)
        ->get(route('filament.admin.resources.activity-logs.index'))
        ->assertOk();
});

test('a panel user without audit.view cannot access the audit list', function () {
    $user = auditInterfacePanelUserWithoutAudit();

    $this->actingAs($user)
        ->get(route('filament.admin.resources.activity-logs.index'))
        ->assertForbidden();
});

test('a guest is redirected to the login page', function () {
    $this->get(route('filament.admin.resources.activity-logs.index'))
        ->assertRedirect(route('filament.admin.auth.login'));
});

test('the audit navigation is hidden without audit.view', function () {
    $user = auditInterfacePanelUserWithoutAudit();
    $this->actingAs($user);

    expect(ActivityLogResource::canViewAny())->toBeFalse();
});

test('the audit navigation is visible with audit.view', function () {
    $admin = auditInterfaceAdmin();
    $this->actingAs($admin);

    expect(ActivityLogResource::canViewAny())->toBeTrue();
});

test('an unauthorized user cannot open a record detail by direct URL', function () {
    $admin = auditInterfaceAdmin();
    $activity = auditInterfaceRecord(
        AuditEventType::UserCreated,
        User::factory()->create(['name' => 'Subject User']),
        newValues: ['name' => 'Subject User'],
    );

    $url = route('filament.admin.resources.activity-logs.view', ['record' => $activity]);
    $this->actingAs(auditInterfacePanelUserWithoutAudit())->get($url)->assertForbidden();

    $this->actingAs($admin)->get($url)->assertOk();
});

test('an unauthorized Livewire page access is refused', function () {
    $user = auditInterfacePanelUserWithoutAudit();
    $this->actingAs($user);

    Livewire::test(ListActivityLogs::class)->assertStatus(403);
});

test('an authorized Livewire page displays paginated audit records', function () {
    $admin = auditInterfaceAdmin();
    $subject = User::factory()->create();

    $activities = collect(range(1, 30))->map(
        fn (int $index): Activity => auditInterfaceRecord(
            AuditEventType::UserCreated,
            $subject,
            newValues: ['name' => "User {$index}"],
        ),
    );

    $this->actingAs($admin);

    $component = Livewire::test(ListActivityLogs::class)
        ->loadTable()
        ->assertCountTableRecords(30)
        ->assertTableColumnExists('origin')
        ->assertTableColumnExists('actor_display')
        ->assertSee('Système');

    $page = $component->instance();

    if (! $page instanceof ListActivityLogs) {
        $this->fail('The audit list page was not resolved.');
    }

    $records = $page->getTableRecords();

    if (! $records instanceof LengthAwarePaginator) {
        $this->fail('The audit table did not return a length-aware paginator.');
    }

    expect($records->count())->toBe(25)
        ->and($activities)->toHaveCount(30)
        ->and(config('filament-activity-log.resource.global_search.enabled'))->toBeTrue();
});

test('the period filter returns only events in the requested range', function () {
    $admin = auditInterfaceAdmin();
    $subject = User::factory()->create();

    $old = auditInterfaceRecord(
        AuditEventType::UserCreated,
        $subject,
        newValues: ['name' => 'Old event'],
    );

    DB::table('activity_log')
        ->where('id', $old->getKey())
        ->update(['created_at' => now()->subDays(2)]);

    $recent = auditInterfaceRecord(
        AuditEventType::UserUpdated,
        $subject,
        oldValues: ['name' => 'Old event'],
        newValues: ['name' => 'Recent event'],
    );

    $this->actingAs($admin);

    Livewire::test(ListActivityLogs::class)
        ->loadTable()
        ->filterTable('created_at', [
            'created_from' => now()->subDay()->toDateString(),
            'created_until' => now()->addDay()->toDateString(),
        ])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});

test('the event and origin filters return the expected records', function () {
    $admin = auditInterfaceAdmin();
    $actor = User::factory()->create();
    $subject = User::factory()->create();

    $created = auditInterfaceRecord(
        AuditEventType::UserCreated,
        $subject,
        newValues: ['name' => 'Created'],
        context: AuditContext::system(correlationId: 'created'),
    );

    $automated = auditInterfaceRecord(
        AuditEventType::UserUpdated,
        $subject,
        oldValues: ['name' => 'Created'],
        newValues: ['name' => 'Automated'],
        context: AuditContext::automated(correlationId: 'automated'),
    );

    $userAction = auditInterfaceRecord(
        AuditEventType::UserUpdated,
        $subject,
        oldValues: ['name' => 'Automated'],
        newValues: ['name' => 'User action'],
        context: AuditContext::user($actor, correlationId: 'user-action'),
    );

    $this->actingAs($admin);

    Livewire::test(ListActivityLogs::class)
        ->loadTable()
        ->filterTable('event', AuditEventType::UserCreated->value)
        ->assertCanSeeTableRecords([$created])
        ->assertCanNotSeeTableRecords([$automated, $userAction])
        ->resetTableFilters()
        ->filterTable('origin', 'automated')
        ->assertCanSeeTableRecords([$automated])
        ->assertCanNotSeeTableRecords([$created, $userAction])
        ->resetTableFilters()
        ->filterTable('causer_id', $actor->getKey())
        ->assertCanSeeTableRecords([$userAction])
        ->assertCanNotSeeTableRecords([$created, $automated])
        ->assertSee($actor->name)
        ->assertDontSee($actor->email);
});

test('the table search returns matching events', function () {
    $admin = auditInterfaceAdmin();
    $subject = User::factory()->create();

    $created = auditInterfaceRecord(
        AuditEventType::UserCreated,
        $subject,
        newValues: ['name' => 'Searchable user'],
    );

    $updated = auditInterfaceRecord(
        AuditEventType::UserUpdated,
        $subject,
        oldValues: ['name' => 'Searchable user'],
        newValues: ['name' => 'Updated user'],
    );

    $this->actingAs($admin);

    Livewire::test(ListActivityLogs::class)
        ->loadTable()
        ->searchTable('user.created')
        ->assertCanSeeTableRecords([$created])
        ->assertCanNotSeeTableRecords([$updated]);
});

test('the detail page shows sanitized changes and metadata', function () {
    $admin = auditInterfaceAdmin();
    $subject = User::factory()->create(['name' => 'Alice Martin']);

    $activity = auditInterfaceRecord(
        AuditEventType::UserUpdated,
        $subject,
        oldValues: ['name' => 'Ancien nom'],
        newValues: [
            'name' => 'Nouveau nom',
            'password' => 'plain-secret-value',
        ],
        context: AuditContext::system(
            correlationId: 'detail-correlation',
            metadata: [
                'source' => 'test',
                'authorization' => 'Bearer plain-secret-value',
            ],
        ),
    );

    $this->actingAs($admin);

    Livewire::test(ViewActivityLog::class, ['record' => $activity->getKey()])
        ->assertSee('Ancien nom')
        ->assertSee('Nouveau nom')
        ->assertSee('detail-correlation')
        ->assertSee('Système')
        ->assertDontSee('plain-secret-value');
});

test('the interface exposes no mutation, export or prune action', function () {
    $admin = auditInterfaceAdmin();
    $this->actingAs($admin);

    $component = Livewire::test(ListActivityLogs::class)
        ->loadTable()
        ->assertTableActionDoesNotExist(['delete', 'restore', 'revert', 'retention_hold'])
        ->assertTableBulkActionDoesNotExist([
            'delete',
            'place_retention_hold',
            'release_retention_hold',
            'restore_selected',
            'revert_selected',
        ]);

    $page = $component->instance();

    if (! $page instanceof ListActivityLogs) {
        $this->fail('The audit list page was not resolved.');
    }

    $visibleHeaderActions = collect($page->getTable()->getHeaderActions())
        ->filter(static fn ($action): bool => $action instanceof Action && $action->isVisible())
        ->map(static fn (Action $action): string => $action->getName())
        ->all();

    expect($visibleHeaderActions)->not->toContain('export')
        ->and($visibleHeaderActions)->not->toContain('prune');
});

test('only audit.view is created and audit.export remains inactive', function () {
    expect(Permission::query()->where('name', 'audit.view')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'audit.export')->exists())->toBeFalse();
});

test('the audit routes expose no mutation verb', function () {
    $methods = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'admin/activity-logs'))
        ->flatMap(fn ($route): array => $route->methods())
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($methods)->toBe(['GET', 'HEAD']);
});
