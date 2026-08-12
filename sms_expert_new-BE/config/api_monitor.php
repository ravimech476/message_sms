<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Error Monitor Configuration
    |--------------------------------------------------------------------------
    |
    | Configure email notifications for Mobile API errors
    |
    */

    // Enable/disable email notifications
    'email_enabled' => env('API_MONITOR_EMAIL_ENABLED', true),

    // Email recipients (comma-separated in .env)
    'email_recipients' => array_filter(explode(',', env('API_MONITOR_EMAIL_RECIPIENTS', ''))),

    // Maximum emails per hour (rate limiting)
    'max_emails_per_hour' => env('API_MONITOR_MAX_EMAILS_HOUR', 10),

    // Minimum severity level to send email
    // Options: low, medium, high, critical
    'min_email_severity' => env('API_MONITOR_MIN_SEVERITY', 'high'),

    // Log to database
    'log_to_database' => env('API_MONITOR_LOG_DATABASE', true),

    // Log retention days (for database cleanup)
    'log_retention_days' => env('API_MONITOR_RETENTION_DAYS', 30),

    // Excluded paths (don't monitor these)
    'excluded_paths' => [
        'api/mobile/auth/check', // Health check endpoint
    ],

    // Excluded status codes (don't send email for these)
    'excluded_status_codes' => [
        401, // Unauthorized (expected for invalid tokens)
        422, // Validation errors (expected user input errors)
    ],

    // Excluded exception classes (don't send email for these)
    'excluded_exceptions' => [
        \Illuminate\Validation\ValidationException::class,
        \Illuminate\Auth\AuthenticationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],

];
