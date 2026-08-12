<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Delivery Receipt Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the delivery receipt push notification system
    |
    */

    // Default daemon name if not specified
    'default_daemon' => 'default',

    // CURL timeout in seconds
    'curl_timeout' => 10,

    // Batch size for processing receipts
    'batch_size' => 1000,

    // Default wait time in minutes before retry
    'default_wait_minutes' => 5,

    /*
    |--------------------------------------------------------------------------
    | White Label Configuration
    |--------------------------------------------------------------------------
    */

    // Default IP for white label clients
    'white_label_default_ip' => null,

    // Map of user IDs to specific white label IPs
    'white_label_ips' => [
        // 'user_big_id' => 'ip_address',
        // Example:
        // '2824c339e34aac7232a3f62e532fteef' => '192.168.1.101',
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Notification Configuration
    |--------------------------------------------------------------------------
    */

    // Default recipient for failure notifications
    'default_recipient' => [
        'email' => env('DELIVERY_RECEIPT_DEFAULT_EMAIL', 'anand@nedholdings.com'),
        'name' => env('DELIVERY_RECEIPT_DEFAULT_NAME', 'SMS Expert'),
    ],

    // CC emails for all failure notifications
    'cc_emails' => [
        env('DELIVERY_RECEIPT_DEFAULT_EMAIL', 'anand@nedholdings.com'),
    ],

    // Special recipients for specific users
    'special_recipients' => [
        '2824c339e34aac7232a3f62e532fteef' => [
            'email' => 'darren.c.mason@gmail.com',
            'name' => 'Darren Mason',
        ],
        'cf3a6aa5899cakbvcsbc6jb3d595b4d5' => [
            'email' => 'darren.c.mason@gmail.com',
            'name' => 'Darren Mason',
        ],
        '7059b957f5361fd1a69fc86ba3dcc1c9' => [
            'email' => 'darren.c.mason@gmail.com',
            'name' => 'Darren Mason',
        ],
        '025c442eb0d2eda6bcac032cfafb3559' => [
            'email' => 'ian.sneddon@voiceconnect.co.uk',
            'name' => 'Ian Sneddon',
        ],
    ],

    // Users excluded from receiving notifications
    'excluded_notification_users' => [
        'efb6a09094fd7703b9051cdd7db97e00', // Steve Brown - auto-responses
        '42fb42646e9e8011f2df1b29cb7de709', // David Lynes - too many notifications
        '49853208c98005ec11a6a8afd037bab6', // Verific - don't need them
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */

    // Log channel name (define in config/logging.php)
    'log_channel' => 'delivery_receipt',

    // Enable debug logging
    'debug' => env('DELIVERY_RECEIPT_DEBUG', false),
];
