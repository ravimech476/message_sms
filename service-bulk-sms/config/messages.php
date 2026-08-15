<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Encryption key
    |--------------------------------------------------------------------------
    */
    'defuse_key' => env('DEFUSE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | SMPP send mode
    |--------------------------------------------------------------------------
    | true  = publish to RabbitMQ (sms.outbound); the smpp:consume binder sends
    |         it (production parity — decoupled, scalable).
    | false = the worker opens an SMPP bind and sends inline (simple, no binder).
    */
    'smpp_async' => env('SMPP_ASYNC_PUBLISH', true),

    /*
    |--------------------------------------------------------------------------
    | Message providers
    |--------------------------------------------------------------------------
    */
    'providers' => [

        'vonage' => [
            'name' => 'Vonage SMS',
            'driver' => 'vonage',
            'required_credentials' => [
                'api_key',
                'api_secret',
            ]
        ],

        'bt' => [
            'name' => 'BT Smart Messaging ',
            'driver' => 'bt',
            'required_credentials' => [
                'username',
                'password',
            ]
        ],

    ],

];
