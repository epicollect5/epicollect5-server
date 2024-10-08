<?php

return [
    // Minimum Project Definition structure
    'project_definition' => [
        'id' => '{{project_ref}}',
        'type' => 'project',
        'project' => [
            'ref' => '{{project_ref}}',
            'name' => '',
            'slug' => '',
            'forms' => [
                [
                    'ref' => '{{form_ref}}',
                    'name' => '',
                    'slug' => '',
                    'type' => 'hierarchy',
                    'inputs' => []
                ]
            ],
            'access' => '',
            'status' => '',
            'logo_url' => '',
            'visibility' => '',
            'small_description' => '',
            'description' => '',
            'category' => 'general',
            'entries_limits' => []
        ]
    ],
    // Minimum Project Extra structure
    'project_extra' => [
        'forms' => [
            '{{form_ref}}' => [
                'group' => [],
                'branch' => [],
                'inputs' => [],
                'details' => [
                    'ref' => '{{form_ref}}',
                    'name' => '',
                    'slug' => '',
                    'type' => 'hierarchy',
                    'inputs' => [],
                    'lists' => [
                        'location_inputs' => [],
                        'multiple_choice_inputs' => [
                            'form' => [],
                            'branch' => []
                        ],

                    ]
                ]
            ]
        ],
        'inputs' => [],
        'project' => [
            'forms' => [
                '{{form_ref}}'
            ],
            'details' => [
                'ref' => '{{project_ref}}',
                'name' => '',
                'slug' => '',
                'access' => '',
                'status' => '',
                'logo_url' => '',
                'visibility' => '',
                'small_description' => '',
                'description' => '',
                'category' => 'general'
            ],
            'entries_limits' => []
        ]
    ],
    // Project Extra structure for resetting the model
    'project_extra_reset' => [
        'forms' => [],
        'inputs' => [],
        'project' => [
            'forms' => [],
            'details' => [
                'ref' => '',
                'name' => '',
                'slug' => '',
                'access' => '',
                'status' => '',
                'logo_url' => '',
                'visibility' => '',
                'small_description' => '',
                'description' => '',
                'category' => ''
            ],
            'entries_limits' => []
        ]
    ],
    // The project details that can be updated when editing, with default values
    'updatable_project_details' => [
        'access' => 'private',
        'status' => 'active',
        'logo_url' => '',
        'visibility' => 'hidden',
        'small_description' => '',
        'description' => '',
        'category' => 'general',
        'app_link_visibility' => 'hidden',
    ],
    // Project Entry Data structures
    'entry_data' => [
        'id' => '',
        'type' => '',
        'attributes' => [
            'form' => [
                'ref' => '',
                'type' => 'hierarchy'
            ]
        ],
        'relationships' => [
            'branch' => [],
            'parent' => []
        ]
    ],
    'entry' => [
        'title' => '',
        'device_id' => '',
        'platform' => '',
        'created_at' => '',
        'entry_uuid' => '',
        'project_version' => '',
        'answers' => []
    ],
];
