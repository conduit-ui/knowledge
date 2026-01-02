<?php

declare(strict_types=1);

use App\Models\Entry;

beforeEach(function () {
    // Clear database before each test
    Entry::query()->delete();
});

test('duplicates command shows error for invalid threshold', function () {
    $this->artisan('duplicates', ['--threshold' => 150])
        ->expectsOutput('Threshold must be between 0 and 100.')
        ->assertExitCode(1);
});

test('duplicates command handles less than 2 entries', function () {
    Entry::factory()->create(['title' => 'Single Entry', 'content' => 'Only one entry']);

    $this->artisan('duplicates')
        ->expectsOutput('Not enough entries to compare (need at least 2).')
        ->assertExitCode(0);
});

test('duplicates command finds no duplicates when entries are different', function () {
    Entry::factory()->create(['title' => 'PHP Tutorial', 'content' => 'Learn PHP programming']);
    Entry::factory()->create(['title' => 'Python Guide', 'content' => 'Learn Python development']);
    Entry::factory()->create(['title' => 'Java Basics', 'content' => 'Introduction to Java']);

    $this->artisan('duplicates', ['--threshold' => 70])
        ->expectsOutputToContain('Scanning for duplicate entries...')
        ->expectsOutputToContain('No potential duplicates found above the threshold.')
        ->assertExitCode(0);
});

test('duplicates command finds duplicates when entries are similar', function () {
    Entry::factory()->create(['title' => 'PHP Tutorial Part 1', 'content' => 'Learn PHP programming basics']);
    Entry::factory()->create(['title' => 'PHP Tutorial Part 1', 'content' => 'Learn PHP programming basics']);
    Entry::factory()->create(['title' => 'Python Guide', 'content' => 'Completely different content']);

    $this->artisan('duplicates', ['--threshold' => 70])
        ->expectsOutputToContain('Scanning for duplicate entries...')
        ->expectsOutputToContain('Found 1 potential duplicate group')
        ->expectsOutputToContain('PHP Tutorial Part 1')
        ->expectsOutputToContain('Use "knowledge:merge {id1} {id2}" to combine duplicate entries.')
        ->assertExitCode(0);
});

test('duplicates command limits output', function () {
    // Create 3 groups of duplicates
    for ($i = 0; $i < 3; $i++) {
        Entry::factory()->create(['title' => "Group $i Entry 1", 'content' => "Content for group $i"]);
        Entry::factory()->create(['title' => "Group $i Entry 1", 'content' => "Content for group $i"]);
    }

    $this->artisan('duplicates', ['--threshold' => 70, '--limit' => 2])
        ->expectsOutputToContain('Found 3 potential duplicate groups')
        ->expectsOutputToContain('... and 1 more group')
        ->assertExitCode(0);
});

test('duplicates command respects threshold parameter', function () {
    Entry::factory()->create(['title' => 'Similar Entry One', 'content' => 'This is some content']);
    Entry::factory()->create(['title' => 'Similar Entry Two', 'content' => 'This is different content']);

    // High threshold - should find no duplicates
    $this->artisan('duplicates', ['--threshold' => 95])
        ->expectsOutputToContain('No potential duplicates found above the threshold.')
        ->assertExitCode(0);
});

test('duplicates command displays similarity percentage', function () {
    Entry::factory()->create(['title' => 'Test Entry', 'content' => 'Same content']);
    Entry::factory()->create(['title' => 'Test Entry', 'content' => 'Same content']);

    $this->artisan('duplicates', ['--threshold' => 70])
        ->expectsOutputToContain('Similarity:')
        ->assertExitCode(0);
});

test('duplicates command displays entry details', function () {
    $entry1 = Entry::factory()->create([
        'title' => 'Test Entry',
        'content' => 'Same content',
        'status' => 'validated',
        'confidence' => 85,
    ]);
    $entry2 = Entry::factory()->create([
        'title' => 'Test Entry',
        'content' => 'Same content',
        'status' => 'validated',
        'confidence' => 90,
    ]);

    $this->artisan('duplicates', ['--threshold' => 70])
        ->expectsOutputToContain("#{$entry1->id} Test Entry")
        ->expectsOutputToContain("#{$entry2->id} Test Entry")
        ->expectsOutputToContain('Status: validated')
        ->expectsOutputToContain('Confidence: 85%')
        ->expectsOutputToContain('Confidence: 90%')
        ->assertExitCode(0);
});

test('duplicates command handles multiple duplicate groups', function () {
    // Group 1
    Entry::factory()->create(['title' => 'PHP Tutorial', 'content' => 'Learn PHP']);
    Entry::factory()->create(['title' => 'PHP Tutorial', 'content' => 'Learn PHP']);

    // Group 2
    Entry::factory()->create(['title' => 'Python Guide', 'content' => 'Learn Python']);
    Entry::factory()->create(['title' => 'Python Guide', 'content' => 'Learn Python']);

    $this->artisan('duplicates', ['--threshold' => 70])
        ->expectsOutputToContain('Found 2 potential duplicate groups')
        ->expectsOutputToContain('PHP Tutorial')
        ->expectsOutputToContain('Python Guide')
        ->assertExitCode(0);
});
