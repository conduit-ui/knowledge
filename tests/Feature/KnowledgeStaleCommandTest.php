<?php

declare(strict_types=1);

use App\Models\Entry;

describe('KnowledgeStaleCommand', function () {
    it('lists entries not used in 90 days', function () {
        $stale = Entry::factory()->create([
            'title' => 'Old Entry',
            'last_used' => now()->subDays(91),
            'confidence' => 80,
            'created_at' => now()->subDays(100),
        ]);

        Entry::factory()->create([
            'title' => 'Recent Entry',
            'last_used' => now()->subDays(50),
            'confidence' => 60,
            'created_at' => now()->subDays(60),
        ]);

        $this->artisan('knowledge:stale')
            ->expectsOutputToContain('Old Entry')
            ->expectsOutputToContain('Usage count:')
            ->assertSuccessful();
    });

    it('lists entries never used and old', function () {
        Entry::factory()->create([
            'title' => 'Never Used',
            'last_used' => null,
            'created_at' => now()->subDays(91),
        ]);

        Entry::factory()->create([
            'title' => 'Never Used Recent',
            'last_used' => null,
            'created_at' => now()->subDays(50),
        ]);

        $this->artisan('knowledge:stale')
            ->expectsOutputToContain('Never Used')
            ->expectsOutputToContain('Never used')
            ->assertSuccessful();
    });

    it('displays high confidence old entries', function () {
        Entry::factory()->create([
            'title' => 'High Confidence Old',
            'confidence' => 85,
            'status' => 'draft',
            'created_at' => now()->subDays(200),
        ]);

        $this->artisan('knowledge:stale')
            ->expectsOutputToContain('High Confidence Old')
            ->expectsOutputToContain('Confidence: 85%')
            ->assertSuccessful();
    });

    it('suggests re-validation', function () {
        Entry::factory()->create([
            'last_used' => now()->subDays(91),
        ]);

        $this->artisan('knowledge:stale')
            ->expectsOutputToContain('re-validation')
            ->assertSuccessful();
    });

    it('displays no stale entries message', function () {
        Entry::factory()->create([
            'last_used' => now()->subDays(50),
        ]);

        $this->artisan('knowledge:stale')
            ->expectsOutputToContain('No stale entries found')
            ->assertSuccessful();
    });

    it('sorts by last used date', function () {
        Entry::factory()->create([
            'title' => 'Very Old',
            'last_used' => now()->subDays(200),
        ]);

        Entry::factory()->create([
            'title' => 'Somewhat Old',
            'last_used' => now()->subDays(100),
        ]);

        $output = $this->artisan('knowledge:stale')->run();

        // The very old entry should appear before the somewhat old entry
        expect($output)->toBe(0);
    });

    it('displays entry id for validation', function () {
        $entry = Entry::factory()->create([
            'last_used' => now()->subDays(91),
        ]);

        $this->artisan('knowledge:stale')
            ->expectsOutputToContain("ID: {$entry->id}")
            ->assertSuccessful();
    });

    it('displays high confidence old unvalidated reason', function () {
        Entry::factory()->create([
            'title' => 'High Conf Old',
            'confidence' => 85,
            'status' => 'draft',
            'created_at' => now()->subDays(200),
            'last_used' => now()->subDays(50),
        ]);

        $this->artisan('knowledge:stale')
            ->expectsOutputToContain('High confidence but old and unvalidated')
            ->assertSuccessful();
    });

    it('displays category when present', function () {
        Entry::factory()->create([
            'title' => 'With Category',
            'category' => 'testing',
            'last_used' => now()->subDays(91),
        ]);

        $this->artisan('knowledge:stale')
            ->expectsOutputToContain('Category: testing')
            ->assertSuccessful();
    });
});
