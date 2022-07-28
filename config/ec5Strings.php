<?php

return [
    'project_roles' => [
        'creator' => 'creator',
        'manager' => 'manager',
        'curator' => 'curator',
        'collector' => 'collector',
        'viewer' => 'viewer'
    ],
    'server_roles' => [
        'basic' => 'basic',
        'admin' => 'admin',
        'superadmin' => 'superadmin'
    ],
    'project' => 'project',
    'form' => 'form',
    'inputs' => 'inputs',
    'branch' => 'branch',
    'group' => 'group',
    'search' => 'search',

    'mapping' => 'mapping',

    'project_status' => [
        'active' => 'active',
        'trashed' => 'trashed',
        'locked' => 'locked'
    ],
    'project_access' => [
        'private' => 'private',
        'public' => 'public'
    ],
    'project_visibility' => [
        'hidden' => 'hidden',
        'listed' => 'listed'
    ],
    'user_state' => [
        'active' => 'active',
        'disabled' => 'disabled',
        'unverified' => 'unverified'
    ],
    'state' => 'state',
    'server_role' => 'server_role',
    'superadmin' => 'superadmin',
    'admin' => 'admin',
    'inputs_type' => [
        'text' => 'text',
        'decimal' => 'decimal',
        'integer' => 'integer',
        'date' => 'date',
        'time' => 'time',
        'dropdown' => 'dropdown',
        'radio' => 'radio',
        'checkbox' => 'checkbox',
        'searchsingle' => 'searchsingle',
        'searchmultiple' => 'searchmultiple',
        'textarea' => 'textarea',
        'location' => 'location',
        'photo' => 'photo',
        'audio' => 'audio',
        'video' => 'video',
        'barcode' => 'barcode',
        'branch' => 'branch',
        'group' => 'group',
        'readme' => 'readme',
        'phone' => 'phone'
    ],
    'entry_types' => [
        'entry' => 'entry',
        'branch_entry' => 'branch_entry',
        'file_entry' => 'file_entry'
    ],
    'mapping_reserved_keys' => [
        'entry_uuid' => 'ec5_uuid',
        'parent_uuid' => 'ec5_parent_uuid',
        'branch_owner_uuid' => 'ec5_branch_owner_uuid',
        'branch_ref' => 'ec5_branch_ref',
        'branch_uuid' => 'ec5_branch_uuid'
    ],
    'jumps' => [
        'ALL' => 'ALL',
        'NO_ANSWER_GIVEN' => 'NO_ANSWER_GIVEN',
        'IS' => 'IS',
        'IS_NOT' => 'IS_NOT'
    ],
    'jump_keys' => [
        'to',
        'when',
        'answer_ref'
    ],
    'providers' => [
        'local' => 'local',
        'google' => 'google',
        'ldap' => 'ldap',
        'localgoogle' => 'localgoogle',//local and google accounts merged
        'passwordless' => 'passwordless',
        'apple' => 'apple'
    ],
    'cookies' => [
        'download-entries' => 'epicollect5-download-entries'
    ],
    'can_bulk_upload' => [
        'NOBODY' => 'nobody',
        'MEMBERS' => 'members',
        'EVERYBODY' => 'everybody'
    ],
    'user_placeholder' => [
        'apple_first_name' => 'Apple User',
        'apple_last_name' => 'n/a',
        'passwordless_first_name' => 'User',
        'passwordless_last_name' => 'n/a'
    ]
];
