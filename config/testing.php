<?php

return [
    'LOCAL_SERVER' => env('LOCAL_SERVER'),
    'UNIT_TEST_RANDOM_EMAIL' => env('UNIT_TEST_RANDOM_EMAIL'),
    'SUPER_ADMIN_EMAIL' => env('SUPER_ADMIN_EMAIL'),
    'CREATOR_EMAIL' => env('CREATOR_EMAIL'),
    'MANAGER_EMAIL' => env('MANAGER_EMAIL'),
    'PASSWORDLESS_TOKEN_EXPIRES_IN' => (int) env('PASSWORDLESS_TOKEN_EXPIRES_IN'),
    'BCRYPT_ROUNDS' => env('BCRYPT_ROUNDS'),
    'JSON_STRUCTURES_WITH_WILDCARD' => [
        'project_definition' => [
            'id',
            'type',
            'project' => [
                'ref',
                'name',
                'slug',
                'forms' => ['*' =>
                    [
                        'ref',
                        'name',
                        'slug',
                        'type',
                        'inputs' => ['*' =>
                            [
                                'max',
                                'min',
                                'ref',
                                'type',
                                'group' => [],
                                'jumps' => [],
                                'regex',
                                'branch' => [],
                                'verify',
                                'default',
                                'is_title',
                                'question',
                                'uniqueness',
                                'is_required',
                                'datetime_format',
                                'possible_answers' => [],
                                'set_to_current_datetime'
                            ]
                        ]
                    ]
                ],
                'access',
                'status',
                'category',
                'homepage',
                'logo_url',
                'created_at',
                'visibility',
                'description',
                'entries_limits',
                'can_bulk_upload',
                'small_description'
            ]
        ]
    ],
    'JSON_STRUCTURES_KEYS' => [
        'project_definition' => [
            'root' => [
                'id',
                'type',
                'project'
            ],
            'project' => [
                'ref',
                'name',
                'slug',
                'forms',
                'access',
                'status',
                'category',
                'homepage',
                'logo_url',
                'created_at',
                'visibility',
                'description',
                'entries_limits',
                'can_bulk_upload',
                'small_description'
            ],
            'forms' => [
                'ref',
                'name',
                'slug',
                'type',
                'inputs'
            ],
            'inputs' => [
                'max',
                'min',
                'ref',
                'type',
                'group',
                'jumps',
                'regex',
                'branch',
                'verify',
                'default',
                'is_title',
                'question',
                'uniqueness',
                'is_required',
                'datetime_format',
                'possible_answers',
                'set_to_current_datetime'
            ],
            //todo
            'group' => [],
            'jumps' => [
                'to',
                'when',
                'answer_ref'
            ],
            'branch' => [],
            'possible_answers' => [
                'answer',
                'answer_ref'
            ]
        ],
        'project_extra' => [
            'root' => [
                'forms',
                'inputs',
                'project'
            ]
        ],
        'project_stats' => [
            'total_entries',
            'form_counts',
            'branch_counts',
            'structure_last_updated'
        ],
        'project_mapping' => [
            'name',
            'forms',
            'map_index',
            'is_default'
        ],
        'project_user' => [
            'name',
            'avatar',
            'role',
            'id'
        ]
    ],
    'WEB_UPLOAD_CONTROLLER_PROJECT' => [
        'name' => 'EC5 Web Upload Controller Project',
        'slug' => 'ec5-web-upload-controller-project'
    ],
    'API_RATE_LIMITS_PROJECT' => [
        'name' => 'Api Rate Limits Project',
        'slug' => 'api-rate-limits-project'
    ],
    'API_RATE_LIMITS_ENTRIES' => [
        'name' => 'Api Rate Limits Entries',
        'slug' => 'api-rate-limits-entries'
    ],
    'API_RATE_LIMITS_MEDIA' => [
        'name' => 'Api Rate Limits Media',
        'slug' => 'api-rate-limits-media'
    ]
];
