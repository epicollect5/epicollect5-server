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
    | been setup for each driver as an example of the required options.
    |
    */

    'disks' => [

        //todo: is this actually needed?
        'public' => [
            'driver' => 'local',
            'root' => public_path('images'),
            'throw' => true,
            'visibility' => 'public',
            'directory_visibility' => 'public',
        ],

        'temp' => array_merge([
            'driver' => config('epicollect.setup.system.storage_driver'),
            'root' => config('epicollect.setup.system.storage_driver') === 'local'
                ? storage_path('app/temp')
                : 'app/temp',
            'throw' => true,
            'visibility' => 'public',
            'directory_visibility' => 'public',

        ], $doSpaces),

        //todo: is this actually needed?
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
        'entry_original' => array_merge([
            'driver' => config('epicollect.setup.system.storage_driver'),
            'root' => config('epicollect.setup.system.storage_driver') === 'local'
                ? storage_path('app/entries/photo/entry_original')
                : 'app/entries/photo/entry_original',
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
        'entry_thumb' => array_merge([
            'driver' => config('epicollect.setup.system.storage_driver'),
            'root' => config('epicollect.setup.system.storage_driver') === 'local'
                ? storage_path('app/entries/photo/entry_thumb')
                : 'app/entries/photo/entry_thumb',
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ]
            ]

        ], $doSpaces),

        //imp: Laravel Team is against having a permission different from 755 on public folders
        //see https://github.com/laravel/docs/pull/8003
        'project_thumb' => array_merge([
            'driver' => config('epicollect.setup.system.storage_driver'),
            'root' => config('epicollect.setup.system.storage_driver') === 'local'
                ? storage_path('app/projects/project_thumb')
                : 'app/projects/project_thumb',
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
        'project_mobile_logo' => array_merge([
            'driver' => config('epicollect.setup.system.storage_driver'),
            'root' => config('epicollect.setup.system.storage_driver') === 'local'
                ? storage_path('app/projects/project_mobile_logo')
                : 'app/projects/project_mobile_logo',
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
        'video' => [
            'driver' => config('epicollect.setup.system.storage_driver'),
            'root' => storage_path('app/entries/video'),
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
        'audio' => [
            'driver' => config('epicollect.setup.system.storage_driver'),
            'root' => storage_path('app/entries/audio'),
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
        'entries_zip' => [
            'driver' => config('epicollect.setup.system.storage_driver'),
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

        /**
         * add orphan files in proper own folders
         * we use these folders to store media file which
         * are not linked to any entry data row (so they are "orphan")
         * we had to do this due to a bug no the app not deleting media files
         * when a data entry was deleted
         * */

        'orphan_entry_original' => [
            'driver' => 'local',
            'root' => storage_path('app/orphans/photo/entry_original'),
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
            ],
        ],

        'orphan_entry_thumb' => [
            'driver' => 'local',
            'root' => storage_path('app/orphans/photo/entry_thumb'),
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
            ],
        ],

        'orphan_video' => [
            'driver' => 'local',
            'root' => storage_path('app/orphans/video'),
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
            ],
        ],

        'orphan_audio' => [
            'driver' => 'local',
            'root' => storage_path('app/orphans/audio'),
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
            ],
        ],

        'debug' => [
            'driver' => 'local',
            'root' => storage_path('app/debug'),
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
            ],
        ],

        's3' => array_merge([
            'driver' => 's3',
            'use_path_style_endpoint' => env('DO_SPACES_USE_PATH_STYLE_ENDPOINT', false),
        ], $doSpaces)
]
];
