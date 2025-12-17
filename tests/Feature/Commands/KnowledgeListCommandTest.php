<?php

declare(strict_types=1);

use App\Models\Entry;

it('lists all entries', function () {
    Entry::factory()->count(3)->create();

    $this->artisan('entries')
        ->assertSuccessful();
});

it('filters by category', function () {
    Entry::factory()->create(['category' => 'architecture', 'title' => 'Architecture Entry']);
    Entry::factory()->create(['category' => 'testing', 'title' => 'Testing Entry']);
    Entry::factory()->create(['category' => 'architecture', 'title' => 'Another Architecture']);

    $this->artisan('entries', ['--category' => 'architecture'])
        ->assertSuccessful();
});

it('filters by priority', function () {
    Entry::factory()->create(['priority' => 'critical']);
    Entry::factory()->create(['priority' => 'high']);
    Entry::factory()->create(['priority' => 'low']);

    $this->artisan('entries', ['--priority' => 'critical'])
        ->assertSuccessful();
});

it('filters by status', function () {
    Entry::factory()->validated()->create();
    Entry::factory()->draft()->create();
    Entry::factory()->draft()->create();

    $this->artisan('entries', ['--status' => 'validated'])
        ->assertSuccessful();
});

it('filters by module', function () {
    Entry::factory()->create(['module' => 'Blood']);
    Entry::factory()->create(['module' => 'Auth']);
    Entry::factory()->create(['module' => 'Blood']);

    $this->artisan('entries', ['--module' => 'Blood'])
        ->assertSuccessful();
});

it('limits results', function () {
    Entry::factory()->count(20)->create();

    $this->artisan('entries', ['--limit' => 5])
        ->assertSuccessful();
});

it('shows default limit of 20', function () {
    Entry::factory()->count(30)->create();

    $this->artisan('entries')
        ->assertSuccessful();
});

it('combines multiple filters', function () {
    Entry::factory()->create([
        'category' => 'architecture',
        'priority' => 'high',
        'status' => 'validated',
    ]);
    Entry::factory()->create([
        'category' => 'architecture',
        'priority' => 'low',
        'status' => 'validated',
    ]);
    Entry::factory()->create([
        'category' => 'testing',
        'priority' => 'high',
        'status' => 'validated',
    ]);

    $this->artisan('entries', [
        '--category' => 'architecture',
        '--priority' => 'high',
    ])->assertSuccessful();
});

it('shows message when no entries exist', function () {
    $this->artisan('entries')
        ->assertSuccessful()
        ->expectsOutput('No entries found.');
});

it('orders by confidence and usage count', function () {
    Entry::factory()->create([
        'title' => 'Low confidence',
        'confidence' => 30,
        'usage_count' => 1,
    ]);
    Entry::factory()->create([
        'title' => 'High confidence',
        'confidence' => 90,
        'usage_count' => 5,
    ]);
    Entry::factory()->create([
        'title' => 'Medium confidence',
        'confidence' => 60,
        'usage_count' => 3,
    ]);

    $this->artisan('entries')
        ->assertSuccessful();
});

it('shows entry count', function () {
    Entry::factory()->count(5)->create();

    $this->artisan('entries')
        ->assertSuccessful()
        ->expectsOutputToContain('5 entries');
});

it('accepts min-confidence filter', function () {
    Entry::factory()->create(['confidence' => 90]);
    Entry::factory()->create(['confidence' => 50]);
    Entry::factory()->create(['confidence' => 80]);

    $this->artisan('entries', ['--min-confidence' => 75])
        ->assertSuccessful();
});

it('shows pagination info when results are limited', function () {
    Entry::factory()->count(25)->create();

    $this->artisan('entries', ['--limit' => 10])
        ->assertSuccessful()
        ->expectsOutputToContain('Showing 10 of 25');
});
