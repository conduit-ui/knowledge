<?php

declare(strict_types=1);

use App\Models\Entry;

beforeEach(function () {
    Entry::query()->delete();
});

describe('KnowledgeGitAuthorCommand', function (): void {
    describe('displaying entries by author', function (): void {
        it('displays entries by a specific author', function () {
            Entry::factory()->create([
                'title' => 'Entry 1',
                'author' => 'John Doe',
            ]);

            Entry::factory()->create([
                'title' => 'Entry 2',
                'author' => 'John Doe',
            ]);

            Entry::factory()->create([
                'title' => 'Entry 3',
                'author' => 'Jane Smith',
            ]);

            $this->artisan('git:author', ['name' => 'John Doe'])
                ->expectsOutputToContain('Entries by author: John Doe')
                ->expectsOutputToContain('Entry 1')
                ->expectsOutputToContain('Entry 2')
                ->expectsOutputToContain('Total entries: 2')
                ->assertSuccessful();
        });

        it('displays multiple entries with full details', function () {
            Entry::factory()->create([
                'title' => 'First Entry',
                'author' => 'John Doe',
                'category' => 'architecture',
                'priority' => 'high',
                'branch' => 'feature/test',
                'commit' => 'abc123',
            ]);

            Entry::factory()->create([
                'title' => 'Second Entry',
                'author' => 'John Doe',
                'category' => 'performance',
                'priority' => 'medium',
                'branch' => 'main',
                'commit' => 'def456',
            ]);

            $this->artisan('git:author', ['name' => 'John Doe'])
                ->expectsOutputToContain('Entries by author: John Doe')
                ->expectsOutputToContain('First Entry')
                ->expectsOutputToContain('architecture')
                ->expectsOutputToContain('high')
                ->expectsOutputToContain('feature/test')
                ->expectsOutputToContain('abc123')
                ->expectsOutputToContain('Second Entry')
                ->expectsOutputToContain('performance')
                ->expectsOutputToContain('medium')
                ->expectsOutputToContain('main')
                ->expectsOutputToContain('def456')
                ->expectsOutputToContain('Total entries: 2')
                ->assertSuccessful();
        });

        it('displays entry details for author', function () {
            Entry::factory()->create([
                'title' => 'Test Entry',
                'author' => 'John Doe',
                'content' => 'Test content',
                'category' => 'testing',
                'commit' => 'abc123',
            ]);

            $this->artisan('git:author', ['name' => 'John Doe'])
                ->expectsOutputToContain('Test Entry')
                ->expectsOutputToContain('testing')
                ->assertSuccessful();
        });

        it('handles entries with null category and branch', function () {
            Entry::factory()->create([
                'title' => 'Entry Without Category',
                'author' => 'John Doe',
                'category' => null,
                'branch' => null,
                'commit' => null,
            ]);

            $this->artisan('git:author', ['name' => 'John Doe'])
                ->expectsOutputToContain('Entry Without Category')
                ->expectsOutputToContain('Category: N/A')
                ->expectsOutputToContain('Branch: N/A')
                ->expectsOutputToContain('Commit: N/A')
                ->assertSuccessful();
        });

        it('counts total entries correctly for author', function () {
            Entry::factory()->count(5)->create(['author' => 'Prolific Author']);
            Entry::factory()->count(3)->create(['author' => 'Other Author']);

            $this->artisan('git:author', ['name' => 'Prolific Author'])
                ->expectsOutputToContain('Total entries: 5')
                ->assertSuccessful();
        });
    });

    describe('handling edge cases', function (): void {
        it('shows message when no entries found for author', function () {
            $this->artisan('git:author', ['name' => 'Unknown Author'])
                ->expectsOutputToContain('No entries found for author: Unknown Author')
                ->assertSuccessful();
        });

        it('handles author names with special characters', function () {
            Entry::factory()->create([
                'title' => 'Special Entry',
                'author' => "O'Brien-Smith",
            ]);

            $this->artisan('git:author', ['name' => "O'Brien-Smith"])
                ->expectsOutputToContain('Special Entry')
                ->expectsOutputToContain("Entries by author: O'Brien-Smith")
                ->assertSuccessful();
        });

        it('handles author names with spaces', function () {
            Entry::factory()->create([
                'title' => 'Space Entry',
                'author' => 'John Smith Doe',
            ]);

            $this->artisan('git:author', ['name' => 'John Smith Doe'])
                ->expectsOutputToContain('Space Entry')
                ->assertSuccessful();
        });

        it('is case-sensitive for author names', function () {
            Entry::factory()->create([
                'title' => 'Entry 1',
                'author' => 'John Doe',
            ]);

            $this->artisan('git:author', ['name' => 'john doe'])
                ->expectsOutputToContain('No entries found for author: john doe')
                ->assertSuccessful();
        });
    });

    describe('command signature', function (): void {
        it('has the correct signature', function () {
            $command = $this->app->make(\App\Commands\KnowledgeGitAuthorCommand::class);
            expect($command->getName())->toBe('git:author');
        });

        it('has the correct description', function () {
            $command = $this->app->make(\App\Commands\KnowledgeGitAuthorCommand::class);
            expect($command->getDescription())->toContain('author');
        });

        it('requires name argument', function () {
            $command = $this->app->make(\App\Commands\KnowledgeGitAuthorCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasArgument('name'))->toBeTrue();
            expect($definition->getArgument('name')->isRequired())->toBeTrue();
        });
    });
});
