<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Report Email Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the email addresses used for various reports.
    |
    */

    'email' => [
        'from_address' => env('REPORT_EMAIL_FROM', 'anand@nedholdings.com'),
        'from_name' => env('REPORT_EMAIL_FROM_NAME', 'SMS Expert'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Daily Stats Report Recipients
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of email addresses to receive the daily stats report.
    |
    */

    'daily_stats_recipients' => array_filter(
        array_map('trim', explode(',', env('REPORT_DAILY_STATS_RECIPIENTS', 'anand@nedholdings.com')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Virtual Number Expiry Report Recipients
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of email addresses to receive the virtual number
    | expiry report.
    |
    */

    'virtual_expiry_recipients' => array_filter(
        array_map('trim', explode(',', env('REPORT_VIRTUAL_EXPIRY_RECIPIENTS', 'anand@nedholdings.com')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Funds Alert Recipients
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of email addresses to receive funds alerts.
    |
    */

    'funds_alert_recipients' => array_filter(
        array_map('trim', explode(',', env('REPORT_FUNDS_ALERT_RECIPIENTS', 'anand@nedholdings.com')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Wallet Reminder Admin Report Recipients
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of email addresses to receive wallet reminder
    | admin reports (daily summary of users notified).
    |
    */

    'wallet_reminder_admin_recipients' => array_filter(
        array_map('trim', explode(',', env('REPORT_WALLET_REMINDER_ADMIN_RECIPIENTS', 'anand@nedholdings.com')))
    ),

    /*
    |--------------------------------------------------------------------------
    | SMPP Checks Recipients
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of email addresses to receive SMPP regular checks
    | status and alert reports.
    |
    */

    'smpp_checks_recipients' => array_filter(
        array_map('trim', explode(',', env('REPORT_SMPP_CHECKS_RECIPIENTS', 'anand@nedholdings.com')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Debug/Test Email Recipients
    |--------------------------------------------------------------------------
    |
    | Email address to use when running reports in debug mode.
    |
    */

    'debug_recipient' => env('REPORT_DEBUG_RECIPIENT', env('MAIL_ADMIN_EMAIL', 'anand@nedholdings.com')),

];
