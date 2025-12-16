<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\ChromaDBClientInterface;

class MockChromaDBClient implements ChromaDBClientInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $collections = [];

    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $documents = [];

    private bool $available = true;

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrCreateCollection(string $name): array
    {
        if (! isset($this->collections[$name])) {
            $this->collections[$name] = [
                'id' => 'collection_'.md5($name),
                'name' => $name,
            ];
            $this->documents[$this->collections[$name]['id']] = [];
        }

        return $this->collections[$name];
    }

    public function add(
        string $collectionId,
        array $ids,
        array $embeddings,
        array $metadatas,
        ?array $documents = null
    ): void {
        if (! isset($this->documents[$collectionId])) {
            $this->documents[$collectionId] = [];
        }

        foreach ($ids as $index => $id) {
            $this->documents[$collectionId][$id] = [
                'id' => $id,
                'embedding' => $embeddings[$index] ?? [],
                'metadata' => $metadatas[$index] ?? [],
                'document' => $documents[$index] ?? null,
            ];
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
        if (! isset($this->documents[$collectionId])) {
            return [
                'ids' => [[]],
                'distances' => [[]],
                'metadatas' => [[]],
                'documents' => [[]],
            ];
        }

        $results = [];
        foreach ($this->documents[$collectionId] as $doc) {
            // Apply metadata filters
            $matchesFilter = true;
            foreach ($where as $key => $value) {
                if (! isset($doc['metadata'][$key]) || $doc['metadata'][$key] !== $value) {
                    $matchesFilter = false;
                    break;
                }
            }

            if (! $matchesFilter) {
                continue;
            }

            $distance = $this->calculateDistance($queryEmbedding, $doc['embedding']);
            $results[] = [
                'id' => $doc['id'],
                'distance' => $distance,
                'metadata' => $doc['metadata'],
                'document' => $doc['document'],
            ];
        }

        // Sort by distance
        usort($results, fn ($a, $b): int => $a['distance'] <=> $b['distance']);

        // Limit results
        $results = array_slice($results, 0, $nResults);

        // Format response
        $ids = [];
        $distances = [];
        $metadatas = [];
        $documents = [];

        foreach ($results as $result) {
            $ids[] = $result['id'];
            $distances[] = $result['distance'];
            $metadatas[] = $result['metadata'];
            $documents[] = $result['document'];
        }

        return [
            'ids' => [$ids],
            'distances' => [$distances],
            'metadatas' => [$metadatas],
            'documents' => [$documents],
        ];
    }

    public function delete(string $collectionId, array $ids): void
    {
        if (! isset($this->documents[$collectionId])) {
            return;
        }

        foreach ($ids as $id) {
            unset($this->documents[$collectionId][$id]);
        }
    }

    public function update(
        string $collectionId,
        array $ids,
        array $embeddings,
        array $metadatas,
        ?array $documents = null
    ): void {
        if (! isset($this->documents[$collectionId])) {
            $this->documents[$collectionId] = [];
        }

        foreach ($ids as $index => $id) {
            $this->documents[$collectionId][$id] = [
                'id' => $id,
                'embedding' => $embeddings[$index] ?? [],
                'metadata' => $metadatas[$index] ?? [],
                'document' => $documents[$index] ?? null,
            ];
        }
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * Calculate Euclidean distance between two vectors.
     *
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function calculateDistance(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return 1.0;
        }

        $sum = 0.0;
        for ($i = 0; $i < count($a); $i++) {
            $diff = $a[$i] - $b[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * Get stored documents for testing.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }
}
