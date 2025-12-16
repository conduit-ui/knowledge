<?php

declare(strict_types=1);

use App\Models\Entry;

test('search status command shows keyword search enabled', function () {
    $this->artisan('knowledge:search:status')
        ->assertSuccessful()
        ->expectsOutputToContain('Keyword Search: Enabled');
});

test('search status command shows semantic search not configured', function () {
    $this->artisan('knowledge:search:status')
        ->assertSuccessful()
        ->expectsOutputToContain('Semantic Search: Not Configured');
});

test('search status command shows embedding provider', function () {
    $this->artisan('knowledge:search:status')
        ->assertSuccessful()
        ->expectsOutputToContain('Provider: none');
});

test('search status command shows database statistics', function () {
    Entry::factory()->count(10)->create();

    $this->artisan('knowledge:search:status')
        ->assertSuccessful()
        ->expectsOutputToContain('Total entries: 10')
        ->expectsOutputToContain('Entries with embeddings: 0')
        ->expectsOutputToContain('Indexed: 0%');
});

test('search status command calculates indexed percentage', function () {
    Entry::factory()->count(10)->create();
    Entry::factory()->count(5)->create(['embedding' => json_encode([1.0, 2.0])]);

    $this->artisan('knowledge:search:status')
        ->assertSuccessful()
        ->expectsOutputToContain('Total entries: 15')
        ->expectsOutputToContain('Entries with embeddings: 5')
        ->expectsOutputToContain('Indexed: 33.3%');
});

test('search status command shows usage instructions', function () {
    $this->artisan('knowledge:search:status')
        ->assertSuccessful()
        ->expectsOutputToContain('Keyword search:  ./know knowledge:search "your query"')
        ->expectsOutputToContain('Index entries:   ./know knowledge:index');
});

test('search status command shows semantic search not available', function () {
    $this->artisan('knowledge:search:status')
        ->assertSuccessful()
        ->expectsOutputToContain('Semantic search: Not available');
});
