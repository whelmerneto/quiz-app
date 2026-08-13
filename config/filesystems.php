<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * The bucket the quiz images live in. Cloudflare R2 today; anything
         * speaking the S3 protocol would work unchanged.
         *
         * `driver => 's3'` names a PROTOCOL, not Amazon. S3 is the de facto
         * interface for object storage, and R2, MinIO, Backblaze B2 and Spaces
         * all implement it. Laravel's stock disk calls its variables AWS_*
         * after the SDK, which reads as "you need an AWS account" when you do
         * not — so they are renamed here. `endpoint` is what points the client
         * at Cloudflare instead of Amazon.
         *
         * `endpoint` and `url` are different hosts and are not interchangeable:
         *   endpoint  the authenticated S3 API, where the application writes.
         *             Not browsable.
         *   url       the public hostname objects are served from. This is what
         *             lands in an <img src>. Without it Storage::url() returns
         *             a path no browser resolves.
         */
        'quiz_storage' => [
            'driver' => 's3',
            'key' => env('QUIZ_STORAGE_KEY'),
            'secret' => env('QUIZ_STORAGE_SECRET'),
            'region' => env('QUIZ_STORAGE_REGION', 'auto'),
            'bucket' => env('QUIZ_STORAGE_BUCKET'),
            'endpoint' => env('QUIZ_STORAGE_ENDPOINT'),
            'url' => env('QUIZ_STORAGE_URL'),
            'use_path_style_endpoint' => env('QUIZ_STORAGE_PATH_STYLE', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
