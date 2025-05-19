<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application. You may change these defaults
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'session_users'
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | here which uses session storage and the Eloquent user provider.
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | Supported: "web", "api_internal", "api_external"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api_internal' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api_external' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | If you have multiple user tables or models you may configure multiple
    | sources which represent each model / table. These sources may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent", "jwt"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => \ec5\Models\User\User::class,
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | JWT key
    |--------------------------------------------------------------------------
    | The JWT key used system wide
    | The expiry time set for JWT tokens
    */

    'jwt' => [
        'secret_key' => env('APP_KEY'),
        'expire' => (int) env('JWT_EXPIRE', 7776000)
    ],
    'jwt-forgot' => [
        'secret_key' => env('APP_KEY'),
        'expire' => (int) env('JWT_FORGOT_EXPIRE', 3600)
    ],
    'jwt-passwordless' => [
        'secret_key' => env('APP_KEY'),
        'expire' => (int) env('JWT_PASSWORDLESS_EXPIRE', 86400)
    ],

    'passwordless_token_expire' => (int) env('PASSWORDLESS_TOKEN_EXPIRES_IN', 1800),
    /*
    |--------------------------------------------------------------------------
    | Passport
    |--------------------------------------------------------------------------
    | The expiry time set for Passport access tokens
    */
    'passport' => [
        'expire' => 7200
    ],
    'account_code' => [
        'expire' => (int) env('ACCOUNT_CODE_EXPIRES_IN', 7200)
    ],
    'account_unverified' => [
        'expire' => (int) env('ACCOUNT_UNVERIFIED_EXPIRES_IN', 3)
    ],
    /*
    |--------------------------------------------------------------------------
    | Auth Methods
    |--------------------------------------------------------------------------
    | The authentication methods available for users
    | Supported: "local", "google", "ldap", "apple", "passwordless"
    */

    'auth_methods' => explode(',', env('AUTH_METHODS') ?? ''),


    /**
     * Whether the mobile app clients can use the local auth api routes
     * Useful when building custom apk with only local users
     */
    'auth_api_local_enabled' => env('AUTH_API_LOCAL_ENABLED', false),

    /**
     * Whether authentication is enabled on the web server
     * Useful when testing apk(s) with a test server
     * When enabled, users cannot login on the web server
     * Login page is not shown
     */
    'auth_web_enabled' => env('AUTH_WEB_ENABLED', true),

    'ip_whitelist' => explode(',', env('IP_WHITELIST') ?? ''),

    'bcrypt_rounds' => (int) env('BCRYPT_ROUNDS', 12),

    'google' => [
        'connect_redirect_uri' => env('APP_URL').'/profile/connect-google-callback',
        'recaptcha_site_key' => env('GOOGLE_RECAPTCHA_SITE_KEY')
    ],
    //Apple redirect does not work with ip or localhost, and must be secure https://
    //They are defined at https://developer.apple.com/account/resources/identifiers
    'apple' => [
        'public_keys_endpoint' => env('APPLE_PUBLIC_KEYS_ENDPOINT'),
        'login_client_id' => env('APPLE_LOGIN_CLIENT_ID'),
        'login_redirect_uri' => env('APP_URL').'/handle/apple',
        'connect_client_id' => env('APPLE_CONNECT_CLIENT_ID'),
        'connect_redirect_uri' => env('APP_URL').'/profile/connect-apple-callback',
    ]
];
