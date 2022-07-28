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
];
