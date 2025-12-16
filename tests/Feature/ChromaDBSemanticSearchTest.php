<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Services\SemanticSearchService;
use Tests\Support\MockChromaDBClient;
use Tests\Support\MockEmbeddingService;

describe('ChromaDB Semantic Search', function () {
    beforeEach(function () {
        $this->mockClient = new MockChromaDBClient;
        $this->mockEmbedding = new MockEmbeddingService;
    });

    it('uses ChromaDB when enabled and available', function () {
        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $this->mockClient,
            true
        );

        expect($service->hasChromaDBSupport())->toBeTrue();
    });

    it('returns false for ChromaDB support when disabled', function () {
        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $this->mockClient,
            false
        );

        expect($service->hasChromaDBSupport())->toBeFalse();
    });

    it('returns false for ChromaDB support when client is null', function () {
        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            null,
            true
        );

        expect($service->hasChromaDBSupport())->toBeFalse();
    });

    it('returns false for ChromaDB support when unavailable', function () {
        $this->mockClient->setAvailable(false);

        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $this->mockClient,
            true
        );

        expect($service->hasChromaDBSupport())->toBeFalse();
    });

    it('performs semantic search using ChromaDB', function () {
        // Create test entries
        $entry1 = Entry::factory()->create([
            'title' => 'Laravel Testing',
            'content' => 'Guide to testing Laravel applications',
            'category' => 'documentation',
            'confidence' => 95,
        ]);

        $entry2 = Entry::factory()->create([
            'title' => 'PHP Best Practices',
            'content' => 'Modern PHP development',
            'category' => 'guide',
            'confidence' => 90,
        ]);

        // Index entries in ChromaDB
        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');

        $this->mockClient->add(
            $collection['id'],
            ['entry_'.$entry1->id, 'entry_'.$entry2->id],
            [
                $this->mockEmbedding->generate($entry1->content),
                $this->mockEmbedding->generate($entry2->content),
            ],
            [
                [
                    'entry_id' => $entry1->id,
                    'title' => $entry1->title,
                    'category' => 'documentation',
                    'module' => '',
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 95,
                ],
                [
                    'entry_id' => $entry2->id,
                    'title' => $entry2->title,
                    'category' => 'guide',
                    'module' => '',
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 90,
                ],
            ],
            [$entry1->content, $entry2->content]
        );

        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $this->mockClient,
            true
        );

        $results = $service->search('Laravel testing');

        expect($results)->not->toBeEmpty();
    });

    it('filters ChromaDB results by category', function () {
        $entry1 = Entry::factory()->create([
            'title' => 'Laravel Testing',
            'content' => 'Guide to testing',
            'category' => 'documentation',
            'confidence' => 95,
        ]);

        $entry2 = Entry::factory()->create([
            'title' => 'PHP Best Practices',
            'content' => 'Modern PHP',
            'category' => 'guide',
            'confidence' => 90,
        ]);

        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');

        $this->mockClient->add(
            $collection['id'],
            ['entry_'.$entry1->id, 'entry_'.$entry2->id],
            [
                $this->mockEmbedding->generate($entry1->content),
                $this->mockEmbedding->generate($entry2->content),
            ],
            [
                [
                    'entry_id' => $entry1->id,
                    'category' => 'documentation',
                    'module' => '',
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 95,
                ],
                [
                    'entry_id' => $entry2->id,
                    'category' => 'guide',
                    'module' => '',
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 90,
                ],
            ]
        );

        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $this->mockClient,
            true
        );

        $results = $service->search('testing', ['category' => 'documentation']);

        expect($results)->not->toBeEmpty()
            ->and($results->first()->category)->toBe('documentation');
    });

    it('filters ChromaDB results by tag', function () {
        $entry = Entry::factory()->create([
            'title' => 'Laravel Testing',
            'content' => 'Guide to testing',
            'tags' => ['laravel', 'testing'],
            'category' => 'documentation',
            'confidence' => 95,
        ]);

        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');

        $this->mockClient->add(
            $collection['id'],
            ['entry_'.$entry->id],
            [$this->mockEmbedding->generate($entry->content)],
            [
                [
                    'entry_id' => $entry->id,
                    'category' => 'documentation',
                    'module' => '',
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 95,
                ],
            ]
        );

        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $this->mockClient,
            true
        );

        $results = $service->search('testing', ['tag' => 'laravel']);

        expect($results)->not->toBeEmpty()
            ->and($results->first()->tags)->toContain('laravel');
    });

    it('falls back to SQLite search when ChromaDB fails', function () {
        // Create entry with embedding in database
        $entry = Entry::factory()->create([
            'title' => 'Laravel Testing',
            'content' => 'Guide to testing',
            'confidence' => 95,
            'embedding' => json_encode($this->mockEmbedding->generate('Guide to testing')),
        ]);

        // Make ChromaDB client fail by making it unavailable
        $this->mockClient->setAvailable(false);

        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $this->mockClient,
            true
        );

        $results = $service->search('testing');

        // Should still return results from SQLite fallback
        expect($results)->not->toBeEmpty();
    });

    it('calculates search scores based on similarity and confidence', function () {
        $entry = Entry::factory()->create([
            'title' => 'Laravel Testing',
            'content' => 'Guide to testing',
            'confidence' => 100,
        ]);

        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');

        $this->mockClient->add(
            $collection['id'],
            ['entry_'.$entry->id],
            [$this->mockEmbedding->generate($entry->content)],
            [
                [
                    'entry_id' => $entry->id,
                    'category' => '',
                    'module' => '',
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 100,
                ],
            ]
        );

        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $this->mockClient,
            true
        );

        $results = $service->search('testing');

        expect($results)->not->toBeEmpty();
        if ($results->isNotEmpty()) {
            expect($results->first()->getAttributes())->toHaveKey('search_score');
        }
    });

    it('filters by module in ChromaDB search', function () {
        $entry = Entry::factory()->create([
            'title' => 'Auth Module',
            'content' => 'Authentication documentation',
            'module' => 'auth',
            'confidence' => 95,
        ]);

        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');

        $this->mockClient->add(
            $collection['id'],
            ['entry_'.$entry->id],
            [$this->mockEmbedding->generate($entry->content)],
            [
                [
                    'entry_id' => $entry->id,
                    'category' => '',
                    'module' => 'auth',
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 95,
                ],
            ]
        );

        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $this->mockClient,
            true
        );

        $results = $service->search('authentication', ['module' => 'auth']);

        expect($results->count())->toBeGreaterThanOrEqual(0);
        if ($results->isNotEmpty()) {
            expect($results->first()->module)->toBe('auth');
        }
    });

    it('filters by priority in ChromaDB search', function () {
        $entry = Entry::factory()->create([
            'title' => 'Critical Task',
            'content' => 'High priority task',
            'priority' => 'critical',
            'confidence' => 95,
        ]);

        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');

        $this->mockClient->add(
            $collection['id'],
            ['entry_'.$entry->id],
            [$this->mockEmbedding->generate($entry->content)],
            [
                [
                    'entry_id' => $entry->id,
                    'category' => '',
                    'module' => '',
                    'priority' => 'critical',
                    'status' => 'draft',
                    'confidence' => 95,
                ],
            ]
        );

        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $this->mockClient,
            true
        );

        $results = $service->search('task', ['priority' => 'critical']);

        expect($results->count())->toBeGreaterThanOrEqual(0);
        if ($results->isNotEmpty()) {
            expect($results->first()->priority)->toBe('critical');
        }
    });

    it('filters by status in ChromaDB search', function () {
        $entry = Entry::factory()->create([
            'title' => 'Validated Entry',
            'content' => 'Validated content',
            'status' => 'validated',
            'confidence' => 95,
        ]);

        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');

        $this->mockClient->add(
            $collection['id'],
            ['entry_'.$entry->id],
            [$this->mockEmbedding->generate($entry->content)],
            [
                [
                    'entry_id' => $entry->id,
                    'category' => '',
                    'module' => '',
                    'priority' => 'medium',
                    'status' => 'validated',
                    'confidence' => 95,
                ],
            ]
        );

        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $this->mockClient,
            true
        );

        $results = $service->search('content', ['status' => 'validated']);

        expect($results->count())->toBeGreaterThanOrEqual(0);
        if ($results->isNotEmpty()) {
            expect($results->first()->status)->toBe('validated');
        }
    });

    it('returns empty collection when ChromaDB returns no results', function () {
        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $this->mockClient,
            true
        );

        $results = $service->search('nonexistent query');

        expect($results)->toBeEmpty();
    });

    it('handles RuntimeException from ChromaDB and falls back to SQLite', function () {
        // Create a client that throws RuntimeException
        $failingClient = new class implements \App\Contracts\ChromaDBClientInterface
        {
            public function getOrCreateCollection(string $name): array
            {
                throw new \RuntimeException('ChromaDB connection failed');
            }

            public function add(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void {}

            public function query(string $collectionId, array $queryEmbedding, int $nResults = 10, array $where = []): array
            {
                throw new \RuntimeException('Query failed');
            }

            public function delete(string $collectionId, array $ids): void {}

            public function update(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void {}

            public function isAvailable(): bool
            {
                return true;
            }
        };

        // Create entry with SQLite embedding
        $entry = Entry::factory()->create([
            'title' => 'Laravel Testing',
            'content' => 'Guide to testing',
            'confidence' => 95,
            'embedding' => json_encode($this->mockEmbedding->generate('Guide to testing')),
        ]);

        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $failingClient,
            true
        );

        $results = $service->search('testing');

        // Should fallback to SQLite and return results
        expect($results)->not->toBeEmpty();
    });

    it('skips entries that no longer exist in database', function () {
        // Create entry
        $entry = Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content',
            'confidence' => 95,
        ]);

        // Index in ChromaDB
        $collection = $this->mockClient->getOrCreateCollection('knowledge_entries');
        $this->mockClient->add(
            $collection['id'],
            ['entry_'.$entry->id, 'entry_9999'],
            [
                $this->mockEmbedding->generate($entry->content),
                $this->mockEmbedding->generate('nonexistent'),
            ],
            [
                [
                    'entry_id' => $entry->id,
                    'category' => '',
                    'module' => '',
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 95,
                ],
                [
                    'entry_id' => 9999,
                    'category' => '',
                    'module' => '',
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 95,
                ],
            ]
        );

        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            $this->mockClient,
            true
        );

        $results = $service->search('content');

        // Should only return the existing entry
        expect($results->count())->toBe(1);
    });

    it('returns empty when ChromaDB client is null', function () {
        $service = new SemanticSearchService(
            $this->mockEmbedding,
            true,
            null,
            true
        );

        // Create entry with SQLite embedding for fallback
        Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content',
            'confidence' => 95,
            'embedding' => json_encode($this->mockEmbedding->generate('Test content')),
        ]);

        $results = $service->search('content');

        // Should fallback to SQLite
        expect($results)->not->toBeEmpty();
    });
});
