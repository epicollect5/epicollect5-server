<?php


return [
    'projects_access' => ['private', 'public'],
    'projects_visibility' => ['listed', 'hidden'],
    'projects_status' => ['trashed', 'locked'],
    'projects_status_all' => ['active', 'trashed', 'trash', 'restore', 'delete', 'locked', 'lock', 'unlock', 'archived'],
    'project_roles' => [
        'creator',
        'manager',
        'curator',
        'collector',
        'viewer'
    ],
    'user_state' => ['active', 'disabled', 'unverified'],
    'forms_type' => ['hierarchy', 'branch'],
    'inputs_type' => [
        'text',
        'decimal',
        'integer',
        'date',
        'time',
        'dropdown',
        'radio',
        'checkbox',
        'textarea',
        'location',
        'photo',
        'audio',
        'video',
        'barcode',
        'branch',
        'group',
        'readme',
        'phone',
        'searchsingle',
        'searchmultiple'
    ],
    'media_input_types' => [
        'photo',
        'audio',
        'video'
    ],
    'inputs_without_answers' => ['group', 'readme'],
    'input_special_types' => ['branch', 'group'],
    'edit_settings' => ['access', 'visibility', 'status', 'category'],
    'search_data_entries' => [
        'form_ref',
        'parent_form_ref',
        'branch',
        'branch_ref',
        'branch_owner_uuid',
        'parent_uuid',
        'uuid',
        'input_ref',
        'per_page',
        'page',
        'sort_by',
        'sort_order',
        'map_index',
        'filter_by',
        'filter_from',
        'filter_to',
        'format',
        'headers',
        'title'
    ],
    'download_subset_entries' => [
        'form_ref',
        'parent_form_ref',
        'branch',
        'branch_ref',
        'branch_owner_uuid',
        'parent_uuid',
        'uuid',
        'input_ref',
        'per_page',
        'page',
        'sort_by',
        'sort_order',
        'map_index',
        'filter_by',
        'filter_from',
        'filter_to',
        'format',
        'headers',
        'title',
        'filename'
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
        'map_index',
        'format',
        'filter_from',
        'filter_to',
        'filter_by'
    ],
    'download_data_entries_format_default' => 'csv',
    'datetime_format' => [
        'dd/MM/YYYY',
        'MM/dd/YYYY',
        'YYYY/MM/dd',
        'MM/YYYY',
        'dd/MM',
        'HH:mm:ss',
        'hh:mm:ss',
        'HH:mm',
        'hh:mm',
        'mm:ss'
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

    'project_categories' => [
        'general',
        'social',
        'art',
        'humanities',
        'biology',
        'economics',
        'science'
    ],
    'project_categories_icons' => [
        'general' => 'fa-globe',
        'social' => 'fa-users',
        'art' => 'fa-paint-brush',
        'humanities' => 'fa-graduation-cap',
        'biology' => 'fa-transgender-alt',
        'economics' => 'fa-money ',
        'science' => 'fa-flask',
    ],
    'entry_location_keys' => [
        'latitude',
        'longitude',
        'accuracy'
    ],
    'web_platform' => 'WEB',
    'edit_mapping_actions' => [
        'create',
        'update',
        'delete-map-confirm',
        'rename-map-confirm'
    ],
    'mapping_structure' => [
        'mapped_inputs',
        'form_ref',
        'name',
        'map_number',
        'is_default'
    ],
    'exclude_from_mapping' => [
        'readme'
    ],
    'exclude_from_entry_data' => [
        'device_id',
        'platform'
    ],
    'bulk_uploadables' => [
        'text',
        'phone',
        'textarea',
        'integer',
        'decimal',
        'date',
        'time',
        'radio',
        'checkbox',
        'dropdown',
        'barcode',
        'location',
        'searchsingle',
        'searchmultiple',
        'group'//not directly but its group inputs
    ],
    'can_bulk_upload' => [
        'nobody',
        'members',
        'everybody'
    ],
    'map_to_reserved' => [
        'ec5_uuid',
        'ec5_parent_uuid',
        'ec5_branch_uuid',
        'ec5_branch_owner_uuid'
    ]
];
