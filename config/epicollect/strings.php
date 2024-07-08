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
        'unverified' => 'unverified',
        'archived' => 'archived'
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
    'mapping_ec5_keys' => [
        'ec5_uuid' => 'ec5_uuid',
        'ec5_parent_uuid' => 'ec5_parent_uuid',
        'ec5_branch_owner_uuid' => 'ec5_branch_owner_uuid',
        'ec5_branch_ref' => 'ec5_branch_ref',
        'ec5_branch_uuid' => 'ec5_branch_uuid'
    ],
    'jumps' => [
        'ALL' => 'ALL',
        'NO_ANSWER_GIVEN' => 'NO_ANSWER_GIVEN',
        'IS' => 'IS',
        'IS_NOT' => 'IS_NOT'
    ],
    'jump_keys' => [
        'to' => 'to',
        'when' => 'when',
        'answer_ref' => 'answer_ref'
    ],
    'providers' => [
        'local' => 'local',
        'google' => 'google',
        'ldap' => 'ldap',
        'localgoogle' => 'localgoogle',//local and google accounts merged
        'passwordless' => 'passwordless',
        'apple' => 'apple'
    ],
    'can_bulk_upload' => [
        'nobody' => 'nobody',
        'members' => 'members',
        'everybody' => 'everybody'
    ],
    'inputs_without_answers' => [
        'group' => 'group',
        'readme' => 'readme'
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
    'download_data_entries' => [
        'map_index' => 'map_index',
        'format' => 'format',
        'filter_from' => 'filter_from',
        'filter_to' => 'filter_to',
        'filter_by' => 'filter_by'
    ],
    'date_formats' => [
        'dd/MM/YYYY' => 'dd/MM/YYYY',
        'MM/dd/YYYY' => 'MM/dd/YYYY',
        'YYYY/MM/dd' => 'YYYY/MM/dd',
        'MM/YYYY' => 'MM/YYYY',
        'dd/MM' => 'dd/MM',
    ],
    'time_formats' => [
        'HH:mm:ss' => 'HH:mm:ss',
        'hh:mm:ss' => 'hh:mm:ss',
        'HH:mm' => 'HH:mm',
        'hh:mm' => 'hh:mm',
        'mm:ss' => 'mm:ss'
    ],
    'datetime_format' => [
        'dd/MM/YYYY' => 'dd/MM/YYYY',
        'MM/dd/YYYY' => 'MM/dd/YYYY',
        'YYYY/MM/dd' => 'YYYY/MM/dd',
        'MM/YYYY' => 'MM/YYYY',
        'dd/MM' => 'dd/MM',
        'HH:mm:ss' => 'HH:mm:ss',
        'hh:mm:ss' => 'hh:mm:ss',
        'HH:mm' => 'HH:mm',
        'hh:mm' => 'hh:mm',
        'mm:ss' => 'mm:ss',
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
    'entry_location_keys' => [
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'accuracy' => 'accuracy'
    ],
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
    'multiple_choice_question_types' => [
        'radio' => 'radio',
        'checkbox' => 'checkbox',
        'dropdown' => 'dropdown',
        'searchsingle' => 'searchsingle',
        'searchmultiple' => 'searchmultiple',
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
        'restore' => 'restore',
        'delete' => 'delete',
        'locked' => 'locked',
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
    ],
    'media_file_extension' => [
        'jpg' => 'jpg',
        'mp4' => 'mp4'
    ]
];
