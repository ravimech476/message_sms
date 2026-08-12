<?php

return [

    /*
    |--------------------------------------------------------------------------
    | URL Forward Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the URL forward daemon.
    |
    */

    'enabled' => env('URLFORWARD_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Daemon Name
    |--------------------------------------------------------------------------
    |
    | Default daemon name if not specified.
    |
    */

    'default_daemon' => env('URLFORWARD_DEFAULT_DAEMON', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Daemon Names
    |--------------------------------------------------------------------------
    |
    | List of daemon names to run. Each daemon processes its own queue.
    |
    */

    'daemons' => array_filter(
        array_map('trim', explode(',', env('URLFORWARD_DAEMONS', 'default')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Timeout Alert Recipients
    |--------------------------------------------------------------------------
    |
    | Email addresses for timeout alerts (internal).
    |
    */

    'timeout_alert_recipients' => array_filter(
        array_map('trim', explode(',', env('URLFORWARD_TIMEOUT_RECIPIENTS', 'anand@nedholdings.com')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Notify Clients
    |--------------------------------------------------------------------------
    |
    | List of client bigids that should receive failure notifications.
    |
    */

    'notify_clients' => array_filter(
        array_map('trim', explode(',', env('URLFORWARD_NOTIFY_CLIENTS', '')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Default Timeout
    |--------------------------------------------------------------------------
    |
    | Default timeout in seconds for HTTP requests.
    |
    */

    'default_timeout' => env('URLFORWARD_DEFAULT_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Max Rows Per Run
    |--------------------------------------------------------------------------
    |
    | Maximum number of rows to process per run.
    |
    */

    'max_rows_per_run' => env('URLFORWARD_MAX_ROWS', 100),

    /*
    |--------------------------------------------------------------------------
    | White Label Configuration
    |--------------------------------------------------------------------------
    |
    | IP addresses for white label clients.
    |
    */

    'default_whitelabel_ip' => env('URLFORWARD_DEFAULT_WHITELABEL_IP', ''),

    /*
    |--------------------------------------------------------------------------
    | White Label Client IPs
    |--------------------------------------------------------------------------
    |
    | Mapping of client bigids to their outgoing IP addresses.
    | Format: userref => IP address
    |
    */

    'whitelabel_client_ips' => [
        // 'client_bigid_here' => '1.2.3.4',
    ],

];
