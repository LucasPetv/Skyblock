<?php
declare(strict_types=1);

namespace SkyBlock\Database;

use Dotenv\Dotenv;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class Database
{
    private static ?self $instance = null;
    private static ?PDO $testConnection = null;
    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        if ($connection instanceof PDO) {
            $this->connection = $this->configureConnection($connection);
            return;
        }

        $root = dirname(__DIR__, 2);
        if (class_exists(Dotenv::class)) {
            Dotenv::createImmutable($root)->safeLoad();
        }

        $config = require $root . '/config/database.php';
        $driver = $config['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            $path = $config['sqlite_path'] ?: ':memory:';
            $dsn = $path === ':memory:' ? 'sqlite::memory:' : 'sqlite:' . $path;
            $pdo = new PDO($dsn);
        } else {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['host'] ?? 'localhost',
                $config['name'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            );

            $pdo = new PDO($dsn, $config['user'] ?? '', $config['password'] ?? '');
        }

        $this->connection = $this->configureConnection($pdo);
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self(self::$testConnection);
        }

        return self::$instance;
    }

    public static function setTestConnection(?PDO $connection): void
    {
        self::$testConnection = $connection;
        self::$instance = null;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
        self::$testConnection = null;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($params);

            return $statement;
        } catch (PDOException $exception) {
            throw new RuntimeException('Database query failed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();

        return $result === false ? null : $result;
    }

    public function execute(string $sql, array $params = []): bool
    {
        return $this->query($sql, $params)->rowCount() >= 0;
    }

    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    private function configureConnection(PDO $connection): PDO
    {
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $connection;
    }
}
