<?php

return [
    'domain_controller' => env('LDAP_DOMAIN_CONTROLLER'), // Server domain (ip or domain)
    'port' => env('LDAP_PORT'), // Port number
    'base_dn' => env('LDAP_BASE_DN'), // BASE_DN, can add multiple
    'ssl' => env('LDAP_SSL', false), // Secure access
    'bind_dn' => env('LDAP_BIND_DN'), // Bind DN
    'bind_dn_password' => env('LDAP_BIND_DN_PASSWORD'), // Bind DN Password
    'user_name_attribute' => env('LDAP_USER_NAME_ATTRIBUTE'), // The attribute containing user detail to store ie uid, mail, sAMAccountName
];
