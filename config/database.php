<?php

return [
    'connections' => [
        'hedgedoc' => [
            'driver' => 'pgsql',
            'host' => env('HEDGEDOC_DB_HOST', '127.0.0.1'),
            'port' => env('HEDGEDOC_DB_PORT', '5432'),
            'database' => env('HEDGEDOC_DB_DATABASE', 'hedgedoc'),
            'username' => env('HEDGEDOC_DB_USERNAME', 'hedgedoc'),
            'password' => env('HEDGEDOC_DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => env('HEDGEDOC_DB_SCHEMA', 'public'),
            'sslmode' => env('HEDGEDOC_DB_SSLMODE', 'prefer'),
        ],
    ],
];
