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
        ],

        'temp' => [
            'driver' => 'local',
            'root' => storage_path('app/temp'),
        ],
        'temp-photo' => [
            'driver' => 'local',
            'root' => storage_path('app/temp/photo'),
        ],
        'temp-audio' => [
            'driver' => 'local',
            'root' => storage_path('app/temp/audio'),
        ],
        'temp-video' => [
            'driver' => 'local',
            'root' => storage_path('app/temp/audio'),
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'entry_original' => [
            'driver' => 'local',
            'root' => storage_path('app/entries/photo/entry_original'),
        ],

        'entry_sidebar' => [
            'driver' => 'local',
            'root' => storage_path('app/entries/photo/entry_sidebar'),
        ],

        'entry_thumb' => [
            'driver' => 'local',
            'root' => storage_path('app/entries/photo/entry_thumb'),
        ],

        'project_thumb' => [
            'driver' => 'local',
            'root' => storage_path('app/projects/project_thumb'),
        ],

        'project_mobile_logo' => [
            'driver' => 'local',
            'root' => storage_path('app/projects/project_mobile_logo'),
        ],
        'video' => [
            'driver' => 'local',
            'root' => storage_path('app/entries/video'),
        ],

        'audio' => [
            'driver' => 'local',
            'root' => storage_path('app/entries/audio'),
        ],

        'entries_zip' => [
            'driver' => 'local',
            'root' => storage_path('app/entries/zip'),
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
        ],

        'orphan_entry_sidebar' => [
            'driver' => 'local',
            'root' => storage_path('app/orphans/photo/entry_sidebar'),
        ],

        'orphan_entry_thumb' => [
            'driver' => 'local',
            'root' => storage_path('app/orphans/photo/entry_thumb'),
        ],

        'orphan_video' => [
            'driver' => 'local',
            'root' => storage_path('app/orphans/video'),
        ],

        'orphan_audio' => [
            'driver' => 'local',
            'root' => storage_path('app/orphans/audio'),
        ],

        'debug' => [
            'driver' => 'local',
            'root' => storage_path('app/debug'),
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
