<?php

/*
|------------------------------------------------------------------------------
| RabbitMQ — SMS pipeline queues
|------------------------------------------------------------------------------
| Connection to the `rabbitmq` container (docker-compose service name). The app
| publishes outbound SMS to `sms.outbound` and DLRs to `sms.dlr`; long-running
| consumers drain them. Ported from sms_expert's RabbitMQ layer.
*/

return [

    'host'     => env('RABBITMQ_HOST', 'rabbitmq'),
    'port'     => (int) env('RABBITMQ_PORT', 5672),
    'user'     => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost'    => env('RABBITMQ_VHOST', '/'),

    'queues' => [
        'outbound' => env('RABBITMQ_SMS_OUTBOUND_QUEUE', 'sms.outbound'),
        'dlr'      => env('RABBITMQ_SMS_DLR_QUEUE', 'sms.dlr'),
    ],

    // Consumer retry/backoff
    'max_retries'  => (int) env('RABBITMQ_MAX_RETRIES', 3),
    'retry_base_s' => (int) env('RABBITMQ_RETRY_BASE_SECONDS', 10),

    // Heartbeat + prefetch
    'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 30),
    'prefetch'  => (int) env('RABBITMQ_PREFETCH', 5),

];
