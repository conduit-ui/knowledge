<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmbeddingServiceInterface;
use GuzzleHttp\Client;

class EmbeddingService implements EmbeddingServiceInterface
{
    private Client $client;

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
}
