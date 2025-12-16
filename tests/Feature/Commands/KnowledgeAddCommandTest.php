<?php

declare(strict_types=1);

use App\Models\Entry;

it('adds a knowledge entry with all options', function () {
    $this->artisan('knowledge:add', [
        'title' => 'Test Entry Title',
        '--content' => 'This is the detailed explanation',
        '--category' => 'architecture',
        '--tags' => 'module.submodule,patterns',
        '--confidence' => 85,
        '--priority' => 'high',
    ])->assertSuccessful();

    expect(Entry::count())->toBe(1);

    $entry = Entry::first();
    expect($entry->title)->toBe('Test Entry Title');
    expect($entry->content)->toBe('This is the detailed explanation');
    expect($entry->category)->toBe('architecture');
    expect($entry->tags)->toBe(['module.submodule', 'patterns']);
    expect($entry->confidence)->toBe(85);
    expect($entry->priority)->toBe('high');
    expect($entry->status)->toBe('draft');
});

it('adds a knowledge entry with minimal options', function () {
    $this->artisan('knowledge:add', [
        'title' => 'Minimal Entry',
        '--content' => 'Content here',
    ])->assertSuccessful();

    expect(Entry::count())->toBe(1);

    $entry = Entry::first();
    expect($entry->title)->toBe('Minimal Entry');
    expect($entry->content)->toBe('Content here');
    expect($entry->priority)->toBe('medium');
    expect($entry->confidence)->toBe(50);
});

it('validates confidence must be between 0 and 100', function () {
    $this->artisan('knowledge:add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--confidence' => 150,
    ])->assertFailed();

    expect(Entry::count())->toBe(0);
});

it('validates confidence cannot be negative', function () {
    $this->artisan('knowledge:add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--confidence' => -10,
    ])->assertFailed();

    expect(Entry::count())->toBe(0);
});

it('validates priority must be valid enum value', function () {
    $this->artisan('knowledge:add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--priority' => 'invalid',
    ])->assertFailed();

    expect(Entry::count())->toBe(0);
});

it('validates category must be valid enum value', function () {
    $this->artisan('knowledge:add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--category' => 'invalid-category',
    ])->assertFailed();

    expect(Entry::count())->toBe(0);
});

it('validates status must be valid enum value', function () {
    $this->artisan('knowledge:add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--status' => 'invalid-status',
    ])->assertFailed();

    expect(Entry::count())->toBe(0);
});

it('requires title argument', function () {
    expect(function () {
        $this->artisan('knowledge:add');
    })->toThrow(\RuntimeException::class, 'Not enough arguments');

    expect(Entry::count())->toBe(0);
});

it('requires content option', function () {
    $this->artisan('knowledge:add', [
        'title' => 'Test Entry',
    ])->assertFailed();

    expect(Entry::count())->toBe(0);
});

it('accepts comma-separated tags', function () {
    $this->artisan('knowledge:add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--tags' => 'tag1,tag2,tag3',
    ])->assertSuccessful();

    $entry = Entry::first();
    expect($entry->tags)->toBe(['tag1', 'tag2', 'tag3']);
});

it('accepts single tag', function () {
    $this->artisan('knowledge:add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--tags' => 'single-tag',
    ])->assertSuccessful();

    $entry = Entry::first();
    expect($entry->tags)->toBe(['single-tag']);
});

it('accepts module option', function () {
    $this->artisan('knowledge:add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--module' => 'Blood',
    ])->assertSuccessful();

    $entry = Entry::first();
    expect($entry->module)->toBe('Blood');
});

it('accepts source, ticket, and author options', function () {
    $this->artisan('knowledge:add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--source' => 'https://example.com',
        '--ticket' => 'JIRA-123',
        '--author' => 'John Doe',
    ])->assertSuccessful();

    $entry = Entry::first();
    expect($entry->source)->toBe('https://example.com');
    expect($entry->ticket)->toBe('JIRA-123');
    expect($entry->author)->toBe('John Doe');
});

it('accepts status option', function () {
    $this->artisan('knowledge:add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--status' => 'validated',
    ])->assertSuccessful();

    $entry = Entry::first();
    expect($entry->status)->toBe('validated');
});
