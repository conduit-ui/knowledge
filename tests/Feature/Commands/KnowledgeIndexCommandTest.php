<?php

declare(strict_types=1);

use App\Contracts\ChromaDBClientInterface;
use App\Models\Entry;
use Tests\Support\MockChromaDBClient;

describe('index command', function (): void {
    describe('--prune flag', function (): void {
        it('shows error when ChromaDB is not available', function (): void {
            $mockClient = new MockChromaDBClient;
            $mockClient->setAvailable(false);
            app()->instance(ChromaDBClientInterface::class, $mockClient);

            $this->artisan('index', ['--prune' => true])
                ->expectsOutput('ChromaDB is not available. Check connection settings.')
                ->assertExitCode(1);
        });

        it('reports no orphans when ChromaDB is in sync', function (): void {
            $mockClient = new MockChromaDBClient;
            app()->instance(ChromaDBClientInterface::class, $mockClient);

            // Create entry in SQLite
            $entry = Entry::factory()->create();

            // Add matching document to ChromaDB
            $collection = $mockClient->getOrCreateCollection('knowledge_entries');
            $mockClient->add(
                $collection['id'],
                ["entry_{$entry->id}"],
                [[0.1, 0.2, 0.3]],
                [['entry_id' => $entry->id, 'title' => $entry->title]]
            );

            $this->artisan('index', ['--prune' => true])
                ->expectsOutput('No orphaned entries found. ChromaDB is in sync.')
                ->assertExitCode(0);
        });

        it('detects orphaned documents with deleted entry_ids', function (): void {
            $mockClient = new MockChromaDBClient;
            app()->instance(ChromaDBClientInterface::class, $mockClient);

            // Add document to ChromaDB with entry_id that doesn't exist in SQLite
            $collection = $mockClient->getOrCreateCollection('knowledge_entries');
            $mockClient->add(
                $collection['id'],
                ['entry_999999'],
                [[0.1, 0.2, 0.3]],
                [['entry_id' => 999999, 'title' => 'Orphaned Entry']]
            );

            $this->artisan('index', ['--prune' => true, '--dry-run' => true])
                ->expectsOutputToContain('Found 1 orphaned documents')
                ->expectsOutputToContain('1 with deleted entry_ids')
                ->expectsOutput('Dry run - no changes made.')
                ->assertExitCode(0);
        });

        it('detects orphaned documents without entry_ids', function (): void {
            $mockClient = new MockChromaDBClient;
            app()->instance(ChromaDBClientInterface::class, $mockClient);

            // Add document to ChromaDB without entry_id (e.g., vision doc)
            $collection = $mockClient->getOrCreateCollection('knowledge_entries');
            $mockClient->add(
                $collection['id'],
                ['vision_doc_123'],
                [[0.1, 0.2, 0.3]],
                [['source' => 'vision', 'title' => 'Vision Document']]
            );

            $this->artisan('index', ['--prune' => true, '--dry-run' => true])
                ->expectsOutputToContain('Found 1 orphaned documents')
                ->expectsOutputToContain('1 without entry_ids')
                ->assertExitCode(0);
        });

        it('deletes orphans when user confirms', function (): void {
            $mockClient = new MockChromaDBClient;
            app()->instance(ChromaDBClientInterface::class, $mockClient);

            // Add orphaned document
            $collection = $mockClient->getOrCreateCollection('knowledge_entries');
            $mockClient->add(
                $collection['id'],
                ['entry_999999'],
                [[0.1, 0.2, 0.3]],
                [['entry_id' => 999999, 'title' => 'Orphaned Entry']]
            );

            $this->artisan('index', ['--prune' => true])
                ->expectsConfirmation('Delete these orphaned documents from ChromaDB?', 'yes')
                ->expectsOutputToContain('Deleted 1 orphaned documents')
                ->assertExitCode(0);

            // Verify document was deleted
            $docs = $mockClient->getAll($collection['id']);
            expect($docs['ids'])->toBeEmpty();
        });

        it('aborts when user declines confirmation', function (): void {
            $mockClient = new MockChromaDBClient;
            app()->instance(ChromaDBClientInterface::class, $mockClient);

            // Add orphaned document
            $collection = $mockClient->getOrCreateCollection('knowledge_entries');
            $mockClient->add(
                $collection['id'],
                ['entry_999999'],
                [[0.1, 0.2, 0.3]],
                [['entry_id' => 999999, 'title' => 'Orphaned Entry']]
            );

            $this->artisan('index', ['--prune' => true])
                ->expectsConfirmation('Delete these orphaned documents from ChromaDB?', 'no')
                ->expectsOutput('Aborted.')
                ->assertExitCode(0);

            // Verify document was NOT deleted
            $docs = $mockClient->getAll($collection['id']);
            expect($docs['ids'])->toHaveCount(1);
        });

        it('handles RuntimeException when connecting to ChromaDB', function (): void {
            $failingClient = new class implements ChromaDBClientInterface
            {
                public function getOrCreateCollection(string $name): array
                {
                    throw new \RuntimeException('Connection refused');
                }

                public function add(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void {}

                public function query(string $collectionId, array $queryEmbedding, int $nResults = 10, array $where = []): array
                {
                    return [];
                }

                public function delete(string $collectionId, array $ids): void {}

                public function update(string $collectionId, array $ids, array $embeddings, array $metadatas, ?array $documents = null): void {}

                public function isAvailable(): bool
                {
                    return true;
                }

                public function getAll(string $collectionId, int $limit = 10000): array
                {
                    return ['ids' => [], 'metadatas' => []];
                }
            };
            app()->instance(ChromaDBClientInterface::class, $failingClient);

            $this->artisan('index', ['--prune' => true])
                ->expectsOutputToContain('Failed to connect to ChromaDB')
                ->assertExitCode(1);
        });

        it('handles batch deletion failures gracefully', function (): void {
            $callCount = 0;
            $failingClient = new class($callCount) implements ChromaDBClientInterface
            {
                private int $callCount;

                public function __construct(int &$callCount)
                {
                    $this->callCount = &$callCount;
                }

                public function getOrCreateCollection(string $name): array
                {
                    return ['id' => 'test_collection', 'name' => $name];
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
                    return true;
                }

                public function getAll(string $collectionId, int $limit = 10000): array
                {
                    return [
                        'ids' => ['entry_999999'],
                        'metadatas' => [['entry_id' => 999999]],
                    ];
                }
            };
            app()->instance(ChromaDBClientInterface::class, $failingClient);

            $this->artisan('index', ['--prune' => true])
                ->expectsConfirmation('Delete these orphaned documents from ChromaDB?', 'yes')
                ->expectsOutputToContain('Failed to delete batch')
                ->assertExitCode(0);
        });
    });
});
