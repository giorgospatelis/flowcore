<?php

namespace FlowCore\Support\Factories;

use FlowCore\Contracts\QueueDriverInterface;
use PRedis\Client as RedisClient;

class DriverFactory
{
    private array $drivers = [];
    private array $configs = [];

    public function register(string $name, string $class): void
    {
        $this->drivers[$name] = $class;
    }

    public function create(string $driver, array $config = []): QueueDriverInterface
    {
        if (! isset($this->drivers[$driver])) {
            throw new \InvalidArgumentException("Driver {$driver} is not registered.");
        }

        $class = $this->drivers[$driver];

        return match($driver) {
            'redis' => new $class($this->createRedisConnection($config)),
            'database' => new $class($this->createDatabaseConnection($config), $config),
            'memory' => new $class(),
            'filesystem' => new $class($config['path'] ?? '/tmp/flowcore'),
            default => new $class($config),
        };
    }

    private function createRedisConnection(array $config): RedisClient
    {
        $redis = new RedisClient();
        $redis->connect($config['host'] ?? '127.0.0.1', $config['port'] ?? 6379);

        if (isset($config['password'])) {
            $redis->auth($config['password']);
        }
        if (isset($config['database'])) {
            $redis->select($config['database']);
        }
        return $redis;
    }

    private function createDatabaseConnection(array $config): \PDO
    {
        $dsn = match ($config['driver'] ?? 'mysql') {
            'mysql' => "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
            'pgsql' => "pgsql:host={$config['host']};dbname={$config['database']}",
            'sqlite' => "sqlite:{$config['database']}",
            default => throw new \Exception('Unsupported database driver')
        };

        return new \PDO(
            $dsn,
            $config['username'] ?? null,
            $config['password'] ?? null,
            $config['options'] ?? []
        );
    }
}
