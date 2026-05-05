<?php

use Illuminate\Support\Str;

return [

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        // ============================================================
        // NEW MERGED DATABASE (production target)
        // ============================================================
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE', 'calibehr_lms_new'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            'engine'    => null,
        ],

        // ============================================================
        // OLD MITRA DATABASE — used ONLY by MigrationSeeder
        // Set credentials in .env: MITRA_OLD_DB_*
        // ============================================================
        'mitra_old' => [
            'driver'    => 'mysql',
            'host'      => env('MITRA_OLD_DB_HOST', '127.0.0.1'),
            'port'      => env('MITRA_OLD_DB_PORT', '3306'),
            'database'  => env('MITRA_OLD_DB_DATABASE', 'calibehr_mitra'),
            'username'  => env('MITRA_OLD_DB_USERNAME', 'root'),
            'password'  => env('MITRA_OLD_DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ],

        // ============================================================
        // OLD LMS DATABASE — used ONLY by MigrationSeeder
        // Set credentials in .env: LMS_OLD_DB_*
        // ============================================================
        'lms_old' => [
            'driver'    => 'mysql',
            'host'      => env('LMS_OLD_DB_HOST', '127.0.0.1'),
            'port'      => env('LMS_OLD_DB_PORT', '3306'),
            'database'  => env('LMS_OLD_DB_DATABASE', 'calibehr_lms'),
            'username'  => env('LMS_OLD_DB_USERNAME', 'root'),
            'password'  => env('LMS_OLD_DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ],

        // ============================================================
        // ECR_New — SQL Server (for Matrix Report: Company/Dept/RM/FH)
        // Set credentials in .env: ECR_SQLSRV_*
        // ============================================================
        'ecr_sqlsrv' => [
            'driver'   => 'sqlsrv',
            'host'     => env('ECR_SQLSRV_HOST', '172.16.1.30'),
            'port'     => env('ECR_SQLSRV_PORT', '1433'),
            'database' => env('ECR_SQLSRV_DB',   'ECR_New'),
            'username' => env('ECR_SQLSRV_USER',  'nbg_sa'),
            'password' => env('ECR_SQLSRV_PASS',  ''),
            'charset'  => 'utf8',
            'prefix'   => '',
            'encrypt'  => env('ECR_SQLSRV_ENCRYPT', false),
            'trust_server_certificate' => true,
        ],

    ],

    'migrations' => 'migrations',
    'redis' => [
        'client'  => env('REDIS_CLIENT', 'phpredis'),
        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port'     => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ],
    ],
//     'sqlsrv_ecr' => [
//     'driver'   => 'sqlsrv',
//     'host'     => '172.16.1.30',
//     'port'     => '1433',
//     'database' => 'ECR_New',
//     'username' => 'nbg_sa',
//     'password' => 'yq2%8Paph*0}}=t',
//     'charset'  => 'utf8',
//     'prefix'   => '',
//     'encrypt'  => false,
//     'trust_server_certificate' => true,
// ],
];
