<?php
declare(strict_types=1);

namespace SkyBlock\Api;

use RuntimeException;
use Throwable;

class HypixelApiClient
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private string $mojangUrl;
    /** @var callable|null */
    private $httpHandler;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?int $timeout = null,
        ?callable $httpHandler = null,
        ?string $mojangUrl = null
    ) {
        $config = require dirname(__DIR__, 2) . '/config/hypixel.php';
        $this->apiKey = $apiKey ?? (string) ($config['api_key'] ?? '');
        $this->baseUrl = rtrim($baseUrl ?? (string) ($config['base_url'] ?? 'https://api.hypixel.net'), '/');
        $this->timeout = $timeout ?? (int) ($config['timeout'] ?? 15);
        $this->mojangUrl = rtrim($mojangUrl ?? (string) ($config['mojang_url'] ?? 'https://api.mojang.com'), '/');
        $this->httpHandler = $httpHandler;
    }

    public function getPlayerByUuid(string $uuid): array
    {
        $response = $this->request('/v2/player', ['uuid' => $uuid]);

        return $response['player'] ?? [];
    }

    public function getProfilesByUuid(string $uuid): array
    {
        $response = $this->request('/v2/skyblock/profiles', ['uuid' => $uuid]);

        return $response['profiles'] ?? [];
    }

    public function getBazaar(): array
    {
        $response = $this->request('/v2/skyblock/bazaar');

        return $response['products'] ?? [];
    }

    public function getAuctions(int $page = 0): array
    {
        $response = $this->request('/v2/skyblock/auctions', ['page' => $page]);

        return [
            'page' => $response['page'] ?? $page,
            'totalPages' => $response['totalPages'] ?? 0,
            'totalAuctions' => $response['totalAuctions'] ?? 0,
            'auctions' => $response['auctions'] ?? [],
        ];
    }

    public function resolveUsernameToUuid(string $username): array
    {
        $response = $this->request('/users/profiles/minecraft/' . rawurlencode($username), [], false, $this->mojangUrl);

        return [
            'id' => $response['id'] ?? '',
            'name' => $response['name'] ?? $username,
        ];
    }

    private function request(string $endpoint, array $params = [], bool $includeApiKey = true, ?string $baseUrlOverride = null): array
    {
        $baseUrl = rtrim($baseUrlOverride ?? $this->baseUrl, '/');
        $url = $baseUrl . '/' . ltrim($endpoint, '/');
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }

        $headers = ['Accept: application/json'];
        if ($includeApiKey && $this->apiKey !== '') {
            $headers[] = 'API-Key: ' . $this->apiKey;
        }

        try {
            if ($this->httpHandler) {
                [$statusCode, $body] = ($this->httpHandler)($url, $headers, $this->timeout);
            } else {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_USERAGENT => 'SkyBlock-Ironman-Assistant/1.0',
                ]);

                $body = curl_exec($ch);
                $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                if ($body === false) {
                    $error = curl_error($ch) ?: 'Unknown cURL error';
                    curl_close($ch);
                    throw new RuntimeException('HTTP request failed: ' . $error);
                }

                curl_close($ch);
            }

            if ($statusCode === 429) {
                throw new RuntimeException('Hypixel API rate limit exceeded.');
            }

            if ($statusCode >= 400) {
                throw new RuntimeException('HTTP error ' . $statusCode . ' returned by remote API.');
            }

            $decoded = json_decode((string) $body, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Invalid JSON response received from remote API.');
            }

            if ($includeApiKey && array_key_exists('success', $decoded) && $decoded['success'] !== true) {
                $cause = $decoded['cause'] ?? 'Unknown API error';
                throw new RuntimeException('Hypixel API error: ' . $cause);
            }

            return $decoded;
        } catch (Throwable $exception) {
            $this->logError($url, $exception->getMessage());
            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException($exception->getMessage(), 0, $exception);
        }
    }

    private function logError(string $url, string $message): void
    {
        $sanitized = $this->apiKey !== ''
            ? str_replace($this->apiKey, '[redacted]', $message)
            : $message;
        $line = sprintf('[%s] %s | %s', date('c'), $url, $sanitized);
        $file = dirname(__DIR__, 2) . '/storage/logs/api.log';
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        file_put_contents($file, $line . PHP_EOL, FILE_APPEND);
    }
}
