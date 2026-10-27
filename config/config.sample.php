<?php

return [

    'db' => [
        'host'    => getenv('URBANEATS_DB_HOST') ?: 'localhost',
        'name'    => getenv('URBANEATS_DB_NAME') ?: 'urbaneats_db',
        'user'    => getenv('URBANEATS_DB_USER') ?: 'root',
        'pass'    => getenv('URBANEATS_DB_PASS') !== false ? getenv('URBANEATS_DB_PASS') : '',
        'charset' => 'utf8mb4',
        'port'    => getenv('URBANEATS_DB_PORT') ?: 3306,
    ],

    'app' => [
        'name'        => 'UrbanEats Cloud Kitchen',
        'tagline'     => 'Premium Cloud Kitchen Platform',
        'url'         => 'http://localhost/UrbanEats-Cloud-Kitchen-Platform/public',
        'environment' => 'development', // 'development' or 'production'
        'timezone'    => 'Asia/Colombo',
    ],

    'security' => [
        'session_lifetime'      => 1800,
        // Max consecutive failed logins before temporary lockout
        'login_max_attempts'    => 5,
        // Duration of lockout in seconds (900s = 15 minutes)
        'login_lockout_seconds' => 900,
        // Lifetime of password reset token in seconds (1800s = 30 minutes)
        'reset_token_lifetime'  => 1800,
        // Bcrypt cost parameter
        'hash_cost'             => 12,
    ],
];
