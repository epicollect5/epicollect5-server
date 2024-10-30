<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, Mandrill, and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.eu.mailgun.net'),
        'scheme' => 'https',
        'zone' => env('MAILGUN_ZONE'),
    ],

    //Google credentials for web auth
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_LOGIN_REDIRECT_URI')
    ],

    //Google credentials mobile auth
    'google_api' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        //this redirect allows the mobile app to be redirected to localhost
        //without it, Socialite will throw an error
        'redirect' => env('GOOGLE_LOGIN_REDIRECT_URI_API', 'http://localhost'),
        //todo: not sure we are still using the scope
        'scope' => env('GOOGLE_SCOPE')
    ]
];
