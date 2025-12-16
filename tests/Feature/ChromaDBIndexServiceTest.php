<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Services\ChromaDBIndexService;
use Tests\Support\MockChromaDBClient;
use Tests\Support\MockEmbeddingService;

describe('ChromaDBIndexService', function () {
    beforeEach(function () {
        $this->mockClient = new MockChromaDBClient;
        $this->mockEmbedding = new MockEmbeddingService;
        $this->service = new ChromaDBIndexService($this->mockClient, $this->mockEmbedding);
    });

    it('indexes an entry', function () {
        $entry = Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content for indexing',
            'category' => 'test',
            'confidence' => 90,
        ]);

        $this->service->indexEntry($entry);

        $docs = $this->mockClient->getDocuments();
        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');

        expect($docs[$collection['id']])->toHaveKey('entry_'.$entry->id);
    });

    it('updates an entry in the index', function () {
        $entry = Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Original content',
            'confidence' => 90,
        ]);

        $this->service->indexEntry($entry);

        $entry->content = 'Updated content';
        $entry->save();

        $this->service->updateEntry($entry);

        $docs = $this->mockClient->getDocuments();
        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');

        expect($docs[$collection['id']]['entry_'.$entry->id]['document'])->toBe('Updated content');
    });

    it('removes an entry from the index', function () {
        $entry = Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content',
        ]);

        $this->service->indexEntry($entry);
        $this->service->removeEntry($entry);

        $docs = $this->mockClient->getDocuments();
        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');

        expect($docs[$collection['id']])->not->toHaveKey('entry_'.$entry->id);
    });

    it('indexes multiple entries in bulk', function () {
        $entries = Entry::factory()->count(3)->create();

        $this->service->indexBatch($entries);

        $docs = $this->mockClient->getDocuments();
        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');

        expect($docs[$collection['id']])->toHaveCount(3);
    });

    it('uses existing embedding if available', function () {
        $embedding = json_encode([0.1, 0.2, 0.3]);

        $entry = Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content',
            'embedding' => $embedding,
        ]);

        $this->service->indexEntry($entry);

        $docs = $this->mockClient->getDocuments();
        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');

        expect($docs[$collection['id']]['entry_'.$entry->id]['embedding'])->toBe([0.1, 0.2, 0.3]);
    });

    it('generates and stores embedding if not available', function () {
        $entry = Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content',
            'embedding' => null,
        ]);

        $this->service->indexEntry($entry);

        $entry->refresh();

        expect($entry->embedding)->not->toBeNull();
    });

    it('skips entries with empty embeddings', function () {
        // Create a mock embedding service that returns empty array
        $emptyEmbedding = new class implements \App\Contracts\EmbeddingServiceInterface
        {
            public function generate(string $text): array
            {
                return [];
            }

            public function similarity(array $a, array $b): float
            {
                return 0.0;
            }
        };

        $mockClient = new MockChromaDBClient;
        $service = new ChromaDBIndexService($mockClient, $emptyEmbedding);

        $entry = Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content',
            'embedding' => null,
        ]);

        $service->indexEntry($entry);

        $docs = $mockClient->getDocuments();

        // Collection may or may not be created, but no documents should be indexed
        $hasDocuments = false;
        foreach ($docs as $collectionDocs) {
            if (! empty($collectionDocs)) {
                $hasDocuments = true;
                break;
            }
        }

        expect($hasDocuments)->toBeFalse();
    });

    it('includes metadata when indexing', function () {
        $entry = Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content',
            'category' => 'documentation',
            'module' => 'auth',
            'priority' => 'high',
            'status' => 'validated',
            'confidence' => 95,
        ]);

        $this->service->indexEntry($entry);

        $docs = $this->mockClient->getDocuments();
        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');
        $metadata = $docs[$collection['id']]['entry_'.$entry->id]['metadata'];

        expect($metadata['category'])->toBe('documentation')
            ->and($metadata['module'])->toBe('auth')
            ->and($metadata['priority'])->toBe('high')
            ->and($metadata['status'])->toBe('validated')
            ->and($metadata['confidence'])->toBe(95);
    });

    it('handles ChromaDB failures gracefully', function () {
        // Make client unavailable
        $this->mockClient->setAvailable(false);

        $entry = Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content',
        ]);

        // Should not throw exception
        $this->service->indexEntry($entry);

        expect(true)->toBeTrue();
    });

    it('skips empty embeddings in batch indexing', function () {
        // Create a mock embedding service that returns empty for specific text
        $conditionalEmbedding = new class implements \App\Contracts\EmbeddingServiceInterface
        {
            private int $callCount = 0;

            public function generate(string $text): array
            {
                $this->callCount++;

                // Return empty for first call, valid for others
                if ($this->callCount === 1) {
                    return [];
                }

                return [0.1, 0.2, 0.3];
            }

            public function similarity(array $a, array $b): float
            {
                return 0.5;
            }
        };

        $service = new ChromaDBIndexService($this->mockClient, $conditionalEmbedding);

        $entries = Entry::factory()->count(3)->create();

        $service->indexBatch($entries);

        $docs = $this->mockClient->getDocuments();
        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');

        // Should have 2 entries (skipped first one with empty embedding)
        expect($docs[$collection['id']])->toHaveCount(2);
    });

    it('handles exceptions during indexEntry gracefully', function () {
        // Create a client that throws on add
        $failingClient = new class implements \App\Contracts\ChromaDBClientInterface
        {
            public function getOrCreateCollection(string $name): array
            {
                throw new \RuntimeException('Connection failed');
            }

            public function add(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void
            {
                throw new \RuntimeException('Add failed');
            }

            public function query(string $collectionId, array $queryEmbedding, int $nResults = 10, array $where = []): array
            {
                return [];
            }

            public function delete(string $collectionId, array $ids): void {}

            public function update(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void {}

            public function isAvailable(): bool
            {
                return false;
            }
        };

        $service = new ChromaDBIndexService($failingClient, $this->mockEmbedding);

        $entry = Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content',
        ]);

        // Should not throw exception
        $service->indexEntry($entry);

        expect(true)->toBeTrue();
    });

    it('handles exceptions during updateEntry gracefully', function () {
        // Create a client that throws on update
        $failingClient = new class implements \App\Contracts\ChromaDBClientInterface
        {
            public function getOrCreateCollection(string $name): array
            {
                throw new \RuntimeException('Connection failed');
            }

            public function add(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void {}

            public function query(string $collectionId, array $queryEmbedding, int $nResults = 10, array $where = []): array
            {
                return [];
            }

            public function delete(string $collectionId, array $ids): void {}

            public function update(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void
            {
                throw new \RuntimeException('Update failed');
            }

            public function isAvailable(): bool
            {
                return false;
            }
        };

        $service = new ChromaDBIndexService($failingClient, $this->mockEmbedding);

        $entry = Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content',
        ]);

        // Should not throw exception
        $service->updateEntry($entry);

        expect(true)->toBeTrue();
    });

    it('handles exceptions during removeEntry gracefully', function () {
        // Create a client that throws on delete
        $failingClient = new class implements \App\Contracts\ChromaDBClientInterface
        {
            public function getOrCreateCollection(string $name): array
            {
                throw new \RuntimeException('Connection failed');
            }

            public function add(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void {}

            public function query(string $collectionId, array $queryEmbedding, int $nResults = 10, array $where = []): array
            {
                return [];
            }

            public function delete(string $collectionId, array $ids): void
            {
                throw new \RuntimeException('Delete failed');
            }

            public function update(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void {}

            public function isAvailable(): bool
            {
                return false;
            }
        };

        $service = new ChromaDBIndexService($failingClient, $this->mockEmbedding);

        $entry = Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content',
        ]);

        // Should not throw exception
        $service->removeEntry($entry);

        expect(true)->toBeTrue();
    });

    it('handles exceptions during batch indexing gracefully', function () {
        // Create a client that throws on add
        $failingClient = new class implements \App\Contracts\ChromaDBClientInterface
        {
            public function getOrCreateCollection(string $name): array
            {
                throw new \RuntimeException('Connection failed');
            }

            public function add(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void
            {
                throw new \RuntimeException('Add failed');
            }

            public function query(string $collectionId, array $queryEmbedding, int $nResults = 10, array $where = []): array
            {
                return [];
            }

            public function delete(string $collectionId, array $ids): void {}

            public function update(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void {}

            public function isAvailable(): bool
            {
                return false;
            }
        };

        $service = new ChromaDBIndexService($failingClient, $this->mockEmbedding);

        $entries = Entry::factory()->count(3)->create();

        // Should not throw exception
        $service->indexBatch($entries);

        expect(true)->toBeTrue();
    });
});
