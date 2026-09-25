<?php
declare(strict_types=1);

return [
    'api_key'     => getenv('HYPIXEL_API_KEY') ?: '',
    'base_url'    => 'https://api.hypixel.net',
    'timeout'     => 15,
    'mojang_url'  => 'https://api.mojang.com',
];
