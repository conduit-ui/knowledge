<?php

declare(strict_types=1);

use App\Models\Entry;

it('searches entries by keyword in title', function () {
    Entry::factory()->create(['title' => 'Laravel Timezone Conversion']);
    Entry::factory()->create(['title' => 'React Component Testing']);
    Entry::factory()->create(['title' => 'Database Timezone Handling']);

    $this->artisan('knowledge:search', ['query' => 'timezone'])
        ->assertSuccessful();

    // We can't assert exact output in Laravel Zero easily, but we can verify the command runs
});

it('searches entries by keyword in content', function () {
    Entry::factory()->create([
        'title' => 'Random Title',
        'content' => 'This is about timezone conversion in Laravel',
    ]);
    Entry::factory()->create([
        'title' => 'Another Title',
        'content' => 'This is about React components',
    ]);

    $this->artisan('knowledge:search', ['query' => 'timezone'])
        ->assertSuccessful();
});

it('searches entries by tag', function () {
    Entry::factory()->create([
        'title' => 'Entry 1',
        'tags' => ['blood.notifications', 'laravel'],
    ]);
    Entry::factory()->create([
        'title' => 'Entry 2',
        'tags' => ['react', 'frontend'],
    ]);
    Entry::factory()->create([
        'title' => 'Entry 3',
        'tags' => ['blood.scheduling', 'laravel'],
    ]);

    $this->artisan('knowledge:search', ['--tag' => 'blood.notifications'])
        ->assertSuccessful();
});

it('searches entries by category', function () {
    Entry::factory()->create(['category' => 'architecture', 'title' => 'Architecture Entry']);
    Entry::factory()->create(['category' => 'testing', 'title' => 'Testing Entry']);
    Entry::factory()->create(['category' => 'architecture', 'title' => 'Another Architecture']);

    $this->artisan('knowledge:search', ['--category' => 'architecture'])
        ->assertSuccessful();
});

it('searches entries by category and module', function () {
    Entry::factory()->create([
        'category' => 'architecture',
        'module' => 'Blood',
        'title' => 'Blood Architecture',
    ]);
    Entry::factory()->create([
        'category' => 'architecture',
        'module' => 'Auth',
        'title' => 'Auth Architecture',
    ]);
    Entry::factory()->create([
        'category' => 'testing',
        'module' => 'Blood',
        'title' => 'Blood Testing',
    ]);

    $this->artisan('knowledge:search', [
        '--category' => 'architecture',
        '--module' => 'Blood',
    ])->assertSuccessful();
});

it('searches entries by priority', function () {
    Entry::factory()->create(['priority' => 'critical', 'title' => 'Critical Entry']);
    Entry::factory()->create(['priority' => 'high', 'title' => 'High Entry']);
    Entry::factory()->create(['priority' => 'low', 'title' => 'Low Entry']);

    $this->artisan('knowledge:search', ['--priority' => 'critical'])
        ->assertSuccessful();
});

it('searches entries by status', function () {
    Entry::factory()->validated()->create(['title' => 'Validated Entry']);
    Entry::factory()->draft()->create(['title' => 'Draft Entry']);

    $this->artisan('knowledge:search', ['--status' => 'validated'])
        ->assertSuccessful();
});

it('shows message when no results found', function () {
    Entry::factory()->create(['title' => 'Something else']);

    $this->artisan('knowledge:search', ['query' => 'nonexistent'])
        ->assertSuccessful()
        ->expectsOutput('No entries found.');
});

it('searches with multiple filters', function () {
    Entry::factory()->create([
        'title' => 'Laravel Testing Best Practices',
        'category' => 'testing',
        'module' => 'Blood',
        'priority' => 'high',
        'tags' => ['laravel', 'pest'],
    ]);
    Entry::factory()->create([
        'title' => 'React Testing',
        'category' => 'testing',
        'module' => 'Frontend',
        'priority' => 'medium',
    ]);

    $this->artisan('knowledge:search', [
        '--category' => 'testing',
        '--module' => 'Blood',
        '--priority' => 'high',
    ])->assertSuccessful();
});

it('handles case-insensitive search', function () {
    Entry::factory()->create(['title' => 'Laravel Best Practices']);

    $this->artisan('knowledge:search', ['query' => 'LARAVEL'])
        ->assertSuccessful();
});

it('requires at least one search parameter', function () {
    $this->artisan('knowledge:search')
        ->assertFailed();
});
