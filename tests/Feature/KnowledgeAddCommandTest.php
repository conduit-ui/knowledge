<?php

declare(strict_types=1);

use App\Models\Entry;

beforeEach(function () {
    Entry::query()->delete();
});

it('creates a knowledge entry with required fields', function () {
    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Test content',
    ])->assertSuccessful();

    $entry = Entry::first();
    expect($entry)->not->toBeNull();
    expect($entry->title)->toBe('Test Entry');
    expect($entry->content)->toBe('Test content');
});

it('auto-populates git fields when in a git repository', function () {
    $this->artisan('add', [
        'title' => 'Git Auto Entry',
        '--content' => 'Content with git context',
    ])->assertSuccessful();

    $entry = Entry::first();
    expect($entry)->not->toBeNull();
    expect($entry->branch)->not->toBeNull();
    expect($entry->commit)->not->toBeNull();
});

it('skips git detection with --no-git flag', function () {
    $this->artisan('add', [
        'title' => 'No Git Entry',
        '--content' => 'Content without git',
        '--no-git' => true,
    ])->assertSuccessful();

    $entry = Entry::first();
    expect($entry)->not->toBeNull();
    expect($entry->branch)->toBeNull();
    expect($entry->commit)->toBeNull();
    expect($entry->repo)->toBeNull();
});

it('allows manual git field overrides', function () {
    $this->artisan('add', [
        'title' => 'Manual Git Entry',
        '--content' => 'Content with manual git',
        '--repo' => 'custom/repo',
        '--branch' => 'custom-branch',
        '--commit' => 'abc123',
    ])->assertSuccessful();

    $entry = Entry::first();
    expect($entry)->not->toBeNull();
    expect($entry->repo)->toBe('custom/repo');
    expect($entry->branch)->toBe('custom-branch');
    expect($entry->commit)->toBe('abc123');
});

it('validates required content field', function () {
    $this->artisan('add', [
        'title' => 'No Content Entry',
    ])->assertFailed();

    expect(Entry::count())->toBe(0);
});

it('validates confidence range', function () {
    $this->artisan('add', [
        'title' => 'Invalid Confidence',
        '--content' => 'Test',
        '--confidence' => 150,
    ])->assertFailed();

    expect(Entry::count())->toBe(0);
});

it('creates entry with tags', function () {
    $this->artisan('add', [
        'title' => 'Tagged Entry',
        '--content' => 'Content',
        '--tags' => 'php,laravel,testing',
    ])->assertSuccessful();

    $entry = Entry::first();
    expect($entry->tags)->toBe(['php', 'laravel', 'testing']);
});
