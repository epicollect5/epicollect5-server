<?php

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
    | Supported: "local", "ftp", "s3", "rackspace"
    |
    */

    'default' => 'local',

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

        'public' => [
            'driver' => 'local',
            'root' => public_path('images'),
            'throw' => true,
            'visibility' => 'public',
            'directory_visibility' => 'public',
        ],

        'temp' => [
            'driver' => 'local',
            'root' => storage_path('app/temp'),
            'throw' => true,
            'visibility' => 'public',
            'directory_visibility' => 'public',
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
            'directory_visibility' => 'public'
        ],

        'entry_original' => [
            'driver' => 'local',
            'root' => storage_path('app/entries/photo/entry_original'),
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
            'directory_visibility' => 'public'
        ],

        'entry_thumb' => [
            'driver' => 'local',
            'root' => storage_path('app/entries/photo/entry_thumb'),
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
            'directory_visibility' => 'public'
        ],

        'project_thumb' => [
            'driver' => 'local',
            'root' => storage_path('app/projects/project_thumb'),
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
            'directory_visibility' => 'public',
        ],

        'project_mobile_logo' => [
            'driver' => 'local',
            'root' => storage_path('app/projects/project_mobile_logo'),
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
            'directory_visibility' => 'public',
        ],

        'video' => [
            'driver' => 'local',
            'root' => storage_path('app/entries/video'),
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
            'directory_visibility' => 'public'
        ],

        'audio' => [
            'driver' => 'local',
            'root' => storage_path('app/entries/audio'),
            'throw' => true,
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
            'directory_visibility' => 'public'
        ],

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
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
            'directory_visibility' => 'public'
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
                    'public' => 0775,
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
                    'public' => 0775,
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
                    'public' => 0775,
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
                    'public' => 0775,
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
                    'public' => 0775,
                    'private' => 0700,
                ],
            ],
        ],

        'ftp' => [
            'driver' => 'ftp',
            'host' => 'ftp.example.com',
            'username' => 'your-username',
            'password' => 'your-password',

            // Optional FTP Settings...
            // 'port'     => 21,
            // 'root'     => '',
            // 'passive'  => true,
            // 'ssl'      => true,
            // 'timeout'  => 30,
        ],

        's3' => [
            'driver' => 's3',
            'key' => 'your-key',
            'secret' => 'your-secret',
            'region' => 'your-region',
            'bucket' => 'your-bucket',
        ],

        'rackspace' => [
            'driver' => 'rackspace',
            'username' => 'your-username',
            'key' => 'your-key',
            'container' => 'your-container',
            'endpoint' => 'https://identity.api.rackspacecloud.com/v2.0/',
            'region' => 'IAD',
            'url_type' => 'publicURL',
        ],

    ],

];
