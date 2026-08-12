<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Heartbeat Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the SMS heartbeat system.
    |
    */

    'enabled' => env('HEARTBEAT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    |
    | Credentials for sending heartbeat SMS messages.
    |
    */

    'api_user' => env('HEARTBEAT_API_USER', 'heartbeat'),
    'api_password' => env('HEARTBEAT_API_PASSWORD', ''),
    'account_bigid' => env('HEARTBEAT_ACCOUNT_BIGID', '6641b01402fe76dd6656c16bc9c38700'),

    /*
    |--------------------------------------------------------------------------
    | Default Route
    |--------------------------------------------------------------------------
    |
    | Default SMS route for heartbeat messages.
    | 'l' = mBird-Blend, 'e' = CSN-Direct
    |
    */

    'default_route' => env('HEARTBEAT_DEFAULT_ROUTE', 'l'),

    /*
    |--------------------------------------------------------------------------
    | Network SIM Numbers
    |--------------------------------------------------------------------------
    |
    | Phone numbers for each network used for heartbeat testing.
    |
    */

    'sims' => [
        'vodafone' => env('HEARTBEAT_SIM_VODAFONE', '447493870606'),
        'o2' => env('HEARTBEAT_SIM_O2', '447926477918'),
        'ee' => env('HEARTBEAT_SIM_EE', '447415523379'),
        'three' => env('HEARTBEAT_SIM_THREE', '447479443491'),
        'orange' => env('HEARTBEAT_SIM_ORANGE', '447976606687'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Recipients
    |--------------------------------------------------------------------------
    |
    | Email addresses for heartbeat alerts.
    |
    */

    'internal_alert_recipients' => array_filter(
        array_map('trim', explode(',', env('HEARTBEAT_INTERNAL_RECIPIENTS', 'anand@nedholdings.com')))
    ),

    'client_alert_recipients' => array_filter(
        array_map('trim', explode(',', env('HEARTBEAT_CLIENT_RECIPIENTS', 'anand@nedholdings.com')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Operating Hours
    |--------------------------------------------------------------------------
    |
    | Hours during which heartbeat should run (24-hour format).
    |
    */

    'start_hour' => env('HEARTBEAT_START_HOUR', 6),
    'end_hour' => env('HEARTBEAT_END_HOUR', 21),

];
