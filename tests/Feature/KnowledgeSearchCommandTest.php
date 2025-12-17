<?php

declare(strict_types=1);

use App\Models\Entry;

describe('KnowledgeSearchCommand', function () {
    beforeEach(function () {
        Entry::factory()->create([
            'title' => 'Laravel Testing',
            'content' => 'How to test Laravel applications',
            'tags' => ['laravel', 'testing'],
            'category' => 'tutorial',
            'confidence' => 95,
        ]);

        Entry::factory()->create([
            'title' => 'PHP Standards',
            'content' => 'PHP coding standards and PSR guidelines',
            'tags' => ['php', 'standards'],
            'category' => 'guide',
            'confidence' => 90,
        ]);
    });

    it('requires at least one parameter', function () {
        $this->artisan('search')
            ->expectsOutput('Please provide at least one search parameter.')
            ->assertFailed();
    });

    it('finds entries by keyword', function () {
        $this->artisan('search', ['query' => 'Laravel'])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 1 entry')
            ->expectsOutputToContain('Laravel Testing');
    });

    it('filters by tag', function () {
        $this->artisan('search', ['--tag' => 'php'])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 1 entry')
            ->expectsOutputToContain('PHP Standards');
    });

    it('filters by category', function () {
        $this->artisan('search', ['--category' => 'tutorial'])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 1 entry')
            ->expectsOutputToContain('Laravel Testing');
    });

    it('shows no results message', function () {
        $this->artisan('search', ['query' => 'nonexistent'])
            ->assertSuccessful()
            ->expectsOutput('No entries found.');
    });

    it('supports semantic flag', function () {
        $this->artisan('search', [
            'query' => 'Laravel',
            '--semantic' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 1 entry')
            ->expectsOutputToContain('Laravel Testing');
    });

    it('combines query and filters', function () {
        $this->artisan('search', [
            'query' => 'Laravel',
            '--category' => 'tutorial',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 1 entry')
            ->expectsOutputToContain('Laravel Testing');
    });

    it('shows entry details', function () {
        $this->artisan('search', ['query' => 'Laravel'])
            ->assertSuccessful()
            ->expectsOutputToContain('Laravel Testing')
            ->expectsOutputToContain('Category: tutorial');
    });

    it('truncates long content', function () {
        Entry::factory()->create([
            'title' => 'Long Content',
            'content' => str_repeat('a', 150),
            'confidence' => 100,
        ]);

        $this->artisan('search', ['query' => 'Long'])
            ->assertSuccessful()
            ->expectsOutputToContain('...');
    });
});
