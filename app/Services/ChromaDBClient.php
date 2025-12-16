<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ChromaDBClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;

class ChromaDBClient implements ChromaDBClientInterface
{
    private Client $client;

    private string $baseUrl;

    /**
     * @var array<string, string>
     */
    private array $collections = [];

    public function __construct(string $baseUrl = 'http://localhost:8000')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 10,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrCreateCollection(string $name): array
    {
        if (isset($this->collections[$name])) {
            return ['id' => $this->collections[$name], 'name' => $name];
        }

        try {
            $response = $this->client->post('/api/v1/collections', [
                'json' => [
                    'name' => $name,
                    'get_or_create' => true,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            if (! is_array($data) || ! isset($data['id'])) {
                throw new \RuntimeException('Invalid response from ChromaDB');
            }

            $this->collections[$name] = (string) $data['id'];

            return $data;
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to create collection: '.$e->getMessage(), 0, $e);
        }
    }

    public function add(
        string $collectionId,
        array $ids,
        array $embeddings,
        array $metadatas,
        ?array $documents = null
    ): void {
        try {
            $payload = [
                'ids' => $ids,
                'embeddings' => $embeddings,
                'metadatas' => $metadatas,
            ];

            if ($documents !== null) {
                $payload['documents'] = $documents;
            }

            $this->client->post("/api/v1/collections/{$collectionId}/add", [
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to add documents: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function query(
        string $collectionId,
        array $queryEmbedding,
        int $nResults = 10,
        array $where = []
    ): array {
        try {
            $payload = [
                'query_embeddings' => [$queryEmbedding],
                'n_results' => $nResults,
            ];

            if (count($where) > 0) {
                $payload['where'] = $where;
            }

            $response = $this->client->post("/api/v1/collections/{$collectionId}/query", [
                'json' => $payload,
            ]);

            $data = json_decode((string) $response->getBody(), true);

            return is_array($data) ? $data : [];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to query documents: '.$e->getMessage(), 0, $e);
        }
    }

    public function delete(string $collectionId, array $ids): void
    {
        try {
            $this->client->post("/api/v1/collections/{$collectionId}/delete", [
                'json' => [
                    'ids' => $ids,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to delete documents: '.$e->getMessage(), 0, $e);
        }
    }

    public function update(
        string $collectionId,
        array $ids,
        array $embeddings,
        array $metadatas,
        ?array $documents = null
    ): void {
        try {
            $payload = [
                'ids' => $ids,
                'embeddings' => $embeddings,
                'metadatas' => $metadatas,
            ];

            if ($documents !== null) {
                $payload['documents'] = $documents;
            }

            $this->client->post("/api/v1/collections/{$collectionId}/update", [
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to update documents: '.$e->getMessage(), 0, $e);
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->client->get('/api/v1/heartbeat');

            return $response->getStatusCode() === 200;
        } catch (ConnectException $e) {
            return false;
        } catch (GuzzleException $e) {
            return false;
        }
    }
}
