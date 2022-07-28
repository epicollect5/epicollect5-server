<?php


return [
    'server' => [
        // The server roles that can perform actions on other server roles
        'roles' => [
            'superadmin' => ['admin', 'basic'],
            'admin' => ['basic'],
            'basic' => []
        ]
    ],
    'projects' => [
        'roles' => [
            // The project roles that can perform actions on other project roles
            'creator' => ['manager', 'curator', 'collector', 'viewer'],
            'manager' => ['curator', 'collector', 'viewer'],
            'curator' => [],
            'collector' => [],
            'viewer' => []
        ],
        // The default project creator role
        'creator_role' => 'creator',
        'manager_role' => 'manager'
    ]
];
