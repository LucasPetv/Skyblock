<?php
declare(strict_types=1);

return [
    'env'   => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN),
    'cache' => [
        'profile_seconds' => 300,
        'bazaar_seconds'  => 60,
        'player_seconds'  => 300,
    ],
];
