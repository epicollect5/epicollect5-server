<?php

$doSpaces = [
    'region'   => env('DO_SPACES_REGION'),
    'bucket'   => env('DO_SPACES_BUCKET'),
    'endpoint' => env('DO_SPACES_ENDPOINT'),
    'key'      => env('DO_SPACES_KEY'),
    'secret'   => env('DO_SPACES_SECRET'),
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. A "local" driver, as well as a variety of cloud
    | based drivers are available for your choosing. Just store away!
    |
    | Supported: "local", "s3"
    |
    */

    'default' => config('epicollect.setup.system.storage_driver', 'local'),



    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    'cloud' => 's3',

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required options.
    |
    */

    'disks' => [
        //this is needed to server the image placeholder for example
        'public' => [
            'driver' => 'local',
            'root' => public_path('images'),
            'throw' => true,
            'visibility' => 'public',
            'directory_visibility' => 'public',
        ],

        //temp files are always stored locally
        'temp' => [
            'driver' => 'local',
            'root' => storage_path('app/temp'),
            'throw' => true,
            'visibility' => 'public',
            'directory_visibility' => 'public',

        ],

        'local' => [
            'driver' => config('epicollect.setup.system.storage_driver'),
            'root' => storage_path('app'),
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ]
        ],

        //imp: Laravel Team is against having a permission different from 755 on public folders
        //see https://github.com/laravel/docs/pull/8003
        'photo' => array_merge([
            'driver' => config('epicollect.setup.system.storage_driver'),
            'root' => config('epicollect.setup.system.storage_driver') === 'local'
                ? storage_path('app/entries/photo/entry_original')
                : 'app/entries/photo',
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ]

        ], $doSpaces),

        //imp: Laravel Team is against having a permission different from 755 on public folders
        //see https://github.com/laravel/docs/pull/8003
        'project' => array_merge([
            'driver' => config('epicollect.setup.system.storage_driver'),
            'root' => config('epicollect.setup.system.storage_driver') === 'local'
                ? storage_path('app/projects/project_thumb')
                : 'app/projects',
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ]
        ], $doSpaces),

        //imp: Laravel Team is against having a permission different from 755 on public folders
        //see https://github.com/laravel/docs/pull/8003
        'video' => array_merge([
            'driver' => config('epicollect.setup.system.storage_driver'),
            'root' => config('epicollect.setup.system.storage_driver') === 'local'
                ? storage_path('app/entries/video')
                : 'app/entries/video',
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ]
        ], $doSpaces),

        //imp: Laravel Team is against having a permission different from 755 on public folders
        //see https://github.com/laravel/docs/pull/8003
        'audio' => array_merge([
            'driver' => config('epicollect.setup.system.storage_driver'),
            'root' => config('epicollect.setup.system.storage_driver') === 'local'
                ? storage_path('app/entries/audio')
                : 'app/entries/audio',
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ]
        ], $doSpaces),

        //imp: Laravel Team is against having a permission different from 755 on public folders
        //see https://github.com/laravel/docs/pull/8003
        //zip archive are stored locally, served and deleted straight away
        'entries_zip' => [
            'driver' => 'local',
            'root' => storage_path('app/entries/zip'),
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ]
        ],

        's3' => [
            'driver' => 's3',
            'use_path_style_endpoint' => env('DO_SPACES_USE_PATH_STYLE_ENDPOINT', false),
            'region'   => env('DO_SPACES_REGION'),
            'bucket'   => env('DO_SPACES_BUCKET'),
            'endpoint' => env('DO_SPACES_ENDPOINT'),
            'key'      => env('DO_SPACES_KEY'),
            'secret'   => env('DO_SPACES_SECRET'),
        ]
]
];
