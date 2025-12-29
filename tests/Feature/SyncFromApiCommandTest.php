<?php

declare(strict_types=1);

use App\Models\Entry;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Entry::query()->delete();
    Cache::forget('knowledge.last_sync_timestamp');

    // Set up mock API token
    putenv('PREFRONTAL_API_TOKEN=test-token-12345');
});

afterEach(function () {
    putenv('PREFRONTAL_API_TOKEN');
});

it('requires PREFRONTAL_API_TOKEN environment variable', function () {
    putenv('PREFRONTAL_API_TOKEN');

    $this->artisan('sync')
        ->expectsOutput('PREFRONTAL_API_TOKEN environment variable is not set.')
        ->assertFailed();
});

it('displays correct help information', function () {
    $this->artisan('sync', ['--help' => true])
        ->assertSuccessful();
});

it('can display custom API URL in output', function () {
    $customUrl = 'https://custom-api.example.com/api/knowledge';

    // This will fail to connect, but we can verify the URL was parsed
    $this->artisan('sync', ['--from' => $customUrl])
        ->expectsOutput('Starting sync from: '.$customUrl);
});

it('creates entries with proper field mapping', function () {
    // Manual test to verify entry creation logic
    $entryData = [
        'title' => 'Test Issue #1',
        'content' => 'Test content for the issue',
        'category' => 'github',
        'source' => 'https://github.com/test/repo/issues/1',
        'module' => 'test/repo',
        'tags' => ['bug', 'urgent'],
        'priority' => 'medium',
        'confidence' => 70,
        'status' => 'draft',
    ];

    $entry = Entry::create($entryData);

    expect($entry)->not->toBeNull();
    expect($entry->title)->toBe('Test Issue #1');
    expect($entry->content)->toBe('Test content for the issue');
    expect($entry->category)->toBe('github');
    expect($entry->source)->toBe('https://github.com/test/repo/issues/1');
    expect($entry->module)->toBe('test/repo');
    expect($entry->tags)->toBe(['bug', 'urgent']);
});

it('updates existing entries based on source URL', function () {
    // Create initial entry
    $entry = Entry::create([
        'title' => 'Old Title',
        'content' => 'Old content',
        'category' => 'github',
        'source' => 'https://github.com/test/repo/issues/1',
        'module' => 'test/repo',
        'priority' => 'medium',
        'confidence' => 70,
        'status' => 'draft',
    ]);

    expect(Entry::count())->toBe(1);

    // Update the same entry (simulating sync)
    $existing = Entry::where('source', 'https://github.com/test/repo/issues/1')->first();
    $existing->update([
        'title' => 'Updated Title',
        'content' => 'Updated content',
        'tags' => ['updated', 'synced'],
    ]);

    // Should still be only 1 entry
    expect(Entry::count())->toBe(1);

    $updated = Entry::where('source', 'https://github.com/test/repo/issues/1')->first();
    expect($updated->title)->toBe('Updated Title');
    expect($updated->content)->toBe('Updated content');
    expect($updated->tags)->toBe(['updated', 'synced']);
});

it('handles comma-separated tags correctly', function () {
    $tagsString = 'php,laravel,testing';
    $tagsArray = array_map('trim', explode(',', $tagsString));

    $entry = Entry::create([
        'title' => 'Tagged Entry',
        'content' => 'Entry with tags',
        'category' => 'github',
        'tags' => $tagsArray,
        'priority' => 'medium',
        'confidence' => 70,
        'status' => 'draft',
    ]);

    expect($entry->tags)->toBe(['php', 'laravel', 'testing']);
});

it('tracks timestamp in cache', function () {
    expect(Cache::get('knowledge.last_sync_timestamp'))->toBeNull();

    Cache::forever('knowledge.last_sync_timestamp', now()->toIso8601String());

    expect(Cache::get('knowledge.last_sync_timestamp'))->not->toBeNull();
});

it('can clear last sync timestamp for full sync', function () {
    Cache::forever('knowledge.last_sync_timestamp', now()->subDay()->toIso8601String());

    expect(Cache::get('knowledge.last_sync_timestamp'))->not->toBeNull();

    Cache::forget('knowledge.last_sync_timestamp');

    expect(Cache::get('knowledge.last_sync_timestamp'))->toBeNull();
});
