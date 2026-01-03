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

    private string $tenant;

    private string $database;

    /**
     * @var array<string, string>
     */
    private array $collections = [];

    public function __construct(
        string $baseUrl = 'http://localhost:8000',
        string $tenant = 'default_tenant',
        string $database = 'default_database'
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->tenant = $tenant;
        $this->database = $database;
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);
    }

    /**
     * Get the v2 API base path for collections.
     */
    private function getCollectionsPath(): string
    {
        return "/api/v2/tenants/{$this->tenant}/databases/{$this->database}/collections";
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
            // First try to get existing collection
            $response = $this->client->get($this->getCollectionsPath()."/{$name}");

            if ($response->getStatusCode() === 200) {
                $data = json_decode((string) $response->getBody(), true);
                if (is_array($data) && isset($data['id'])) {
                    $this->collections[$name] = (string) $data['id'];
                    return $data;
                }
            }

            // Create new collection if not found
            $response = $this->client->post($this->getCollectionsPath(), [
                'json' => [
                    'name' => $name,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            if (! is_array($data) || ! isset($data['id'])) {
                throw new \RuntimeException('Invalid response from ChromaDB: '.json_encode($data));
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

            $response = $this->client->post($this->getCollectionsPath()."/{$collectionId}/add", [
                'json' => $payload,
            ]);

            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException('ChromaDB add failed: '.(string) $response->getBody());
            }
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
                'include' => ['metadatas', 'documents', 'distances'],
            ];

            if (count($where) > 0) {
                $payload['where'] = $where;
            }

            $response = $this->client->post($this->getCollectionsPath()."/{$collectionId}/query", [
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
            $this->client->post($this->getCollectionsPath()."/{$collectionId}/delete", [
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

            $this->client->post($this->getCollectionsPath()."/{$collectionId}/update", [
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to update documents: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Get collection count for verification.
     */
    public function getCollectionCount(string $collectionId): int
    {
        try {
            $response = $this->client->get($this->getCollectionsPath()."/{$collectionId}/count");
            $data = json_decode((string) $response->getBody(), true);

            return is_int($data) ? $data : 0;
        } catch (GuzzleException $e) {
            return 0;
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->client->get('/api/v2/heartbeat');

            return $response->getStatusCode() === 200;
        } catch (ConnectException $e) {
            return false;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    /**
     * Get all document IDs and metadata from a collection.
     *
     * @return array{ids: array<string>, metadatas: array<array<string, mixed>>}
     */
    public function getAll(string $collectionId, int $limit = 10000): array
    {
        try {
            $response = $this->client->post($this->getCollectionsPath()."/{$collectionId}/get", [
                'json' => [
                    'limit' => $limit,
                    'include' => ['metadatas'],
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            return [
                'ids' => $data['ids'] ?? [],
                'metadatas' => $data['metadatas'] ?? [],
            ];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to get documents: '.$e->getMessage(), 0, $e);
        }
    }
}
