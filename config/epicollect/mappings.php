<?php

return [
    'categories_icons' => [
        'general' => 'fa-globe',
        'social' => 'fa-users',
        'art' => 'fa-paint-brush',
        'humanities' => 'fa-graduation-cap',
        'biology' => 'fa-transgender-alt',
        'economics' => 'fa-money',
        'science' => 'fa-flask'
    ],
    'reserved_keys' => [
        'entry_uuid' => 'ec5_uuid',
        'parent_uuid' => 'ec5_parent_uuid',
        'branch_owner_uuid' => 'ec5_branch_owner_uuid',
        'branch_ref' => 'ec5_branch_ref',
        'branch_uuid' => 'ec5_branch_uuid'
    ],
    'cookies' => [
        'download-entries' => 'epicollect5-download-entries'
    ],
    'user_placeholder' => [
        'apple_first_name' => 'Apple User',
        'apple_last_name' => 'n/a',
        'passwordless_first_name' => 'User',
        'passwordless_last_name' => 'n/a'
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
    'web_platform' => 'WEB',
    'carbon_formats' => [
        'ISO' => 'Y-m-d\TH:i:s.000\Z',
        'fake_date' => 'Y-m-d\T00:00:00.000\Z',
        'fake_time' => '1970-01-01\TH:i:s.000\Z'
    ],
];
