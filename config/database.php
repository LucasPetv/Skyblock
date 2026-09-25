<?php
declare(strict_types=1);

return [
    'driver'   => getenv('DB_DRIVER') ?: 'mysql',
    'host'     => getenv('DB_HOST') ?: 'localhost',
    'name'     => getenv('DB_NAME') ?: 'skyblock',
    'user'     => getenv('DB_USER') ?: 'skyblock',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset'  => 'utf8mb4',
    'sqlite_path' => getenv('DB_SQLITE_PATH') ?: '',
];
