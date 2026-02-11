<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\Qdrant\QdrantConnector;
use App\Integrations\Qdrant\Requests\GetCollectionInfo;

/**
 * @codeCoverageIgnore Raw socket I/O — requires live Redis/Qdrant connections
 */
class AgentHealthService
{
    /**
     * Check Redis connectivity using raw socket protocol.
     *
     * @return array{ok: bool, ping: string, error: string|null}
     */
    public function checkRedis(): array
    {
        try {
            /** @var string $host */
            $host = config('search.redis.host', 'localhost');
            /** @var int $port */
            $port = (int) config('search.redis.port', 6379);
            /** @var string|null $password */
            $password = config('search.redis.password');

            $socket = @fsockopen($host, $port, $errno, $errstr, 2.0);
            if (! is_resource($socket)) {
                return ['ok' => false, 'ping' => '', 'error' => "Cannot connect to {$host}:{$port}"];
            }

            if ($password !== null && $password !== '') {
                fwrite($socket, "AUTH {$password}\r\n");
                $authReply = fgets($socket);
                if ($authReply === false || ! str_starts_with(trim($authReply), '+OK')) {
                    fclose($socket);

                    return ['ok' => false, 'ping' => '', 'error' => 'AUTH failed'];
                }
            }

            fwrite($socket, "PING\r\n");
            $reply = fgets($socket);
            fclose($socket);

            $pingResult = $reply !== false ? trim($reply) : '';

            return [
                'ok' => $pingResult === '+PONG',
                'ping' => ltrim($pingResult, '+'),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'ping' => '', 'error' => $e->getMessage()];
        }
    }

    /**
     * Check Qdrant connectivity via collection info.
     *
     * @return array{ok: bool, collection: string, points_count: int, error: string|null}
     */
    public function checkQdrant(): array
    {
        /** @var string $collection */
        $collection = config('search.qdrant.collection', 'knowledge');

        try {
            $connector = new QdrantConnector(
                host: (string) config('search.qdrant.host', 'localhost'),
                port: (int) config('search.qdrant.port', 6333),
                apiKey: config('search.qdrant.api_key'),
                secure: (bool) config('search.qdrant.secure', false),
            );

            $response = $connector->send(new GetCollectionInfo($collection));

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'collection' => $collection,
                    'points_count' => 0,
                    'error' => 'HTTP '.$response->status(),
                ];
            }

            /** @var array<string, mixed> $data */
            $data = $response->json();
            /** @var array<string, mixed> $resultData */
            $resultData = $data['result'] ?? [];
            /** @var int $pointsCount */
            $pointsCount = $resultData['points_count'] ?? 0;

            return [
                'ok' => true,
                'collection' => $collection,
                'points_count' => $pointsCount,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'collection' => $collection,
                'points_count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get last event timestamp per agent from Redis.
     *
     * @return array<string, string|null>
     */
    public function getAgentTimestamps(): array
    {
        try {
            /** @var string $host */
            $host = config('search.redis.host', 'localhost');
            /** @var int $port */
            $port = (int) config('search.redis.port', 6379);

            $socket = @fsockopen($host, $port, $errno, $errstr, 2.0);
            if (! is_resource($socket)) {
                return [];
            }

            fwrite($socket, "KEYS agent:*:last_event\r\n");
            $keys = $this->readRedisArray($socket);

            $agents = [];
            foreach ($keys as $key) {
                $parts = explode(':', $key);
                if (count($parts) >= 3) {
                    fwrite($socket, "GET {$key}\r\n");
                    $value = $this->readRedisBulkString($socket);
                    $agents[$parts[1]] = $value;
                }
            }

            fclose($socket);

            return $agents;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  resource  $socket
     */
    private function readRedisBulkString($socket): ?string
    {
        $line = fgets($socket);
        if ($line === false) {
            return null;
        }

        $line = trim($line);

        if ($line === '$-1') {
            return null;
        }

        if (str_starts_with($line, '$')) {
            $length = max(1, (int) substr($line, 1) + 2);
            $data = fread($socket, $length);

            return $data !== false ? rtrim($data, "\r\n") : null;
        }

        if (str_starts_with($line, '+')) {
            return substr($line, 1);
        }

        return null;
    }

    /**
     * @param  resource  $socket
     * @return array<int, string>
     */
    private function readRedisArray($socket): array
    {
        $line = fgets($socket);
        if ($line === false) {
            return [];
        }

        $line = trim($line);

        if (! str_starts_with($line, '*')) {
            return [];
        }

        $count = (int) substr($line, 1);
        if ($count <= 0) {
            return [];
        }

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $value = $this->readRedisBulkString($socket);
            if ($value !== null) {
                $items[] = $value;
            }
        }

        return $items;
    }
}
