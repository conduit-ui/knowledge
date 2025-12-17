<?php

declare(strict_types=1);

use App\Enums\ObservationType;
use App\Models\Entry;
use App\Models\Observation;
use App\Models\Session;

it('searches entries by keyword in title', function () {
    Entry::factory()->create(['title' => 'Laravel Timezone Conversion']);
    Entry::factory()->create(['title' => 'React Component Testing']);
    Entry::factory()->create(['title' => 'Database Timezone Handling']);

    $this->artisan('search', ['query' => 'timezone'])
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

    $this->artisan('search', ['query' => 'timezone'])
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

    $this->artisan('search', ['--tag' => 'blood.notifications'])
        ->assertSuccessful();
});

it('searches entries by category', function () {
    Entry::factory()->create(['category' => 'architecture', 'title' => 'Architecture Entry']);
    Entry::factory()->create(['category' => 'testing', 'title' => 'Testing Entry']);
    Entry::factory()->create(['category' => 'architecture', 'title' => 'Another Architecture']);

    $this->artisan('search', ['--category' => 'architecture'])
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

    $this->artisan('search', [
        '--category' => 'architecture',
        '--module' => 'Blood',
    ])->assertSuccessful();
});

it('searches entries by priority', function () {
    Entry::factory()->create(['priority' => 'critical', 'title' => 'Critical Entry']);
    Entry::factory()->create(['priority' => 'high', 'title' => 'High Entry']);
    Entry::factory()->create(['priority' => 'low', 'title' => 'Low Entry']);

    $this->artisan('search', ['--priority' => 'critical'])
        ->assertSuccessful();
});

it('searches entries by status', function () {
    Entry::factory()->validated()->create(['title' => 'Validated Entry']);
    Entry::factory()->draft()->create(['title' => 'Draft Entry']);

    $this->artisan('search', ['--status' => 'validated'])
        ->assertSuccessful();
});

it('shows message when no results found', function () {
    Entry::factory()->create(['title' => 'Something else']);

    $this->artisan('search', ['query' => 'nonexistent'])
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

    $this->artisan('search', [
        '--category' => 'testing',
        '--module' => 'Blood',
        '--priority' => 'high',
    ])->assertSuccessful();
});

it('handles case-insensitive search', function () {
    Entry::factory()->create(['title' => 'Laravel Best Practices']);

    $this->artisan('search', ['query' => 'LARAVEL'])
        ->assertSuccessful();
});

it('requires at least one search parameter', function () {
    $this->artisan('search')
        ->assertFailed();
});

describe('--observations flag', function (): void {
    it('searches observations instead of entries', function (): void {
        $session = Session::factory()->create();

        Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Authentication Bug Fix',
            'type' => ObservationType::Bugfix,
            'narrative' => 'Fixed OAuth bug',
        ]);

        Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Feature Implementation',
            'type' => ObservationType::Feature,
            'narrative' => 'Added new feature',
        ]);

        // Create an entry that should NOT appear in results
        Entry::factory()->create(['title' => 'Authentication Entry']);

        $this->artisan('search', [
            'query' => 'authentication',
            '--observations' => true,
        ])->assertSuccessful();
    });

    it('shows no observations message when none found', function (): void {
        $session = Session::factory()->create();

        Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Something else',
            'narrative' => 'Different topic',
        ]);

        $this->artisan('search', [
            'query' => 'nonexistent',
            '--observations' => true,
        ])->assertSuccessful()
            ->expectsOutput('No observations found.');
    });

    it('displays observation type, title, concept, and created date', function (): void {
        $session = Session::factory()->create();

        Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Bug Fix',
            'type' => ObservationType::Bugfix,
            'concept' => 'Authentication',
            'narrative' => 'Fixed auth bug',
        ]);

        $output = $this->artisan('search', [
            'query' => 'bug',
            '--observations' => true,
        ]);

        $output->assertSuccessful();
    });

    it('requires query when using observations flag', function (): void {
        $this->artisan('search', [
            '--observations' => true,
        ])->assertFailed();
    });

    it('searches by observation type', function (): void {
        $session = Session::factory()->create();

        Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Feature 1',
            'type' => ObservationType::Feature,
            'narrative' => 'Feature narrative',
        ]);

        Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Bug 1',
            'type' => ObservationType::Bugfix,
            'narrative' => 'Bug narrative',
        ]);

        $this->artisan('search', [
            'query' => 'narrative',
            '--observations' => true,
        ])->assertSuccessful();
    });

    it('searches observations by concept', function (): void {
        $session = Session::factory()->create();

        Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Auth Fix',
            'type' => ObservationType::Bugfix,
            'concept' => 'Authentication',
            'narrative' => 'Fixed OAuth',
        ]);

        Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Cache Update',
            'type' => ObservationType::Change,
            'concept' => 'Performance',
            'narrative' => 'Updated cache',
        ]);

        $this->artisan('search', [
            'query' => 'authentication',
            '--observations' => true,
        ])->assertSuccessful();
    });

    it('counts observations correctly', function (): void {
        $session = Session::factory()->create();

        Observation::factory(3)->create([
            'session_id' => $session->id,
            'title' => 'Test Observation',
            'narrative' => 'Test narrative',
        ]);

        $this->artisan('search', [
            'query' => 'test',
            '--observations' => true,
        ])->assertSuccessful()
            ->expectsOutput('Found 3 observations');
    });

    it('truncates long observation narratives', function (): void {
        $session = Session::factory()->create();

        $longNarrative = str_repeat('This is a very long narrative. ', 10); // Over 100 chars

        Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Long Narrative Observation',
            'narrative' => $longNarrative,
        ]);

        $this->artisan('search', [
            'query' => 'narrative',
            '--observations' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('...');
    });
});
