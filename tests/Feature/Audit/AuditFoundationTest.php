<?php

use AlizHarb\ActivityLog\ActivityLogPlugin;
use AlizHarb\ActivityLog\Pages\AuditDashboard;
use AlizHarb\ActivityLog\Pages\UserActivitiesPage;
use AlizHarb\ActivityLog\Policies\ActivityPolicy;
use AlizHarb\ActivityLog\Resources\ActivityLogs\ActivityLogResource;
use AlizHarb\ActivityLog\Widgets\ActivityChartWidget;
use AlizHarb\ActivityLog\Widgets\LatestActivityWidget;
use App\Models\AdministratorInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

test('the activity log table contains the Spatie and audit foundation columns', function () {
    $columns = Schema::getColumnListing('activity_log');

    expect(array_diff([
        'id',
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'event',
        'causer_type',
        'causer_id',
        'attribute_changes',
        'properties',
        'risk_score',
        'risk_level',
        'retention_hold',
        'integrity_hash',
        'request_id',
        'ip_address',
        'created_at',
        'updated_at',
    ], $columns))->toBe([])
        ->and($columns)->not->toContain('company_id')
        ->and($columns)->not->toContain('reason')
        ->and($columns)->not->toContain('correlation_id');
});

test('the activity log configuration is safe by default', function () {
    expect(config('activitylog.enabled'))->toBeTrue()
        ->and(config('activitylog.buffer.enabled'))->toBeFalse()
        ->and(config('activitylog.default_log_name'))->toBe('audit')
        ->and(config('activitylog.clean_after_days'))->toBeNull()
        ->and(config('activitylog.default_except_attributes'))->toContain(
            'password',
            'remember_token',
            'token_hash',
            'iban',
            'nir',
        );
});

test('the plugin is installed and its presentation features are disabled', function () {
    expect(class_exists(ActivityLogPlugin::class))->toBeTrue()
        ->and(config('filament-activity-log.permissions.enabled'))->toBeTrue()
        ->and(config('filament-activity-log.permissions.view_any'))->toBe('audit.view')
        ->and(config('filament-activity-log.permissions.view'))->toBe('audit.view')
        ->and(config('filament-activity-log.mutations.enabled'))->toBeFalse()
        ->and(config('filament-activity-log.privacy.immutable_mode'))->toBeTrue()
        ->and(config('filament-activity-log.widgets.enabled'))->toBeFalse()
        ->and(config('filament-activity-log.dashboard.enabled'))->toBeFalse()
        ->and(config('filament-activity-log.auto_context.enabled'))->toBeFalse()
        ->and(config('filament-activity-log.retention.enabled'))->toBeFalse()
        ->and(UserActivitiesPage::shouldRegisterNavigation())->toBeFalse()
        ->and(AuditDashboard::shouldRegisterNavigation())->toBeFalse()
        ->and(ActivityChartWidget::canView())->toBeFalse()
        ->and(LatestActivityWidget::canView())->toBeFalse();
});

test('existing models do not enable uncontrolled activity logging', function () {
    expect(class_uses_recursive(User::class))->not->toContain(LogsActivity::class)
        ->and(class_uses_recursive(AdministratorInvitation::class))->not->toContain(LogsActivity::class);
});

test('the plugin policy owns the Activity model authorization', function () {
    expect(Gate::getPolicyFor(Activity::class))->toBeInstanceOf(ActivityPolicy::class);
});

test('the audit resource is denied without the audit.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    expect(ActivityLogResource::canViewAny())->toBeFalse();
});

test('the audit resource is allowed with the audit.view permission', function () {
    $user = User::factory()->create();

    Permission::create(['name' => 'audit.view', 'guard_name' => 'web']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user->givePermissionTo('audit.view');
    $user->unsetRelation('permissions');

    $this->actingAs($user);

    expect(ActivityLogResource::canViewAny())->toBeTrue();
});

test('the plugin custom authorization takes precedence over the RBAC mapping', function () {
    $user = User::factory()->create();

    Permission::create(['name' => 'audit.view', 'guard_name' => 'web']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user->givePermissionTo('audit.view');
    $user->unsetRelation('permissions');

    $this->actingAs($user);

    config(['filament-activity-log.permissions.custom_authorization' => static fn (User $user): bool => false]);

    expect(ActivityLogResource::canViewAny())->toBeFalse();

    config(['filament-activity-log.permissions.custom_authorization' => static fn (User $user): bool => true]);

    expect(ActivityLogResource::canViewAny())->toBeTrue();
});

test('audit mutations are denied even with the audit.view permission', function () {
    $user = User::factory()->create();

    Permission::create(['name' => 'audit.view', 'guard_name' => 'web']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user->givePermissionTo('audit.view');
    $user->unsetRelation('permissions');

    $activity = new Activity;

    expect(Gate::forUser($user)->allows('update', $activity))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $activity))->toBeFalse()
        ->and(Gate::forUser($user)->allows('restore', $activity))->toBeFalse();
});

test('the clean command refuses to run without an explicit retention period', function () {
    config(['filament-activity-log.integrity.key' => 'test-only-integrity-key']);

    activity()->log('retention guard');

    expect(Artisan::call('activitylog:clean'))->toBe(1)
        ->and(Activity::query()->count())->toBe(1);
});

test('activities are persisted immediately and signed when the buffer is disabled', function () {
    config(['filament-activity-log.integrity.key' => 'test-only-integrity-key']);

    activity()->log('audit foundation integrity check');

    $stored = Activity::query()->latest('id')->firstOrFail();

    expect($stored->getKey())->not->toBeNull()
        ->and(config('activitylog.buffer.enabled'))->toBeFalse()
        ->and($stored->getAttribute('integrity_hash'))->not->toBeNull();

    expect(Artisan::call('filament-activity-log:verify-integrity'))->toBe(0);

    DB::table('activity_log')
        ->where('id', $stored->getKey())
        ->update(['description' => 'tampered']);

    expect(Artisan::call('filament-activity-log:verify-integrity'))->toBe(1);
});
