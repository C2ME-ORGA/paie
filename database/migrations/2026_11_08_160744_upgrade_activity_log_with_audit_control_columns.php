<?php

declare(strict_types=1);

use AlizHarb\ActivityLog\Exceptions\InvalidConfigurationException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

return new class extends Migration
{
    /**
     * Tracks the schema objects this migration actually created.
     *
     * Laravel does not remember whether a conditional column existed before a
     * migration ran. This table is therefore the only reliable rollback
     * ownership marker. It is created on the activity connection and removed
     * by down().
     */
    private const OWNERSHIP_TABLE = 'activity_log_audit_control_objects';

    public function up(): void
    {
        $modelClass = config('activitylog.activity_model', Activity::class);

        if (! is_string($modelClass) || ! is_a($modelClass, Activity::class, true)) {
            throw new InvalidConfigurationException('configuration.activity_model');
        }

        $activity = new $modelClass;
        $schema = Schema::connection($activity->getConnectionName());
        $tableName = $activity->getTable();

        if (! $schema->hasTable($tableName)) {
            throw new RuntimeException(
                "Cannot install audit control columns: table [{$tableName}] is missing. Run the Activitylog migration first.",
            );
        }

        $this->ensureOwnershipTable($schema);

        $columnsToCreate = array_values(array_filter(
            array_keys($this->columns()),
            static fn (string $column): bool => ! $schema->hasColumn($tableName, $column),
        ));

        $indexesToCreate = array_values(array_filter(
            array_keys($this->indexes()),
            static fn (string $index): bool => ! $schema->hasIndex($tableName, $index),
        ));

        // Record ownership before DDL. If a DDL statement fails, down() still
        // checks actual existence before dropping anything.
        $this->recordOwnership($schema, $columnsToCreate, $indexesToCreate);

        $schema->table($tableName, function (Blueprint $table) use ($columnsToCreate): void {
            $this->addColumns($table, $columnsToCreate);
        });

        $schema->table($tableName, function (Blueprint $table) use ($indexesToCreate): void {
            $this->addIndexes($table, $indexesToCreate);
        });
    }

    public function down(): void
    {
        $modelClass = config('activitylog.activity_model', Activity::class);

        if (! is_string($modelClass) || ! is_a($modelClass, Activity::class, true)) {
            throw new InvalidConfigurationException('configuration.activity_model');
        }

        $activity = new $modelClass;
        $schema = Schema::connection($activity->getConnectionName());
        $tableName = $activity->getTable();

        // Installations migrated before this ownership marker existed are
        // intentionally conservative: without proof of ownership, columns are
        // left untouched.
        if (! $schema->hasTable(self::OWNERSHIP_TABLE)) {
            return;
        }

        if (! $schema->hasTable($tableName)) {
            $schema->dropIfExists(self::OWNERSHIP_TABLE);

            return;
        }

        $ownedObjects = $schema->getConnection()
            ->table(self::OWNERSHIP_TABLE)
            ->get(['object_type', 'object_name']);

        $ownedIndexes = $ownedObjects
            ->where('object_type', 'index')
            ->pluck('object_name')
            ->filter(static fn (mixed $name): bool => is_string($name))
            ->values()
            ->all();

        $ownedColumns = $ownedObjects
            ->where('object_type', 'column')
            ->pluck('object_name')
            ->filter(static fn (mixed $name): bool => is_string($name))
            ->values()
            ->all();

        $schema->table($tableName, function (Blueprint $table) use ($schema, $tableName, $ownedIndexes): void {
            foreach ($ownedIndexes as $indexName) {
                if ($schema->hasIndex($tableName, $indexName)) {
                    $table->dropIndex($indexName);
                }
            }
        });

        $schema->table($tableName, function (Blueprint $table) use ($schema, $tableName, $ownedColumns): void {
            foreach ($ownedColumns as $columnName) {
                if ($schema->hasColumn($tableName, $columnName)) {
                    $table->dropColumn($columnName);
                }
            }
        });

        $schema->dropIfExists(self::OWNERSHIP_TABLE);
    }

    private function ensureOwnershipTable(Builder $schema): void
    {
        if ($schema->hasTable(self::OWNERSHIP_TABLE)) {
            return;
        }

        $schema->create(self::OWNERSHIP_TABLE, function (Blueprint $table): void {
            $table->string('object_type', 16);
            $table->string('object_name', 100);
            $table->primary(['object_type', 'object_name']);
        });
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $indexes
     */
    private function recordOwnership(
        Builder $schema,
        array $columns,
        array $indexes,
    ): void {
        $rows = [];

        foreach ($columns as $column) {
            $rows[] = ['object_type' => 'column', 'object_name' => $column];
        }

        foreach ($indexes as $index) {
            $rows[] = ['object_type' => 'index', 'object_name' => $index];
        }

        if ($rows === []) {
            return;
        }

        $schema->getConnection()
            ->table(self::OWNERSHIP_TABLE)
            ->insertOrIgnore($rows);
    }

    /**
     * @param  list<string>  $columns
     */
    private function addColumns(Blueprint $table, array $columns): void
    {
        foreach ($columns as $column) {
            match ($column) {
                'risk_score' => $table->unsignedTinyInteger('risk_score')->nullable(),
                'risk_level' => $table->string('risk_level', 16)->nullable(),
                'retention_hold' => $table->boolean('retention_hold')->default(false),
                'integrity_hash' => $table->char('integrity_hash', 64)->nullable(),
                'request_id' => $table->string('request_id', 100)->nullable(),
                'ip_address' => $table->string('ip_address', 45)->nullable(),
                default => null,
            };
        }
    }

    /**
     * @param  list<string>  $indexes
     */
    private function addIndexes(Blueprint $table, array $indexes): void
    {
        foreach ($indexes as $index) {
            match ($index) {
                'activity_log_risk_score_index' => $table->index(['risk_score'], 'activity_log_risk_score_index'),
                'activity_log_risk_level_index' => $table->index(['risk_level'], 'activity_log_risk_level_index'),
                'activity_log_retention_hold_index' => $table->index(['retention_hold'], 'activity_log_retention_hold_index'),
                'activity_log_integrity_hash_index' => $table->index(['integrity_hash'], 'activity_log_integrity_hash_index'),
                'activity_log_log_created_index' => $table->index(['log_name', 'created_at'], 'activity_log_log_created_index'),
                'activity_log_event_created_index' => $table->index(['event', 'created_at'], 'activity_log_event_created_index'),
                'activity_log_subject_created_index' => $table->index(['subject_type', 'subject_id', 'created_at'], 'activity_log_subject_created_index'),
                'activity_log_causer_created_index' => $table->index(['causer_type', 'causer_id', 'created_at'], 'activity_log_causer_created_index'),
                'activity_log_risk_created_index' => $table->index(['risk_level', 'created_at'], 'activity_log_risk_created_index'),
                'activity_log_request_created_index' => $table->index(['request_id', 'created_at'], 'activity_log_request_created_index'),
                'activity_log_ip_created_index' => $table->index(['ip_address', 'created_at'], 'activity_log_ip_created_index'),
                default => null,
            };
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function columns(): array
    {
        return [
            'risk_score' => [],
            'risk_level' => [],
            'retention_hold' => [],
            'integrity_hash' => [],
            'request_id' => [],
            'ip_address' => [],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function indexes(): array
    {
        return [
            'activity_log_risk_score_index' => ['risk_score'],
            'activity_log_risk_level_index' => ['risk_level'],
            'activity_log_retention_hold_index' => ['retention_hold'],
            'activity_log_integrity_hash_index' => ['integrity_hash'],
            'activity_log_log_created_index' => ['log_name', 'created_at'],
            'activity_log_event_created_index' => ['event', 'created_at'],
            'activity_log_subject_created_index' => ['subject_type', 'subject_id', 'created_at'],
            'activity_log_causer_created_index' => ['causer_type', 'causer_id', 'created_at'],
            'activity_log_risk_created_index' => ['risk_level', 'created_at'],
            'activity_log_request_created_index' => ['request_id', 'created_at'],
            'activity_log_ip_created_index' => ['ip_address', 'created_at'],
        ];
    }
};
