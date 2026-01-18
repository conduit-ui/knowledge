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

    public function similarity(array $a, array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] ** 2;
            $magnitudeB += $b[$i] ** 2;
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}
