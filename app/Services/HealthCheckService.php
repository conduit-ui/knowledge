<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\HealthCheckInterface;
use Redis;

class HealthCheckService implements HealthCheckInterface
{
    private const TIMEOUT_SECONDS = 2;

    /**
     * @var array<string, array{name: string, type: string, checker: callable}>
     */
    private array $services;

    public function __construct()
    {
        $this->services = [
            'qdrant' => [
                'name' => 'Qdrant',
                'type' => 'Vector Database',
                'checker' => fn () => $this->checkQdrant(),
            ],
            'redis' => [
                'name' => 'Redis',
                'type' => 'Cache',
                'checker' => fn () => $this->checkRedis(),
            ],
            'embeddings' => [
                'name' => 'Embeddings',
                'type' => 'ML Service',
                'checker' => fn () => $this->checkEmbeddings(),
            ],
            'ollama' => [
                'name' => 'Ollama',
                'type' => 'LLM Engine',
                'checker' => fn () => $this->checkOllama(),
            ],
        ];
    }

    public function check(string $service): array
    {
        if (! isset($this->services[$service])) {
            return [
                'name' => $service,
                'healthy' => false,
                'endpoint' => 'unknown',
                'type' => 'Unknown',
            ];
        }

        $config = $this->services[$service];
        $endpoint = $this->getEndpoint($service);

        return [
            'name' => $config['name'],
            'healthy' => ($config['checker'])(),
            'endpoint' => $endpoint,
            'type' => $config['type'],
        ];
    }

    public function checkAll(): array
    {
        return array_map(
            fn (string $service) => $this->check($service),
            $this->getServices()
        );
    }

    public function getServices(): array
    {
        return array_keys($this->services);
    }

    private function getEndpoint(string $service): string
    {
        return match ($service) {
            'qdrant' => config('search.qdrant.host', 'localhost').':'.config('search.qdrant.port', 6333),
            'redis' => config('database.redis.default.host', '127.0.0.1').':'.config('database.redis.default.port', 6380),
            'embeddings' => config('search.qdrant.embedding_server', 'http://localhost:8001'),
            'ollama' => config('search.ollama.host', 'localhost').':'.config('search.ollama.port', 11434),
            default => 'unknown',
        };
    }

    private function checkQdrant(): bool
    {
        $host = config('search.qdrant.host', 'localhost');
        $port = config('search.qdrant.port', 6333);

        return $this->httpCheck("http://{$host}:{$port}/healthz");
    }

    /**
     * @codeCoverageIgnore Requires native Redis extension with live connection
     */
    private function checkRedis(): bool
    {
        if (! extension_loaded('redis')) {
            return false;
        }

        try {
            $redis = new Redis;
            $host = config('database.redis.default.host', '127.0.0.1');
            $port = (int) config('database.redis.default.port', 6380);

            return $redis->connect($host, $port, self::TIMEOUT_SECONDS);
        } catch (\Exception) {
            return false;
        }
    }

    private function checkEmbeddings(): bool
    {
        $server = config('search.qdrant.embedding_server', 'http://localhost:8001');

        return $this->httpCheck("{$server}/health");
    }

    private function checkOllama(): bool
    {
        $host = config('search.ollama.host', 'localhost');
        $port = config('search.ollama.port', 11434);

        return $this->httpCheck("http://{$host}:{$port}/api/tags");
    }

    private function httpCheck(string $url): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => self::TIMEOUT_SECONDS,
                'method' => 'GET',
            ],
        ]);

        return @file_get_contents($url, false, $context) !== false;
    }
}
