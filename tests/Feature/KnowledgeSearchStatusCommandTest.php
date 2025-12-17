<?php

declare(strict_types=1);

use App\Contracts\EmbeddingServiceInterface;
use App\Models\Entry;
use Tests\Support\MockEmbeddingService;

describe('KnowledgeSearchStatusCommand', function () {
    it('shows keyword search enabled', function () {
        $this->artisan('search:status')
            ->assertSuccessful()
            ->expectsOutputToContain('Keyword Search: Enabled');
    });

    it('shows semantic search not configured', function () {
        $this->artisan('search:status')
            ->assertSuccessful()
            ->expectsOutputToContain('Semantic Search: Not Configured');
    });

    it('shows embedding provider', function () {
        $this->artisan('search:status')
            ->assertSuccessful()
            ->expectsOutputToContain('Provider: none');
    });

    it('shows database statistics', function () {
        Entry::factory()->count(10)->create();

        $this->artisan('search:status')
            ->assertSuccessful()
            ->expectsOutputToContain('Total entries: 10')
            ->expectsOutputToContain('Entries with embeddings: 0')
            ->expectsOutputToContain('Indexed: 0%');
    });

    it('calculates indexed percentage', function () {
        Entry::factory()->count(10)->create();
        Entry::factory()->count(5)->create(['embedding' => json_encode([1.0, 2.0])]);

        $this->artisan('search:status')
            ->assertSuccessful()
            ->expectsOutputToContain('Total entries: 15')
            ->expectsOutputToContain('Entries with embeddings: 5')
            ->expectsOutputToContain('Indexed: 33.3%');
    });

    it('shows usage instructions', function () {
        $this->artisan('search:status')
            ->assertSuccessful()
            ->expectsOutputToContain('Keyword search:  ./know knowledge:search "your query"')
            ->expectsOutputToContain('Index entries:   ./know knowledge:index');
    });

    it('shows semantic search not available', function () {
        $this->artisan('search:status')
            ->assertSuccessful()
            ->expectsOutputToContain('Semantic search: Not available');
    });

    it('shows semantic search enabled when configured', function () {
        config(['search.semantic_enabled' => true]);
        config(['search.embedding_provider' => 'mock']);

        $this->app->bind(EmbeddingServiceInterface::class, MockEmbeddingService::class);

        $this->artisan('search:status')
            ->assertSuccessful()
            ->expectsOutputToContain('Semantic Search: Enabled')
            ->expectsOutputToContain('Provider: mock')
            ->expectsOutputToContain('Semantic search: ./know knowledge:search "your query" --semantic');
    });
});
