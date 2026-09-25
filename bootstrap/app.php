<?php
declare(strict_types=1);

use Dotenv\Dotenv;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

$autoload = APP_ROOT . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefixes = [
            'SkyBlock\\' => APP_ROOT . '/src/',
            'Dotenv\\' => APP_ROOT . '/src/Fallback/Dotenv/',
        ];

        foreach ($prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $path = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($path)) {
                require_once $path;
            }
        }
    });
}

if (class_exists(Dotenv::class)) {
    Dotenv::createImmutable(APP_ROOT)->safeLoad();
}

require_once APP_ROOT . '/src/Support/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = app_config('app');
ini_set('display_errors', $config['debug'] ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');

set_exception_handler(static function (Throwable $exception) use ($config): void {
    http_response_code(500);
    app_log('app', sprintf('[%s] %s in %s:%d', $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine()));

    $message = $config['debug']
        ? htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8')
        : 'An unexpected error occurred.';

    if (wants_json()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message], JSON_THROW_ON_ERROR);
        return;
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Error</title></head><body>';
    echo '<h1>Application Error</h1><p>' . $message . '</p>';
    echo '</body></html>';
});
