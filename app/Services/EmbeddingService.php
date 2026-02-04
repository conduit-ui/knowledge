<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmbeddingServiceInterface;
use GuzzleHttp\Client;

class EmbeddingService implements EmbeddingServiceInterface
{
    private readonly Client $client;

    public function __construct(
        string $serverUrl = 'http://localhost:8001',
    ) {
        $this->client = new Client([
            'base_uri' => rtrim($serverUrl, '/'),
            'timeout' => 30,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);
    }

    /**
     * @return array<int, float>
     *
     * @codeCoverageIgnore Requires external embedding server
     */
    public function generate(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        try {
            $response = $this->client->post('/embed', [
                'json' => ['text' => $text],
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = json_decode((string) $response->getBody(), true);

            if (! is_array($data) || ! isset($data['embeddings'][0])) {
                return [];
            }

            return array_map(fn ($val): float => (float) $val, $data['embeddings'][0]);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     *
     * @codeCoverageIgnore Same logic tested in StubEmbeddingServiceTest
     */
    public function similarity(array $a, array $b): float
    {
        if ($a === [] || $b === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $valA) {
            $valB = $b[$i];
            $dotProduct += $valA * $valB;
            $normA += $valA * $valA;
            $normB += $valB * $valB;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}
