<?php

declare(strict_types=1);

use App\Models\Entry;

beforeEach(function () {
    Entry::query()->delete();
});

it('displays entries by a specific author', function () {
    Entry::factory()->create([
        'title' => 'Entry 1',
        'author' => 'John Doe',
    ]);

    Entry::factory()->create([
        'title' => 'Entry 2',
        'author' => 'John Doe',
    ]);

    Entry::factory()->create([
        'title' => 'Entry 3',
        'author' => 'Jane Smith',
    ]);

    $this->artisan('knowledge:git:author', ['name' => 'John Doe'])
        ->expectsOutputToContain('Entry 1')
        ->expectsOutputToContain('Entry 2')
        ->assertSuccessful();
});

it('shows message when no entries found for author', function () {
    $this->artisan('knowledge:git:author', ['name' => 'Unknown Author'])
        ->expectsOutputToContain('No entries found')
        ->assertSuccessful();
});

it('displays entry details for author', function () {
    Entry::factory()->create([
        'title' => 'Test Entry',
        'author' => 'John Doe',
        'content' => 'Test content',
        'category' => 'testing',
        'commit' => 'abc123',
    ]);

    $this->artisan('knowledge:git:author', ['name' => 'John Doe'])
        ->expectsOutputToContain('Test Entry')
        ->expectsOutputToContain('testing')
        ->assertSuccessful();
});
