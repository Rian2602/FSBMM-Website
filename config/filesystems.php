<?php

use League\Flysystem\AwsS3V3\AwsS3V3Adapter;

// (** executed: reused by the 'local' and 'public' disks below when
// FILESYSTEM_LOCAL_DRIVER / FILESYSTEM_PUBLIC_DRIVER are set to 's3'.
// Vercel (container runtime) has an ephemeral filesystem, so persistent
// uploads + cross-request exports must live in an S3-compatible bucket
// (R2/Spaces). Local defaults keep unit and feature tests (SQLite + local
// disks) green. The 'local' disk only serves report exports here, so it can
// point at the same OCI bucket as 'public'. **)
// (** executed: the s3 swap is guarded — it only engages when the Flysystem
// AWS adapter (league/flysystem-aws-s3-v3) is installed AND the object-storage
// config is complete. Otherwise disks fall back to the local ones, so a
// misconfigured/lost AWS_* env can never take the site down (it just behaves
// like the pre-S3 setup). AWS_ENDPOINT is OPTIONAL — it is required for
// S3-compatible providers (R2/Spaces), but classic AWS S3 resolves the endpoint
// from AWS_DEFAULT_REGION automatically. **)
$s3Ready = class_exists(AwsS3V3Adapter::class)
    && env('AWS_ACCESS_KEY_ID')
    && env('AWS_SECRET_ACCESS_KEY')
    && env('AWS_DEFAULT_REGION')
    && env('AWS_BUCKET');

$localUsesS3 = env('FILESYSTEM_LOCAL_DRIVER', 'local') === 's3' && $s3Ready;
$publicUsesS3 = env('FILESYSTEM_PUBLIC_DRIVER', 'local') === 's3' && $s3Ready;

$s3Shape = [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => false,
    'report' => false,
];

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

        'local' => $localUsesS3 ? array_merge([
            'visibility' => 'private',
        ], $s3Shape) : [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => $publicUsesS3 ? array_merge([
            'visibility' => 'public',
        ], $s3Shape) : [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/') . '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
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
