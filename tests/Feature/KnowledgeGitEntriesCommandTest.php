<?php

declare(strict_types=1);

use App\Models\Entry;

beforeEach(function () {
    Entry::query()->delete();
});

it('displays entries for a specific commit', function () {
    Entry::factory()->create([
        'title' => 'Entry 1',
        'commit' => 'abc123',
    ]);

    Entry::factory()->create([
        'title' => 'Entry 2',
        'commit' => 'abc123',
    ]);

    Entry::factory()->create([
        'title' => 'Entry 3',
        'commit' => 'def456',
    ]);

    $this->artisan('git:entries', ['commit' => 'abc123'])
        ->expectsOutputToContain('Entry 1')
        ->expectsOutputToContain('Entry 2')
        ->assertSuccessful();
});

it('shows message when no entries found for commit', function () {
    $this->artisan('git:entries', ['commit' => 'nonexistent'])
        ->expectsOutputToContain('No entries found')
        ->assertSuccessful();
});

it('displays entry details', function () {
    Entry::factory()->create([
        'title' => 'Test Entry',
        'commit' => 'abc123',
        'content' => 'Test content',
        'category' => 'testing',
    ]);

    $this->artisan('git:entries', ['commit' => 'abc123'])
        ->expectsOutputToContain('Test Entry')
        ->expectsOutputToContain('testing')
        ->assertSuccessful();
});
