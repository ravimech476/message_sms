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
