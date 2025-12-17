<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmbeddingServiceInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ChromaDBEmbeddingService implements EmbeddingServiceInterface
{
    private Client $client;

    private string $model;

    public function __construct(
        string $embeddingServerUrl = 'http://localhost:8001',
        string $model = 'all-MiniLM-L6-v2'
    ) {
        $this->client = new Client([
            'base_uri' => rtrim($embeddingServerUrl, '/'),
            'timeout' => 30,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);
        $this->model = $model;
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
                'json' => [
                    'text' => $text,
                    'model' => $this->model,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = json_decode((string) $response->getBody(), true);

            if (! is_array($data)) {
                return [];
            }

            // Handle both singular 'embedding' and plural 'embeddings' response formats
            if (isset($data['embeddings']) && is_array($data['embeddings']) && isset($data['embeddings'][0])) {
                return array_map(fn ($val): float => (float) $val, $data['embeddings'][0]);
            }

            if (isset($data['embedding']) && is_array($data['embedding'])) {
                return array_map(fn ($val): float => (float) $val, $data['embedding']);
            }

            return [];
        } catch (GuzzleException $e) {
            // Gracefully handle embedding generation failures
            return [];
        }
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function similarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $magnitudeA += $a[$i] * $a[$i];
            $magnitudeB += $b[$i] * $b[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}
