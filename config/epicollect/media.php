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
        'disk' => 'project',
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
    // List of formats accessible by the media controller
    'formats' => [
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
        'photo',
        'project',
        'audio',
        'video'
    ],
    'entries_deletable' => [
        'photo',
        'audio',
        'video'
    ],
    'generic_placeholder' => [
        'filename' => 'ec5-placeholder-256x256.jpg',
        'width' => 256,
        'height' => 256,
        'size_in_bytes' => 3735
    ],
    'photo_not_synced_placeholder' => [
        'filename' => 'ec5-photo-unsynced-placeholder-512x512.jpg',
        'width' => 512,
        'height' => 512,
        'size_in_bytes' => 40073
    ],
    'content_type' => [
        'audio' => 'audio/mp4',
        'photo' => 'image/jpeg',
        'video' => 'video/mp4'
    ],
    'media_formats_disks' => [
        'entry_original' => 'photo',
        'entry_thumb' => 'photo',
        'project_thumb' => 'project',
        'project_mobile_logo' => 'project',
        'audio' => 'audio',
        'video' => 'video'
    ]
];
