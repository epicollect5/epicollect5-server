<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admin user
    |--------------------------------------------------------------------------
    |
    | When setting up Epicollect5 for the first time, a Super Admin user needs
    | to be created.
    |
    */

    'super_admin_user' => [
        'first_name' => env('SUPER_ADMIN_FIRST_NAME'),
        'last_name' => env('SUPER_ADMIN_LAST_NAME'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD')
    ],
    'system' => [
        'email' => env('SYSTEM_EMAIL')
    ],
    'opencage' => [
        'endpoint' => env('OPENCAGE_ENDPOINT'),
        'key' => env('OPENCAGE_KEY')
    ],
    'google_recaptcha' => [
        'verify_endpoint' => env('GOOGLE_RECAPTCHA_API_VERIFY_ENDPOINT'),
        'site_key' => env('GOOGLE_RECAPTCHA_SITE_KEY'),
        'secret_key' => env('GOOGLE_RECAPTCHA_SECRET_KEY')
    ],
    'ip_filtering_enabled' => env('IP_FILTERING_ENABLED'),
    'cost_x_gb' => env('COST_X_GB')
];
