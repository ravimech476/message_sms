<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email Notifications
    |--------------------------------------------------------------------------
    |
    | Enable or disable exception email notifications
    |
    */

    'email_enabled' => env('EXCEPTION_EMAIL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Email Recipients
    |--------------------------------------------------------------------------
    |
    | List of email addresses to receive exception notifications
    | Can be comma-separated in .env or an array
    |
    */

    'email_recipients' => is_string(env('EXCEPTION_EMAIL_RECIPIENTS'))
        ? array_map('trim', explode(',', env('EXCEPTION_EMAIL_RECIPIENTS', '')))
        : (array) env('EXCEPTION_EMAIL_RECIPIENTS', []),

    /*
    |--------------------------------------------------------------------------
    | Production Only
    |--------------------------------------------------------------------------
    |
    | Only send exception emails in production/staging environments
    |
    */

    'email_only_production' => env('EXCEPTION_EMAIL_ONLY_PRODUCTION', false),

    /*
    |--------------------------------------------------------------------------
    | Email Throttling
    |--------------------------------------------------------------------------
    |
    | Maximum number of exception emails to send per minute
    | This prevents email flooding in case of repeated errors
    |
    */

    'email_throttle' => env('EXCEPTION_EMAIL_THROTTLE', 5),

    /*
    |--------------------------------------------------------------------------
    | SMS Notifications (Optional)
    |--------------------------------------------------------------------------
    |
    | Enable SMS notifications for critical exceptions
    |
    */

    'sms_enabled' => env('EXCEPTION_SMS_ENABLED', false),

    'sms_recipients' => is_string(env('EXCEPTION_SMS_RECIPIENTS'))
        ? array_map('trim', explode(',', env('EXCEPTION_SMS_RECIPIENTS', '')))
        : (array) env('EXCEPTION_SMS_RECIPIENTS', []),

    'sms_sender' => env('EXCEPTION_SMS_SENDER', 'SMS_EXPERT'),

    'sms_only_production' => env('EXCEPTION_SMS_ONLY_PRODUCTION', true),

    /*
    |--------------------------------------------------------------------------
    | SMS Throttling
    |--------------------------------------------------------------------------
    |
    | Rate limiting for SMS notifications
    |
    */

    'sms_throttle_minutes' => env('EXCEPTION_SMS_THROTTLE_MINUTES', 5),
    'sms_throttle_max' => env('EXCEPTION_SMS_THROTTLE_MAX', 3),

    /*
    |--------------------------------------------------------------------------
    | Skip Types
    |--------------------------------------------------------------------------
    |
    | Exception types to skip for SMS notifications
    |
    */

    'sms_skip_types' => is_string(env('EXCEPTION_SMS_SKIP_TYPES'))
        ? array_map('trim', explode(',', env('EXCEPTION_SMS_SKIP_TYPES', '')))
        : (array) env('EXCEPTION_SMS_SKIP_TYPES', [
            'Illuminate\Auth\AuthenticationException',
            'Illuminate\Validation\ValidationException',
            'Illuminate\Session\TokenMismatchException',
        ]),

];
