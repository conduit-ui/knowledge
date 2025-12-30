<?php

declare(strict_types=1);

use App\Models\Entry;

beforeEach(function () {
    Entry::query()->delete();
});

describe('KnowledgeGitEntriesCommand', function (): void {
    describe('displaying entries by commit', function (): void {
        it('displays entries for a specific commit', function () {
            Entry::factory()->create([
                'title' => 'Entry 1',
                'commit' => 'abc123',
            ]);

            Entry::factory()->create([
                'title' => 'Entry 2',
                'commit' => 'abc123',
            ]);

            Entry::factory()->create([
                'title' => 'Entry 3',
                'commit' => 'def456',
            ]);

            $this->artisan('git:entries', ['commit' => 'abc123'])
                ->expectsOutputToContain('Entries for commit: abc123')
                ->expectsOutputToContain('Entry 1')
                ->expectsOutputToContain('Entry 2')
                ->expectsOutputToContain('Total entries: 2')
                ->assertSuccessful();
        });

        it('displays multiple entries with full details', function () {
            Entry::factory()->create([
                'title' => 'First Entry',
                'commit' => 'abc123',
                'category' => 'architecture',
                'priority' => 'high',
                'branch' => 'feature/test',
                'author' => 'John Doe',
            ]);

            Entry::factory()->create([
                'title' => 'Second Entry',
                'commit' => 'abc123',
                'category' => 'performance',
                'priority' => 'medium',
                'branch' => 'main',
                'author' => 'Jane Smith',
            ]);

            $this->artisan('git:entries', ['commit' => 'abc123'])
                ->expectsOutputToContain('Entries for commit: abc123')
                ->expectsOutputToContain('First Entry')
                ->expectsOutputToContain('architecture')
                ->expectsOutputToContain('high')
                ->expectsOutputToContain('feature/test')
                ->expectsOutputToContain('John Doe')
                ->expectsOutputToContain('Second Entry')
                ->expectsOutputToContain('performance')
                ->expectsOutputToContain('medium')
                ->expectsOutputToContain('main')
                ->expectsOutputToContain('Jane Smith')
                ->expectsOutputToContain('Total entries: 2')
                ->assertSuccessful();
        });

        it('displays entry details', function () {
            Entry::factory()->create([
                'title' => 'Test Entry',
                'commit' => 'abc123',
                'content' => 'Test content',
                'category' => 'testing',
            ]);

            $this->artisan('git:entries', ['commit' => 'abc123'])
                ->expectsOutputToContain('Test Entry')
                ->expectsOutputToContain('testing')
                ->assertSuccessful();
        });

        it('handles entries with null category and author', function () {
            Entry::factory()->create([
                'title' => 'Entry Without Category',
                'commit' => 'abc123',
                'category' => null,
                'branch' => null,
                'author' => null,
            ]);

            $this->artisan('git:entries', ['commit' => 'abc123'])
                ->expectsOutputToContain('Entry Without Category')
                ->expectsOutputToContain('Category: N/A')
                ->expectsOutputToContain('Branch: N/A')
                ->expectsOutputToContain('Author: N/A')
                ->assertSuccessful();
        });

        it('counts total entries correctly for commit', function () {
            Entry::factory()->count(7)->create(['commit' => 'abc123def456']);
            Entry::factory()->count(3)->create(['commit' => 'other-commit']);

            $this->artisan('git:entries', ['commit' => 'abc123def456'])
                ->expectsOutputToContain('Total entries: 7')
                ->assertSuccessful();
        });
    });

    describe('handling edge cases', function (): void {
        it('shows message when no entries found for commit', function () {
            $this->artisan('git:entries', ['commit' => 'nonexistent'])
                ->expectsOutputToContain('No entries found for commit: nonexistent')
                ->assertSuccessful();
        });

        it('handles full 40-character SHA-1 commit hashes', function () {
            $fullHash = 'abc123def456789012345678901234567890abcd';
            Entry::factory()->create([
                'title' => 'Full Hash Entry',
                'commit' => $fullHash,
            ]);

            $this->artisan('git:entries', ['commit' => $fullHash])
                ->expectsOutputToContain('Full Hash Entry')
                ->expectsOutputToContain("Entries for commit: {$fullHash}")
                ->assertSuccessful();
        });

        it('handles short commit hashes', function () {
            Entry::factory()->create([
                'title' => 'Short Hash Entry',
                'commit' => 'abc123',
            ]);

            $this->artisan('git:entries', ['commit' => 'abc123'])
                ->expectsOutputToContain('Short Hash Entry')
                ->assertSuccessful();
        });

        it('is case-sensitive for commit hashes', function () {
            Entry::factory()->create([
                'title' => 'Entry 1',
                'commit' => 'ABC123',
            ]);

            $this->artisan('git:entries', ['commit' => 'abc123'])
                ->expectsOutputToContain('No entries found for commit: abc123')
                ->assertSuccessful();
        });

        it('handles commits with special characters in hash', function () {
            Entry::factory()->create([
                'title' => 'Special Entry',
                'commit' => 'a1b2c3d4e5f6',
            ]);

            $this->artisan('git:entries', ['commit' => 'a1b2c3d4e5f6'])
                ->expectsOutputToContain('Special Entry')
                ->assertSuccessful();
        });
    });

    describe('command signature', function (): void {
        it('has the correct signature', function () {
            $command = $this->app->make(\App\Commands\KnowledgeGitEntriesCommand::class);
            expect($command->getName())->toBe('git:entries');
        });

        it('has the correct description', function () {
            $command = $this->app->make(\App\Commands\KnowledgeGitEntriesCommand::class);
            expect($command->getDescription())->toContain('commit');
        });

        it('requires commit argument', function () {
            $command = $this->app->make(\App\Commands\KnowledgeGitEntriesCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasArgument('commit'))->toBeTrue();
            expect($definition->getArgument('commit')->isRequired())->toBeTrue();
        });
    });
});
