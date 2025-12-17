<?php

declare(strict_types=1);

use App\Contracts\EmbeddingServiceInterface;
use App\Models\Entry;
use App\Services\ChromaDBIndexService;
use Tests\Support\MockChromaDBClient;
use Tests\Support\MockEmbeddingService;

beforeEach(function () {
    $this->mockEmbedding = new MockEmbeddingService;
    $this->mockClient = new MockChromaDBClient;
    $this->mockIndexService = new ChromaDBIndexService($this->mockClient, $this->mockEmbedding);
});

describe('KnowledgeIndexCommand', function () {
    describe('when ChromaDB is not enabled', function () {
        beforeEach(function () {
            config(['search.chromadb.enabled' => false]);
        });

        it('shows not enabled warning', function () {
            $this->artisan('knowledge:index')
                ->expectsOutputToContain('ChromaDB is not enabled')
                ->expectsOutputToContain('knowledge:config set chromadb.enabled true')
                ->assertSuccessful();
        });

        it('shows dry-run for new entries', function () {
            Entry::factory()->count(3)->create(['embedding' => null]);

            $this->artisan('knowledge:index')
                ->expectsOutputToContain('Would index 3 new entries')
                ->assertSuccessful();
        });

        it('shows dry-run for reindexing with force', function () {
            Entry::factory()->count(5)->create(['embedding' => 'existing']);

            $this->artisan('knowledge:index', ['--force' => true])
                ->expectsOutputToContain('Would reindex all 5 entries')
                ->assertSuccessful();
        });

        it('shows no entries message when empty', function () {
            $this->artisan('knowledge:index')
                ->expectsOutputToContain('Would index 0 new entries')
                ->expectsOutputToContain('No entries to index')
                ->assertSuccessful();
        });

        it('shows configuration steps', function () {
            Entry::factory()->create(['embedding' => null]);

            $this->artisan('knowledge:index')
                ->expectsOutputToContain('Once configured, this command will:')
                ->expectsOutputToContain('Generate embeddings for entry content')
                ->expectsOutputToContain('Store embeddings in ChromaDB')
                ->assertSuccessful();
        });
    });

    describe('when ChromaDB is enabled but embedding service fails', function () {
        beforeEach(function () {
            config(['search.chromadb.enabled' => true]);

            $this->app->bind(EmbeddingServiceInterface::class, function () {
                return new class implements EmbeddingServiceInterface
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
            });
        });

        it('shows embedding service warning', function () {
            $this->artisan('knowledge:index')
                ->expectsOutputToContain('Embedding service is not responding')
                ->expectsOutputToContain('knowledge:serve start')
                ->assertSuccessful();
        });
    });

    describe('when ChromaDB and embedding service are available', function () {
        beforeEach(function () {
            config(['search.chromadb.enabled' => true]);

            $this->app->bind(EmbeddingServiceInterface::class, fn () => $this->mockEmbedding);
            $this->app->bind(ChromaDBIndexService::class, fn () => $this->mockIndexService);
        });

        it('indexes new entries', function () {
            Entry::factory()->count(3)->create(['embedding' => null]);

            $this->artisan('knowledge:index')
                ->expectsOutputToContain('Indexing 3 entries to ChromaDB')
                ->expectsOutputToContain('Indexed 3 entries successfully')
                ->assertSuccessful();
        });

        it('shows already indexed message when no new entries', function () {
            Entry::factory()->count(2)->create(['embedding' => 'existing']);

            $this->artisan('knowledge:index')
                ->expectsOutputToContain('All entries are already indexed')
                ->assertSuccessful();
        });

        it('reindexes all entries with force flag', function () {
            Entry::factory()->count(3)->create(['embedding' => 'existing']);

            $this->artisan('knowledge:index', ['--force' => true])
                ->expectsOutputToContain('Indexing 3 entries to ChromaDB')
                ->expectsOutputToContain('Indexed 3 entries successfully')
                ->assertSuccessful();
        });

        it('respects batch size option', function () {
            Entry::factory()->count(5)->create(['embedding' => null]);

            $this->artisan('knowledge:index', ['--batch' => 2])
                ->expectsOutputToContain('Indexed 5 entries successfully')
                ->assertSuccessful();
        });

        it('handles singular entry correctly', function () {
            Entry::factory()->create(['embedding' => null]);

            $this->artisan('knowledge:index')
                ->expectsOutputToContain('Indexing 1 entry to ChromaDB')
                ->expectsOutputToContain('Indexed 1 entry successfully')
                ->assertSuccessful();
        });
    });

    describe('error handling', function () {
        beforeEach(function () {
            config(['search.chromadb.enabled' => true]);
            $this->app->bind(EmbeddingServiceInterface::class, fn () => $this->mockEmbedding);
        });

        it('falls back to individual indexing on batch failure', function () {
            $failingIndexService = Mockery::mock(ChromaDBIndexService::class);
            $failingIndexService->shouldReceive('indexBatch')
                ->andThrow(new RuntimeException('Batch failed'));
            $failingIndexService->shouldReceive('indexEntry')
                ->andReturnNull();

            $this->app->bind(ChromaDBIndexService::class, fn () => $failingIndexService);

            Entry::factory()->count(2)->create(['embedding' => null]);

            $this->artisan('knowledge:index')
                ->expectsOutputToContain('Indexed 2 entries successfully')
                ->assertSuccessful();
        });

        it('reports failed entries when individual indexing fails', function () {
            $failingIndexService = Mockery::mock(ChromaDBIndexService::class);
            $failingIndexService->shouldReceive('indexBatch')
                ->andThrow(new RuntimeException('Batch failed'));
            $failingIndexService->shouldReceive('indexEntry')
                ->andThrow(new RuntimeException('Individual failed'));

            $this->app->bind(ChromaDBIndexService::class, fn () => $failingIndexService);

            Entry::factory()->count(2)->create(['embedding' => null]);

            $this->artisan('knowledge:index')
                ->expectsOutputToContain('Failed to index 2 entries')
                ->assertFailed();
        });
    });
});

describe('command signature', function () {
    it('has the correct signature', function () {
        $command = $this->app->make(\App\Commands\KnowledgeIndexCommand::class);
        expect($command->getName())->toBe('knowledge:index');
    });

    it('has force option', function () {
        $command = $this->app->make(\App\Commands\KnowledgeIndexCommand::class);
        $definition = $command->getDefinition();
        expect($definition->hasOption('force'))->toBeTrue();
    });

    it('has batch option with default', function () {
        $command = $this->app->make(\App\Commands\KnowledgeIndexCommand::class);
        $definition = $command->getDefinition();
        expect($definition->hasOption('batch'))->toBeTrue();
        expect($definition->getOption('batch')->getDefault())->toBe('100');
    });
});
