<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RabbitMQ Connection Configuration
    |--------------------------------------------------------------------------
    */
    
    'host' => env('RABBITMQ_HOST', 'localhost'),
    'port' => env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    
    /*
    |--------------------------------------------------------------------------
    | Queue Names
    |--------------------------------------------------------------------------
    */
    
    'queues' => [
        'sms' => env('RABBITMQ_SMS_QUEUE', 'sms.outbound'),
        'priority' => env('RABBITMQ_PRIORITY_QUEUE', 'sms.priority'),
        'failed' => env('RABBITMQ_FAILED_QUEUE', 'sms.failed'),
        'dlr' => env('RABBITMQ_DLR_QUEUE', 'sms.dlr'),
        'inbound' => env('RABBITMQ_INBOUND_QUEUE', 'sms.inbound'),
        'webhook_dlr' => env('RABBITMQ_WEBHOOK_DLR_QUEUE', 'webhook.dlr'),
        'webhook_inbound' => env('RABBITMQ_WEBHOOK_INBOUND_QUEUE', 'webhook.inbound'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */
    
    'retry' => [
        'max_attempts' => env('RABBITMQ_MAX_RETRIES', 3),
        'initial_delay' => env('RABBITMQ_INITIAL_RETRY_DELAY', 10), // seconds
        'max_delay' => env('RABBITMQ_MAX_RETRY_DELAY', 300), // 5 minutes
        'backoff_multiplier' => env('RABBITMQ_BACKOFF_MULTIPLIER', 2),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Message TTL
    |--------------------------------------------------------------------------
    */
    
    'message_ttl' => [
        'default' => env('RABBITMQ_MESSAGE_TTL', 86400000), // 24 hours in ms
        'failed' => env('RABBITMQ_FAILED_TTL', 300000), // 5 minutes in ms
        'priority' => env('RABBITMQ_PRIORITY_TTL', 3600000), // 1 hour in ms
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Consumer Configuration
    |--------------------------------------------------------------------------
    */
    
    'consumer' => [
        'prefetch_count' => env('RABBITMQ_PREFETCH_COUNT', 1),
        'timeout' => env('RABBITMQ_CONSUMER_TIMEOUT', 0), // 0 = infinite
    ],
];
