<?php

declare(strict_types=1);

use App\Models\Entry;

test('displays total entries count', function () {
    Entry::factory()->count(5)->create();

    $this->artisan('knowledge:stats')
        ->expectsOutputToContain('Total Entries: 5')
        ->assertSuccessful();
});

test('displays entries by status', function () {
    Entry::factory()->count(3)->create(['status' => 'draft']);
    Entry::factory()->count(2)->create(['status' => 'validated']);
    Entry::factory()->count(1)->create(['status' => 'deprecated']);

    $this->artisan('knowledge:stats')
        ->expectsOutputToContain('draft: 3')
        ->expectsOutputToContain('validated: 2')
        ->expectsOutputToContain('deprecated: 1')
        ->assertSuccessful();
});

test('displays entries by category', function () {
    Entry::factory()->count(2)->create(['category' => 'debugging']);
    Entry::factory()->count(3)->create(['category' => 'architecture']);
    Entry::factory()->count(1)->create(['category' => null]);

    $this->artisan('knowledge:stats')
        ->expectsOutputToContain('debugging: 2')
        ->expectsOutputToContain('architecture: 3')
        ->assertSuccessful();
});

test('displays usage statistics', function () {
    Entry::factory()->create([
        'usage_count' => 10,
        'last_used' => now()->subDays(5),
    ]);

    Entry::factory()->create([
        'usage_count' => 5,
        'last_used' => now()->subDays(2),
    ]);

    Entry::factory()->create([
        'usage_count' => 0,
        'last_used' => null,
    ]);

    $this->artisan('knowledge:stats')
        ->expectsOutputToContain('Total Usage: 15')
        ->expectsOutputToContain('Average Usage: 5')
        ->assertSuccessful();
});

test('displays stale entries count', function () {
    Entry::factory()->count(2)->create([
        'last_used' => now()->subDays(91),
    ]);

    Entry::factory()->create([
        'last_used' => now()->subDays(50),
    ]);

    $this->artisan('knowledge:stats')
        ->expectsOutputToContain('Stale Entries (90+ days): 2')
        ->assertSuccessful();
});

test('handles empty database gracefully', function () {
    $this->artisan('knowledge:stats')
        ->expectsOutputToContain('Total Entries: 0')
        ->assertSuccessful();
});
