<?php

return [
    'entry_max' => 1024,
    'entry_min' => 768,
    'entry_original' => [1024, 768],
    'entry_original_portrait' => [768, 1024],
    'entry_original_landscape' => [1024, 768],
    'entry_thumb' => [100, 100],
    'project_thumb' => [512, 512],
    'project_mobile_logo' => [128, 128],
    'project_avatar' => [
        'filename' => 'logo.jpg',
        'driver' => [
            'project_thumb' => 'project_thumb',
            'project_mobile_logo' => 'project_mobile_logo'
        ],
        'width' => [
            'thumb' => 512,
            'mobile' => 128
        ],
        'height' => [
            'thumb' => 512,
            'mobile' => 128
        ],
        'quality' => 100,
        'font_size' => [
            'thumb' => 128,
            'mobile' => 48
        ]
    ],
    'video' => [],
    'audio' => [],
    // List of types viewable via the media controller
    'viewable' => [
        'entry_original',
        'entry_thumb',
        'project_thumb',
        'project_mobile_logo',
        'video',
        'audio',
        'temp'
    ],
    // List of the directory drivers we want to delete files from when deleting a project
    'project_deletable' => [
        'entry_original',
        'entry_thumb',
        'project_thumb',
        'project_mobile_logo',
        'video',
        'audio'
    ],
    'entries_deletable' => [
        'entry_original',
        'entry_thumb',
        'video',
        'audio'
    ]
];
