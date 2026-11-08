<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const AUDIT_CONTROL_COLUMNS = [
    'risk_score',
    'risk_level',
    'retention_hold',
    'integrity_hash',
    'request_id',
    'ip_address',
];

const AUDIT_CONTROL_INDEXES = [
    'activity_log_risk_score_index',
    'activity_log_risk_level_index',
    'activity_log_retention_hold_index',
    'activity_log_integrity_hash_index',
    'activity_log_log_created_index',
    'activity_log_event_created_index',
    'activity_log_subject_created_index',
    'activity_log_causer_created_index',
    'activity_log_risk_created_index',
    'activity_log_request_created_index',
    'activity_log_ip_created_index',
];

function resetAuditControlSchema(): void
{
    $indexes = array_values(array_filter(
        AUDIT_CONTROL_INDEXES,
        static fn (string $index): bool => Schema::hasIndex('activity_log', $index),
    ));

    if ($indexes !== []) {
        Schema::table('activity_log', function (Blueprint $table) use ($indexes): void {
            foreach ($indexes as $index) {
                $table->dropIndex($index);
            }
        });
    }

    $columns = array_values(array_filter(
        AUDIT_CONTROL_COLUMNS,
        static fn (string $column): bool => Schema::hasColumn('activity_log', $column),
    ));

    if ($columns !== []) {
        Schema::table('activity_log', function (Blueprint $table) use ($columns): void {
            foreach ($columns as $column) {
                $table->dropColumn($column);
            }
        });
    }

    Schema::dropIfExists('activity_log_audit_control_objects');
}

function auditControlMigration(): Migration
{
    $migration = require database_path('migrations/2026_11_08_160744_upgrade_activity_log_with_audit_control_columns.php');

    if (! $migration instanceof Migration) {
        throw new RuntimeException('The audit control migration could not be loaded.');
    }

    return $migration;
}

function runAuditControlMigration(Migration $migration, string $method): void
{
    (new ReflectionMethod($migration, $method))->invoke($migration);
}

function baseActivityLogMigration(): Migration
{
    $migration = require database_path('migrations/2026_11_08_160743_create_activity_log_table.php');

    if (! $migration instanceof Migration) {
        throw new RuntimeException('The Activitylog base migration could not be loaded.');
    }

    return $migration;
}

beforeEach(function () {
    resetAuditControlSchema();
});

test('a fresh installation creates only the owned columns, indexes and marker', function () {
    $migration = auditControlMigration();

    runAuditControlMigration($migration, 'up');

    foreach (AUDIT_CONTROL_COLUMNS as $column) {
        expect(Schema::hasColumn('activity_log', $column))->toBeTrue();
    }

    foreach (AUDIT_CONTROL_INDEXES as $index) {
        expect(Schema::hasIndex('activity_log', $index))->toBeTrue();
    }

    expect(Schema::hasTable('activity_log_audit_control_objects'))->toBeTrue()
        ->and(Schema::getConnection()->table('activity_log_audit_control_objects')->count())->toBe(17);
});

test('a normal rollback removes only the objects created by the migration', function () {
    $migration = auditControlMigration();
    runAuditControlMigration($migration, 'up');
    runAuditControlMigration($migration, 'down');

    foreach (AUDIT_CONTROL_COLUMNS as $column) {
        expect(Schema::hasColumn('activity_log', $column))->toBeFalse();
    }

    foreach (AUDIT_CONTROL_INDEXES as $index) {
        expect(Schema::hasIndex('activity_log', $index))->toBeFalse();
    }

    expect(Schema::hasTable('activity_log_audit_control_objects'))->toBeFalse();
});

test('a rollback never removes a column that existed before the migration', function () {
    Schema::table('activity_log', function (Blueprint $table): void {
        $table->unsignedTinyInteger('risk_score')->nullable();
        $table->string('request_id', 100)->nullable();
    });

    $migration = auditControlMigration();
    runAuditControlMigration($migration, 'up');
    runAuditControlMigration($migration, 'down');

    expect(Schema::hasColumn('activity_log', 'risk_score'))->toBeTrue()
        ->and(Schema::hasColumn('activity_log', 'request_id'))->toBeTrue()
        ->and(Schema::hasColumn('activity_log', 'risk_level'))->toBeFalse()
        ->and(Schema::hasColumn('activity_log', 'retention_hold'))->toBeFalse()
        ->and(Schema::hasColumn('activity_log', 'integrity_hash'))->toBeFalse()
        ->and(Schema::hasColumn('activity_log', 'ip_address'))->toBeFalse()
        ->and(Schema::hasTable('activity_log_audit_control_objects'))->toBeFalse();

    resetAuditControlSchema();
});

test('a rollback never removes a plugin-named index that existed before the migration', function () {
    Schema::table('activity_log', function (Blueprint $table): void {
        $table->unsignedTinyInteger('risk_score')->nullable();
        $table->index(['risk_score'], 'activity_log_risk_score_index');
    });

    $migration = auditControlMigration();
    runAuditControlMigration($migration, 'up');
    runAuditControlMigration($migration, 'down');

    expect(Schema::hasColumn('activity_log', 'risk_score'))->toBeTrue()
        ->and(Schema::hasIndex('activity_log', 'activity_log_risk_score_index'))->toBeTrue()
        ->and(Schema::hasColumn('activity_log', 'risk_level'))->toBeFalse();

    resetAuditControlSchema();
});

test('a rollback without ownership marker leaves all existing columns untouched', function () {
    $migration = auditControlMigration();
    runAuditControlMigration($migration, 'up');

    Schema::dropIfExists('activity_log_audit_control_objects');

    runAuditControlMigration($migration, 'down');

    foreach (AUDIT_CONTROL_COLUMNS as $column) {
        expect(Schema::hasColumn('activity_log', $column))->toBeTrue();
    }

    expect(Schema::hasTable('activity_log_audit_control_objects'))->toBeFalse();

    resetAuditControlSchema();
});

test('the migration fails explicitly when the activity log table is missing', function () {
    Schema::dropIfExists('activity_log');

    $migration = auditControlMigration();

    expect(fn () => runAuditControlMigration($migration, 'up'))
        ->toThrow(RuntimeException::class, 'Activitylog migration first');

    runAuditControlMigration(baseActivityLogMigration(), 'up');
});

test('the migration can be run again after a normal rollback', function () {
    $migration = auditControlMigration();

    runAuditControlMigration($migration, 'up');
    runAuditControlMigration($migration, 'down');
    runAuditControlMigration($migration, 'up');

    foreach (AUDIT_CONTROL_COLUMNS as $column) {
        expect(Schema::hasColumn('activity_log', $column))->toBeTrue();
    }

    expect(Schema::hasTable('activity_log_audit_control_objects'))->toBeTrue();
});
