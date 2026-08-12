<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sinch SMPP Configuration (formerly mBlox)
    |--------------------------------------------------------------------------
    | Based on OLD SYSTEM thisserver_details.inc pattern
    */

    'host' => env('SINCH_SMPP_HOST', 'smpp4.mblox.com'),

    'port' => env('SINCH_SMPP_PORT', 3200),

    'system_id' => env('SINCH_SMPP_SYSTEM_ID'),

    'password' => env('SINCH_SMPP_PASSWORD'),

    'sender' => env('SINCH_SMPP_SENDER'),

    /*
    |--------------------------------------------------------------------------
    | System Type (OLD SYSTEM: identifies daemon for DLR mapping)
    |--------------------------------------------------------------------------
    | Used to identify which daemon sent the message for proper DLR processing
    | Format: smppProdN where N = daemon number
    */
    'system_type' => env('SINCH_SMPP_SYSTEM_TYPE', 'smppProd1'),

    /*
    |--------------------------------------------------------------------------
    | Profile (mBlox specific)
    |--------------------------------------------------------------------------
    */
    'profile' => env('SINCH_SMPP_PROFILE', '21839'),

    /*
    |--------------------------------------------------------------------------
    | Server Failover (OLD SYSTEM: arrServers pattern)
    |--------------------------------------------------------------------------
    | Primary server is tried first, falls back to secondary if connection fails
    */
    'servers' => [
        'primary' => env('SINCH_SMPP_PRIMARY_HOST', 'smpp4.mblox.com'),
        'secondary' => env('SINCH_SMPP_SECONDARY_HOST', 'smpp4.mblox.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sequence ID Range (OLD SYSTEM: unique message ID ranges per daemon)
    |--------------------------------------------------------------------------
    | Each daemon should have a unique range to avoid message ID collisions
    | This is critical when running multiple parallel daemons
    */
    'seq_id_range' => [
        'min' => (int) env('SINCH_SMPP_SEQ_ID_MIN', 1),
        'max' => (int) env('SINCH_SMPP_SEQ_ID_MAX', 2000000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttle Settings
    |--------------------------------------------------------------------------
    */
    'throttle' => [
        'tps_limit' => (int) env('SINCH_SMPP_TPS_LIMIT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Settings
    |--------------------------------------------------------------------------
    */
    'connection' => [
        'timeout' => (int) env('SINCH_SMPP_TIMEOUT', 30),
        'keepalive' => (int) env('SINCH_SMPP_KEEPALIVE', 60),
        'recv_timeout' => (int) env('SINCH_SMPP_RECV_TIMEOUT', 10000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Bind Type
    |--------------------------------------------------------------------------
    */
    'bind_type' => env('SINCH_SMPP_BIND_TYPE', 'transceiver'),

];