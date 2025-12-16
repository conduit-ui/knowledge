<?php

declare(strict_types=1);

use App\Models\Entry;

it('shows message when no stale entries exist', function () {
    Entry::factory()->create([
        'last_used' => now(),
        'confidence' => 80,
        'status' => 'validated',
    ]);

    $this->artisan('knowledge:stale')
        ->assertSuccessful()
        ->expectsOutput('No stale entries found. Your knowledge base is up to date!');
});

it('lists stale entries not used in 90 days', function () {
    $staleEntry = Entry::factory()->create([
        'title' => 'Stale Entry',
        'last_used' => now()->subDays(95),
        'confidence' => 80,
        'status' => 'draft',
        'category' => 'architecture',
        'usage_count' => 5,
    ]);

    $this->artisan('knowledge:stale')
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 stale entries needing review')
        ->expectsOutputToContain("ID: {$staleEntry->id}")
        ->expectsOutputToContain('Title: Stale Entry')
        ->expectsOutputToContain('Status: draft')
        ->expectsOutputToContain('Confidence: 80%')
        ->expectsOutputToContain('Category: architecture')
        ->expectsOutputToContain('Last used:')
        ->expectsOutputToContain('Usage count: 5')
        ->expectsOutputToContain('Not used in 90+ days - needs re-validation');
});

it('lists entries never used and created 90+ days ago', function () {
    // Test the display path for entries with null last_used
    $entry = Entry::factory()->create([
        'title' => 'Never Used Entry',
        'last_used' => null,
        'created_at' => now()->subDays(91),
        'confidence' => 70,
        'status' => 'draft',
    ]);

    $this->artisan('knowledge:stale')
        ->assertSuccessful()
        ->expectsOutputToContain("ID: {$entry->id}")
        ->expectsOutputToContain('Never used');
});

it('lists high confidence old unvalidated entries', function () {
    $entry = Entry::factory()->create([
        'title' => 'Old High Confidence',
        'confidence' => 85,
        'status' => 'draft',
        'created_at' => now()->subDays(200),
        'last_used' => now()->subDays(50),
    ]);

    $this->artisan('knowledge:stale')
        ->assertSuccessful()
        ->expectsOutputToContain("ID: {$entry->id}")
        ->expectsOutputToContain('High confidence but old and unvalidated - suggest validation');
});

it('displays entry without category', function () {
    $entry = Entry::factory()->create([
        'title' => 'No Category Entry',
        'category' => null,
        'last_used' => now()->subDays(95),
        'confidence' => 80,
        'status' => 'draft',
    ]);

    $this->artisan('knowledge:stale')
        ->assertSuccessful()
        ->expectsOutputToContain("ID: {$entry->id}")
        ->expectsOutputToContain('Title: No Category Entry');
});

it('tests fallback reason using reflection', function () {
    // Test the fallback path by using reflection to call the private method
    // with an entry that doesn't match any specific pattern
    $command = new \App\Commands\KnowledgeStaleCommand(
        new \App\Services\ConfidenceService
    );

    $entry = Entry::factory()->create([
        'confidence' => 69, // Below 70 threshold
        'status' => 'draft',
        'created_at' => now()->subDays(200),
        'last_used' => now()->subDays(89), // Below 90-day threshold
    ]);

    // Use reflection to access the private determineStaleReason method
    $reflection = new \ReflectionClass($command);
    $method = $reflection->getMethod('determineStaleReason');
    $method->setAccessible(true);

    $reason = $method->invoke($command, $entry);

    expect($reason)->toBe('Needs review');
});

it('shows validation command suggestion', function () {
    Entry::factory()->create([
        'last_used' => now()->subDays(95),
    ]);

    $this->artisan('knowledge:stale')
        ->assertSuccessful()
        ->expectsOutputToContain('Suggestion: Review these entries and run "knowledge:validate <id>" to mark them as current.')
        ->expectsOutputToContain('Consider updating or deprecating entries that are no longer relevant.');
});

it('displays multiple stale entries', function () {
    $entry1 = Entry::factory()->create([
        'title' => 'Stale 1',
        'last_used' => now()->subDays(95),
    ]);

    $entry2 = Entry::factory()->create([
        'title' => 'Stale 2',
        'last_used' => now()->subDays(100),
    ]);

    $this->artisan('knowledge:stale')
        ->assertSuccessful()
        ->expectsOutputToContain('Found 2 stale entries needing review')
        ->expectsOutputToContain("ID: {$entry1->id}")
        ->expectsOutputToContain("ID: {$entry2->id}");
});
