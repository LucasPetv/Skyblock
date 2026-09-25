<?php
declare(strict_types=1);

use SkyBlock\Database\Database;

function app_config(string $name): array
{
    static $configs = [];

    if (!isset($configs[$name])) {
        $path = APP_ROOT . '/config/' . $name . '.php';
        $configs[$name] = file_exists($path) ? require $path : [];
    }

    return $configs[$name];
}

function db(): Database
{
    return Database::getInstance();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): bool
{
    $token ??= $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    $token ??= $_POST['_token'] ?? null;

    return is_string($token) && hash_equals((string) ($_SESSION['_csrf_token'] ?? ''), $token);
}

function ensure_csrf(?string $token = null): void
{
    if (!verify_csrf($token)) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 419);
    }
}

function wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
}

function request_data(): array
{
    $input = file_get_contents('php://input') ?: '';
    $json = [];

    if ($input !== '') {
        $decoded = json_decode($input, true);
        if (is_array($decoded)) {
            $json = $decoded;
        }
    }

    return array_merge($_GET, $_POST, $json);
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function app_log(string $channel, string $message): void
{
    $file = APP_ROOT . '/storage/logs/' . $channel . '.log';
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0775, true);
    }

    file_put_contents($file, '[' . date('c') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

function selected_account_id(): ?int
{
    $accountId = $_GET['account_id'] ?? $_SESSION['selected_account_id'] ?? null;
    if ($accountId !== null && ctype_digit((string) $accountId)) {
        $_SESSION['selected_account_id'] = (int) $accountId;
        return (int) $accountId;
    }

    return null;
}

function selected_profile_id(): ?int
{
    $profileId = $_GET['profile_id'] ?? $_SESSION['selected_profile_id'] ?? null;
    if ($profileId !== null && ctype_digit((string) $profileId)) {
        $_SESSION['selected_profile_id'] = (int) $profileId;
        return (int) $profileId;
    }

    return null;
}

function method_override(): string
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'POST' && isset($_POST['_method'])) {
        return strtoupper((string) $_POST['_method']);
    }

    return $method;
}

function is_active_page(string $page): bool
{
    return basename($_SERVER['PHP_SELF'] ?? '') === $page;
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return 'Never';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i');
    } catch (Throwable) {
        return $value;
    }
}
