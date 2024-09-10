<?php

return [

    /*
    |--------------------------------------------------------------------------
    | When setting up Epicollect5 for the first time, a Super Admin user needs
    | to be created.
    */

    'super_admin_user' => [
        'first_name' => env('SUPER_ADMIN_FIRST_NAME'),
        'last_name' => env('SUPER_ADMIN_LAST_NAME'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD')
    ],
    'system' => [
        'email' => env('SYSTEM_EMAIL'),
        'app_link_enabled' => (bool) env('APP_LINK_ENABLED', false)
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
    'cost_x_gb' => env('COST_X_GB'),
    'storage_available_min_threshold' => env('STORAGE_AVAILABLE_MIN_THRESHOLD', 50),
    'api' => [
        'responseContentTypeHeaderKey' => 'Content-Type',
        'responseContentTypeHeaderValue' => 'application/vnd.api+json; charset=utf-8',
        'response_delay' => [
            'media' => env('RESPONSE_DELAY_MEDIA_REQUEST', 250000000),
            'upload' => env('RESPONSE_DELAY_UPLOAD_REQUEST', 500000000)
        ]
    ],
    'ldap' => [
        'domain_controller' => env('LDAP_DOMAIN_CONTROLLER'), // Server domain (ip or domain)
        'port' => env('LDAP_PORT'), // Port number
        'base_dn' => env('LDAP_BASE_DN'), // BASE_DN, can add multiple
        'ssl' => env('LDAP_SSL', false), // Secure access
        'bind_dn' => env('LDAP_BIND_DN'), // Bind DN
        'bind_dn_password' => env('LDAP_BIND_DN_PASSWORD'), // Bind DN Password
        'user_name_attribute' => env('LDAP_USER_NAME_ATTRIBUTE'), // The attribute containing user detail to store ie uid, mail, sAMAccountName
    ],
    'bulk_deletion' => [
        'chunk_size' => env('BULK_DELETION_CHUNK_SIZE', 100),
    ]
];
