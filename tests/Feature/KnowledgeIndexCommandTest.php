<?php

declare(strict_types=1);

use App\Models\Entry;

beforeEach(function () {
    Entry::factory()->count(5)->create();
});

test('index command shows semantic search not configured message', function () {
    $this->artisan('knowledge:index')
        ->assertSuccessful()
        ->expectsOutputToContain('Semantic indexing is not configured');
});

test('index command shows entry count', function () {
    $this->artisan('knowledge:index')
        ->assertSuccessful()
        ->expectsOutputToContain('Would index 5 new entries');
});

test('index command shows reindex count with force flag', function () {
    $this->artisan('knowledge:index', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Would reindex all 5 entries');
});

test('index command shows zero entries message when all indexed', function () {
    // Mark all entries as indexed
    Entry::query()->update(['embedding' => json_encode([1.0, 2.0, 3.0])]);

    $this->artisan('knowledge:index')
        ->assertSuccessful()
        ->expectsOutputToContain('No entries to index');
});

test('index command shows configuration instructions', function () {
    $this->artisan('knowledge:index')
        ->assertSuccessful()
        ->expectsOutputToContain('embedding provider is: none')
        ->expectsOutputToContain('OpenAI API (future)')
        ->expectsOutputToContain('ChromaDB (future)');
});

test('index command shows future functionality', function () {
    $this->artisan('knowledge:index')
        ->assertSuccessful()
        ->expectsOutputToContain('Generate embeddings for entry content')
        ->expectsOutputToContain('Store embeddings in the database')
        ->expectsOutputToContain('Enable semantic search via the --semantic flag');
});
