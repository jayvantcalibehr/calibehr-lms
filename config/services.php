<?php

return [

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ECR — SQL Server (Calibehr HRMS)
    |--------------------------------------------------------------------------
    | Used for resolving Designation, Department, Branch names from ECR_New DB.
    | Never call env() inside controllers — always use config('services.ecr.*')
    */
    'ecr' => [
        'host'     => env('ECR_SQLSRV_HOST', 'tcp:172.16.1.30,1433'),
        'database' => env('ECR_SQLSRV_DB',   'ECR_New'),
        'username' => env('ECR_SQLSRV_USER',  'nbg_sa'),
        'password' => env('ECR_SQLSRV_PASS',  ''),
        'encrypt'  => env('ECR_SQLSRV_ENCRYPT', false),
        'timeout'  => 5,
    ],

];
