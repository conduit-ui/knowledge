<?php

declare(strict_types=1);

use App\Models\Entry;

beforeEach(function () {
    Entry::factory()->create([
        'title' => 'Laravel Testing',
        'content' => 'How to test Laravel applications',
        'tags' => ['laravel', 'testing'],
        'category' => 'tutorial',
        'confidence' => 95,
    ]);

    Entry::factory()->create([
        'title' => 'PHP Standards',
        'content' => 'PHP coding standards and PSR guidelines',
        'tags' => ['php', 'standards'],
        'category' => 'guide',
        'confidence' => 90,
    ]);
});

test('search command requires at least one parameter', function () {
    $this->artisan('knowledge:search')
        ->expectsOutput('Please provide at least one search parameter.')
        ->assertFailed();
});

test('search command finds entries by keyword', function () {
    $this->artisan('knowledge:search', ['query' => 'Laravel'])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry')
        ->expectsOutputToContain('Laravel Testing');
});

test('search command filters by tag', function () {
    $this->artisan('knowledge:search', ['--tag' => 'php'])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry')
        ->expectsOutputToContain('PHP Standards');
});

test('search command filters by category', function () {
    $this->artisan('knowledge:search', ['--category' => 'tutorial'])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry')
        ->expectsOutputToContain('Laravel Testing');
});

test('search command shows no results message', function () {
    $this->artisan('knowledge:search', ['query' => 'nonexistent'])
        ->assertSuccessful()
        ->expectsOutput('No entries found.');
});

test('search command supports semantic flag', function () {
    $this->artisan('knowledge:search', [
        'query' => 'Laravel',
        '--semantic' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry')
        ->expectsOutputToContain('Laravel Testing');
});

test('search command combines query and filters', function () {
    $this->artisan('knowledge:search', [
        'query' => 'Laravel',
        '--category' => 'tutorial',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry')
        ->expectsOutputToContain('Laravel Testing');
});

test('search command shows entry details', function () {
    $this->artisan('knowledge:search', ['query' => 'Laravel'])
        ->assertSuccessful()
        ->expectsOutputToContain('Laravel Testing')
        ->expectsOutputToContain('Category: tutorial');
});

test('search command truncates long content', function () {
    Entry::factory()->create([
        'title' => 'Long Content',
        'content' => str_repeat('a', 150),
        'confidence' => 100,
    ]);

    $this->artisan('knowledge:search', ['query' => 'Long'])
        ->assertSuccessful()
        ->expectsOutputToContain('...');
});
