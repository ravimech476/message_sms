<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pooled Virtuals Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the pooled virtuals monitoring system.
    |
    */

    'enabled' => env('POOLEDVIRTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Alert Recipients
    |--------------------------------------------------------------------------
    |
    | Email addresses for pooled virtuals alerts.
    |
    */

    'alert_recipients' => array_filter(
        array_map('trim', explode(',', env('POOLEDVIRTS_ALERT_RECIPIENTS', 'anand@nedholdings.com')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Operating Hours
    |--------------------------------------------------------------------------
    |
    | Hours during which fund sweeping should run (24-hour format).
    |
    */

    'sweep_start_hour' => env('POOLEDVIRTS_SWEEP_START_HOUR', 2),
    'sweep_end_hour' => env('POOLEDVIRTS_SWEEP_END_HOUR', 23),

    /*
    |--------------------------------------------------------------------------
    | Pool Check Hour
    |--------------------------------------------------------------------------
    |
    | Hour at which to run the daily pool virtuals check.
    |
    */

    'pool_check_hour' => env('POOLEDVIRTS_POOL_CHECK_HOUR', 6),

    /*
    |--------------------------------------------------------------------------
    | Maximum Iterations
    |--------------------------------------------------------------------------
    |
    | Maximum number of accounts to process in one run to prevent infinite loops.
    |
    */

    'max_iterations' => env('POOLEDVIRTS_MAX_ITERATIONS', 1000),

];
