<?php

use Spatie\Activitylog\Actions\CleanActivityLogAction;
use Spatie\Activitylog\Actions\LogActivityAction;
use Spatie\Activitylog\Models\Activity;

return [

    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITYLOG_ENABLED', true),

    /*
     * No default retention period is configured. The clean command must receive
     * an explicit --days value until the retention policy is arbitrated.
     */
    'clean_after_days' => null,

    /*
     * If no log name is passed to the activity() helper
     * we use this default log name.
     */
    'default_log_name' => 'audit',

    /*
     * You can specify an auth driver here that gets user models.
     * If this is null we'll use the current Laravel auth driver.
     */
    'default_auth_driver' => null,

    /*
     * If set to true, the subject relationship on activities
     * will include soft deleted models.
     */
    'include_soft_deleted_subjects' => false,

    /*
     * This model will be used to log activity.
     * It should implement the Spatie\Activitylog\Contracts\Activity interface
     * and extend Illuminate\Database\Eloquent\Model.
     */
    'activity_model' => Activity::class,

    /*
     * Safety net only: the audit layer will apply an explicit allow-list in
     * Lot 1B. These exclusions prevent accidental secret persistence in the
     * meantime and must never be used as the primary confidentiality control.
     */
    'default_except_attributes' => [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'remember_token',
        'token',
        'token_hash',
        'api_token',
        'access_token',
        'refresh_token',
        'secret',
        'api_key',
        'private_key',
        'authorization',
        'cookie',
        'iban',
        'bic',
        'bank_account',
        'rib',
        'account_number',
        'routing_number',
        'nir',
        'social_security_number',
        'ssn',
        'medical_data',
        'health_data',
        'diagnosis',
        'sick_leave',
    ],

    /*
     * When enabled, activities are buffered in memory and inserted in a
     * single bulk query after the response has been sent to the client.
     * This can significantly reduce the number of database queries when
     * many activities are logged during a single request.
     *
     * Only enable this if your application logs a high volume of activities
     * per request. Buffered activities will not have an ID until the
     * buffer is flushed.
     */
    'buffer' => [
        // Buffered inserts bypass model events, which would prevent the
        // Filament Activity Log metadata (risk, integrity hash, context).
        'enabled' => false,
    ],

    /*
     * These action classes can be overridden to customize how activities
     * are logged and cleaned. Your custom classes must extend the originals.
     */
    'actions' => [
        'log_activity' => LogActivityAction::class,
        'clean_log' => CleanActivityLogAction::class,
    ],
];
