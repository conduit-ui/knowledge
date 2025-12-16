<?php

declare(strict_types=1);

use App\Models\Entry;

describe('KnowledgeIndexCommand', function () {
    beforeEach(function () {
        Entry::factory()->count(5)->create();
    });

    it('shows semantic search not configured message', function () {
        $this->artisan('knowledge:index')
            ->assertSuccessful()
            ->expectsOutputToContain('Semantic indexing is not configured');
    });

    it('shows entry count', function () {
        $this->artisan('knowledge:index')
            ->assertSuccessful()
            ->expectsOutputToContain('Would index 5 new entries');
    });

    it('shows reindex count with force flag', function () {
        $this->artisan('knowledge:index', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Would reindex all 5 entries');
    });

    it('shows zero entries message when all indexed', function () {
        // Mark all entries as indexed
        Entry::query()->update(['embedding' => json_encode([1.0, 2.0, 3.0])]);

        $this->artisan('knowledge:index')
            ->assertSuccessful()
            ->expectsOutputToContain('No entries to index');
    });

    it('shows configuration instructions', function () {
        $this->artisan('knowledge:index')
            ->assertSuccessful()
            ->expectsOutputToContain('embedding provider is: none')
            ->expectsOutputToContain('OpenAI API (future)')
            ->expectsOutputToContain('ChromaDB (future)');
    });

    it('shows future functionality', function () {
        $this->artisan('knowledge:index')
            ->assertSuccessful()
            ->expectsOutputToContain('Generate embeddings for entry content')
            ->expectsOutputToContain('Store embeddings in the database')
            ->expectsOutputToContain('Enable semantic search via the --semantic flag');
    });
});
