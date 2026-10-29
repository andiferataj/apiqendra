<?php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'pgsql' => ['driver' => 'pgsql', 'url' => env('DB_URL'), 'host' => env('DB_HOST', 'postgres'), 'port' => env('DB_PORT', '5432'), 'database' => env('DB_DATABASE', 'apiqendra'), 'username' => env('DB_USERNAME', 'apiqendra'), 'password' => env('DB_PASSWORD', ''), 'charset' => 'utf8', 'prefix' => '', 'prefix_indexes' => true, 'search_path' => 'public', 'sslmode' => env('DB_SSLMODE', 'prefer')],
        'sqlite' => ['driver' => 'sqlite', 'database' => env('DB_DATABASE', database_path('database.sqlite')), 'prefix' => '', 'foreign_key_constraints' => true, 'busy_timeout' => 5000],
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
    'redis' => ['client' => 'predis', 'options' => ['prefix' => env('REDIS_PREFIX', 'apiqendra_')], 'default' => ['host' => env('REDIS_HOST', 'redis'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', 6379), 'database' => 0], 'cache' => ['host' => env('REDIS_HOST', 'redis'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', 6379), 'database' => 1]],
];
