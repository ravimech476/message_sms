<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sinch SMS API Configuration
    |--------------------------------------------------------------------------
    | Used for batch SMS sending via Sinch SMS API
    | Get these from: https://dashboard.sinch.com -> SMS -> API Overview
    */
    'service_plan_id' => env('SINCH_SERVICE_PLAN_ID', ''),
    'api_token'       => env('SINCH_API_TOKEN', ''),
    'base_url'        => env('SINCH_BASE_URL', 'https://sms.api.sinch.com/xms/v1/'),
    'sender'          => env('SINCH_SENDER', 'SENDER_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Sinch Numbers API Configuration
    |--------------------------------------------------------------------------
    | Used for virtual number management (search, rent, release numbers)
    | Get these from: https://dashboard.sinch.com -> Your Project -> Access Keys
    |
    | API Documentation: https://developers.sinch.com/docs/numbers/getting-started
    |
    | Note: The REDHOT API is deprecated. Use the new Numbers API instead.
    */
    'numbers_project_id' => env('SINCH_NUMBERS_PROJECT_ID', ''),
    'numbers_key_id'     => env('SINCH_NUMBERS_KEY_ID', ''),
    'numbers_key_secret' => env('SINCH_NUMBERS_KEY_SECRET', ''),
    'numbers_base_url'   => env('SINCH_NUMBERS_BASE_URL', 'https://numbers.api.sinch.com'),

    /*
    |--------------------------------------------------------------------------
    | Sinch Inbound SMS Webhook URL
    |--------------------------------------------------------------------------
    | This URL will be configured on Sinch numbers for receiving inbound SMS.
    | Must be a publicly accessible HTTPS URL.
    | If not set, will auto-generate from APP_URL + /api/webhook/inbound-sms
    */
    'inbound_webhook_url' => env('SINCH_INBOUND_WEBHOOK_URL', ''),
];
