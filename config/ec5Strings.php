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
        'locked' => 'locked',
        'archived' => 'archived'
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
    ],
    'inputs_without_answers' => [
        'group' => 'group',
        'readme' => 'readme'
    ],
    'input_special_types' => [
        'branch' => 'branch',
        'group' => 'group'
    ],
    'edit_settings' => [
        'access' => 'access',
        'visibility' => 'visibility',
        'status' => 'status',
        'category' => 'category'
    ],
    'search_data_entries' => [
        'form_ref' => 'form_ref',
        'parent_form_ref' => 'parent_form_ref',
        'branch' => 'branch',
        'branch_ref' => 'branch_ref',
        'branch_owner_uuid' => 'branch_owner_uuid',
        'parent_uuid' => 'parent_uuid',
        'uuid' => 'uuid',
        'input_ref' => 'input_ref',
        'per_page' => 'per_page',
        'page' => 'page',
        'sort_by' => 'sort_by',
        'sort_order' => 'sort_order',
        'map_index' => 'map_index',
        'filter_by' => 'filter_by',
        'filter_from' => 'filter_from',
        'filter_to' => 'filter_to',
        'format' => 'format',
        'headers' => 'headers',
        'title' => 'title'
    ],
    'download_subset_entries' => [
        'form_ref' => 'form_ref',
        'parent_form_ref' => 'parent_form_ref',
        'branch' => 'branch',
        'branch_ref' => 'branch_ref',
        'branch_owner_uuid' => 'branch_owner_uuid',
        'parent_uuid' => 'parent_uuid',
        'uuid' => 'uuid',
        'input_ref' => 'input_ref',
        'per_page' => 'per_page',
        'page' => 'page',
        'sort_by' => 'sort_by',
        'sort_order' => 'sort_order',
        'map_index' => 'map_index',
        'filter_by' => 'filter_by',
        'filter_from' => 'filter_from',
        'filter_to' => 'filter_to',
        'format' => 'format',
        'headers' => 'headers',
        'title' => 'title',
        'filename' => 'filename'
    ],
    'search_data_entries_defaults' => [
        'sort_by' => 'created_at',
        'sort_order' => 'DESC',
        'format' => 'json',
        'headers' => 'true'
    ],
    'search_projects_defaults' => [
        'sort_by' => 'created_at',
        'sort_order' => 'DESC',
        'category' => 'general'
    ],
    'download_data_entries' => [
        'map_index' => 'map_index',
        'format' => 'format',
        'filter_from' => 'filter_from',
        'filter_to' => 'filter_to',
        'filter_by' => 'filter_by'
    ],
    'download_data_entries_format_default' => 'csv',
    'datetime_format' => [
        'dd/MM/YYYY' => 'd/m/Y',
        'MM/dd/YYYY' => 'm/d/Y',
        'YYYY/MM/dd' => 'Y/m/d',
        'MM/YYYY' => 'm/Y',
        'dd/MM' => 'd/m',
        'HH:mm:ss' => 'H:i:s',
        'hh:mm:ss' => 'H:i:s',
        'HH:mm' => 'H:i',
        'hh:mm' => 'H:i',
        'mm:ss' => 'i:s'
    ],
    'datetime_formats_php' => [
        'dd/MM/YYYY' => 'd/m/Y',
        'MM/dd/YYYY' => 'm/d/Y',
        'YYYY/MM/dd' => 'Y/m/d',
        'MM/YYYY' => 'm/Y',
        'dd/MM' => 'd/m',
        'HH:mm:ss' => 'H:i:s',
        'hh:mm:ss' => 'H:i:s',
        'HH:mm' => 'H:i',
        'hh:mm' => 'H:i',
        'mm:ss' => 'i:s'
    ],
    'carbon_formats' => [
        'ISO' => 'Y-m-d\TH:i:s.000\Z',
        'fake_date' => 'Y-m-d\T00:00:00.000\Z',
        'fake_time' => '1970-01-01\TH:i:s.000\Z'
    ],
    'project_categories' => [
        'general' => 'general',
        'social' => 'social',
        'art' => 'art',
        'humanities' => 'humanities',
        'biology' => 'biology',
        'economics' => 'economics',
        'science' => 'science'
    ],
    'project_categories_icons' => [
        'general' => 'fa-globe',
        'social' => 'fa-users',
        'art' => 'fa-paint-brush',
        'humanities' => 'fa-graduation-cap',
        'biology' => 'fa-transgender-alt',
        'economics' => 'fa-money',
        'science' => 'fa-flask'
    ],
    'entry_location_keys' => [
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'accuracy' => 'accuracy'
    ],
    'web_platform' => 'WEB',
    'edit_mapping_actions' => [
        'create' => 'create',
        'update' => 'update',
        'delete-map-confirm' => 'delete-map-confirm',
        'rename-map-confirm' => 'rename-map-confirm'
    ],
    'mapping_structure' => [
        'mapped_inputs' => 'mapped_inputs',
        'form_ref' => 'form_ref',
        'name' => 'name',
        'map_number' => 'map_number',
        'is_default' => 'is_default'
    ],
    'exclude_from_mapping' => [
        'readme' => 'readme'
    ],
    'exclude_from_entry_data' => [
        'device_id' => 'device_id',
        'platform' => 'platform'
    ],
    'bulk_uploadables' => [
        'text' => 'text',
        'phone' => 'phone',
        'textarea' => 'textarea',
        'integer' => 'integer',
        'decimal' => 'decimal',
        'date' => 'date',
        'time' => 'time',
        'radio' => 'radio',
        'checkbox' => 'checkbox',
        'dropdown' => 'dropdown',
        'barcode' => 'barcode',
        'location' => 'location',
        'searchsingle' => 'searchsingle',
        'searchmultiple' => 'searchmultiple',
        'group' => 'group'
    ],
    'map_to_reserved' => [
        'ec5_uuid' => 'ec5_uuid',
        'ec5_parent_uuid' => 'ec5_parent_uuid',
        'ec5_branch_uuid' => 'ec5_branch_uuid',
        'ec5_branch_owner_uuid' => 'ec5_branch_owner_uuid'
    ],
    'projects_access' => [
        'private' => 'private',
        'public' => 'public'
    ],
    'projects_visibility' => [
        'listed' => 'listed',
        'hidden' => 'hidden'
    ],
    'projects_status' => [
        'trashed' => 'trashed',
        'locked' => 'locked'
    ],
    'projects_status_all' => [
        'active' => 'active',
        'trashed' => 'trashed',
        'trash' => 'trash',
        'restore' => 'restore',
        'delete' => 'delete',
        'locked' => 'locked',
        'lock' => 'lock',
        'unlock' => 'unlock',
        'archived' => 'archived'
    ],
    'forms_type' => [
        'hierarchy' => 'hierarchy',
        'branch' => 'branch'
    ],
    'media_input_types' => [
        'photo' => 'photo',
        'audio' => 'audio',
        'video' => 'video'
    ]
];
