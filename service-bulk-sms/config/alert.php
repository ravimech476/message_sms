<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application error alerts
    |--------------------------------------------------------------------------
    | Emails the admin when the application throws an unhandled exception
    | (web request, queue job, console command, or a worker). Throttled so one
    | recurring error sends one email per window, not thousands.
    */

    'enabled' => env('ADMIN_ALERT_ENABLED', false),

    // Recipient(s) — comma-separated for more than one.
    'email' => env('ADMIN_ALERT_EMAIL'),

    // One email per unique error (class+file+line+message) per this many minutes.
    'throttle_minutes' => env('ADMIN_ALERT_THROTTLE_MINUTES', 15),

];
