<?php
declare(strict_types=1);

namespace Dotenv;

final class Dotenv
{
    private function __construct(private readonly string $path)
    {
    }

    public static function createImmutable(string $path): self
    {
        return new self(rtrim($path, '/'));
    }

    public function safeLoad(): array
    {
        $file = $this->path . '/.env';
        if (!is_file($file)) {
            return [];
        }

        $loaded = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            $loaded[$key] = $value;
        }

        return $loaded;
    }
}
