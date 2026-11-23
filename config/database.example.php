<?php

require_once __DIR__ . '/../includes/env.php';

return [
    'host' => env('DB_HOST', 'localhost'),
    'port' => (int) env('MYSQLI_PORT', 3306),
    'database' => env('DB_NAME', ''),
    'username' => env('DB_USER', ''),
    'password' => env('DB_PASS', ''),
];