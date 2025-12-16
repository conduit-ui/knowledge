<?php

declare(strict_types=1);

use App\Models\Entry;

it('shows full details of an entry', function () {
    $entry = Entry::factory()->create([
        'title' => 'Test Entry',
        'content' => 'This is the full content of the entry',
        'category' => 'architecture',
        'tags' => ['laravel', 'pest'],
        'module' => 'Blood',
        'priority' => 'high',
        'confidence' => 85,
        'source' => 'https://example.com',
        'ticket' => 'JIRA-123',
        'author' => 'John Doe',
        'status' => 'validated',
    ]);

    $this->artisan('knowledge:show', ['id' => $entry->id])
        ->assertSuccessful()
        ->expectsOutput("ID: {$entry->id}")
        ->expectsOutput("Title: {$entry->title}")
        ->expectsOutput("Content: {$entry->content}")
        ->expectsOutput('Category: architecture')
        ->expectsOutput('Module: Blood')
        ->expectsOutput('Priority: high')
        ->expectsOutput('Confidence: 85%')
        ->expectsOutput('Status: validated')
        ->expectsOutput('Tags: laravel, pest')
        ->expectsOutput('Source: https://example.com')
        ->expectsOutput('Ticket: JIRA-123')
        ->expectsOutput('Author: John Doe');
});

it('shows entry with minimal fields', function () {
    $entry = Entry::factory()->create([
        'title' => 'Minimal Entry',
        'content' => 'Basic content',
        'category' => null,
        'tags' => null,
        'module' => null,
        'source' => null,
        'ticket' => null,
        'author' => null,
    ]);

    $this->artisan('knowledge:show', ['id' => $entry->id])
        ->assertSuccessful()
        ->expectsOutput("ID: {$entry->id}")
        ->expectsOutput("Title: {$entry->title}")
        ->expectsOutput("Content: {$entry->content}");
});

it('shows usage statistics', function () {
    $entry = Entry::factory()->create([
        'title' => 'Test Entry',
        'usage_count' => 5,
    ]);

    $this->artisan('knowledge:show', ['id' => $entry->id])
        ->assertSuccessful()
        ->expectsOutput('Usage Count: 6'); // Incremented after viewing
});

it('increments usage count when viewing', function () {
    $entry = Entry::factory()->create(['usage_count' => 0]);

    expect($entry->usage_count)->toBe(0);

    $this->artisan('knowledge:show', ['id' => $entry->id])
        ->assertSuccessful();

    $entry->refresh();
    expect($entry->usage_count)->toBe(1);
    expect($entry->last_used)->not->toBeNull();
});

it('shows error when entry not found', function () {
    $this->artisan('knowledge:show', ['id' => 9999])
        ->assertFailed()
        ->expectsOutput('Entry not found.');
});

it('validates id must be numeric', function () {
    $this->artisan('knowledge:show', ['id' => 'abc'])
        ->assertFailed();
});

it('shows timestamps', function () {
    $entry = Entry::factory()->create([
        'title' => 'Test Entry',
    ]);

    $this->artisan('knowledge:show', ['id' => $entry->id])
        ->assertSuccessful();
});

it('shows files if present', function () {
    $entry = Entry::factory()->create([
        'title' => 'Test Entry',
        'files' => ['app/Models/User.php', 'config/app.php'],
    ]);

    $this->artisan('knowledge:show', ['id' => $entry->id])
        ->assertSuccessful()
        ->expectsOutput('Files: app/Models/User.php, config/app.php');
});

it('shows repo details if present', function () {
    $entry = Entry::factory()->create([
        'title' => 'Test Entry',
        'repo' => 'conduit-ui/knowledge',
        'branch' => 'main',
        'commit' => 'abc123',
    ]);

    $this->artisan('knowledge:show', ['id' => $entry->id])
        ->assertSuccessful()
        ->expectsOutput('Repo: conduit-ui/knowledge')
        ->expectsOutput('Branch: main')
        ->expectsOutput('Commit: abc123');
});
