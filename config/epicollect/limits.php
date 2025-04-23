<?php

return [
    'project' => [
        'id' => [
            'size' => 32
        ],
        'name' => [
            'min' => 3,
            'max' => 50
        ],
        'form' => [
            'name' => [
                'max' => 50
            ],
        ],
        'small_desc' => [
            'min' => 15,
            'max' => 100
        ],
        'description' => [
            'min' => 3,
            'max' => 3000
        ],
        'logo' => [
            'width' => 4096,
            'height' => 4096,
            'size' => 5000
        ]
    ],
    'formsMaxCount' => 5,
    'inputsMaxCount' => 300,
    'titlesMaxCount' => 3,
    'searchMaxCount' => 5,
    'formlimits' => ['forms' => 5, 'inputs' => 300, 'titles' => 3],
    'search_per_project' => 5,
    'entries_table' => [
        'per_page' => 50
    ],
    'entries_map' => [
        'per_page' => env('ENTRIES_MAP_PER_PAGE', 50000),
    ], //this is a test with new dataviewer
    'entries_export_per_page_json' => 1000,
    'entries_export_chunk' => 100,
    'entries_export_per_page_csv' => 1000,
    'entries_limits_max' => 50000,
    'emails_limit_max' => 100, ///for user import from csv
    // Max lengths for entry answers
    'entry_answer_limits' => [
        'text' => 255,
        'phone' => 255,
        'integer' => 255,
        'decimal' => 255,
        'textarea' => 1000,
        'date' => 25,
        'time' => 25,
        'dropdown' => 13,
        'radio' => 13,
        'checkbox' => 13,
        // Photo has longer length as extension may be jpg or jpeg
        'photo' => 52,
        'audio' => 51,
        'video' => 51,
        'barcode' => 255
    ],
    'possible_answers_limit' => 300,
    'possible_answers_search_limit' => 1000,
    'possible_answers_length_limit' => 250,
    'possible_answer_ref_length_limit' => 13,
    'users_per_page' => 25,
    'projects_per_page' => 8,
    'admin_projects_per_page' => 25,
    'project_mappings' => [
        'max_count' => 4, // (auto mapping and 3 customs)
        'map_key_length' => 20,
        'map_key_length_pa' => 150
    ],
    'question_limit' => 255,
    'readme_question_limit' => 1000,
    'form_name_maxlength' => 50,

    // IMP: Limit for number of rows at a time to chunk when downloading data
    // IMP: EC5 Download Test project (103000 entries) consumes ~140MB memory at 50000 per chunk
    // IMP: with 1000, memory peak is at 20MB but lots of queries
    'download_entries_chunk_size' => (int) env('DOWNLOAD_ENTRIES_ARCHIVE_CHUNK_SIZE', 1000),
    //per hour limit
    'passwordless_rate_limit' => (int) env('PASSWORDLESS_RATE_LIMIT', 10),
    'account_deletion_limit' => (int) env('ACCOUNT_DELETION_LIMIT', 1),
    'oauth_token_limit' => (int) env('OAUTH_TOKEN_LIMIT', 10),
    //per minute limit
    'api_export' => [
        'project' => (int) env('API_RATE_LIMIT_PROJECT', 60),
        'entries' => (int) env('API_RATE_LIMIT_ENTRIES', 60),
        'media' => (int) env('API_RATE_LIMIT_MEDIA', 30),
    ]
];
