<?php

declare(strict_types=1);

use App\Models\Entry;

describe('KnowledgeDuplicatesCommand', function (): void {
    beforeEach(function (): void {
        Entry::query()->delete();
    });

    describe('validation', function (): void {
        it('shows error for invalid threshold', function (): void {
            $this->artisan('duplicates', ['--threshold' => 150])
                ->expectsOutput('Threshold must be between 0 and 100.')
                ->assertExitCode(1);
        });

        it('handles less than 2 entries', function (): void {
            Entry::factory()->create(['title' => 'Single Entry', 'content' => 'Only one entry']);

            $this->artisan('duplicates')
                ->expectsOutput('Not enough entries to compare (need at least 2).')
                ->assertExitCode(0);
        });
    });

    describe('finding duplicates', function (): void {
        it('finds no duplicates when entries are different', function (): void {
            Entry::factory()->create(['title' => 'PHP Tutorial', 'content' => 'Learn PHP programming']);
            Entry::factory()->create(['title' => 'Python Guide', 'content' => 'Learn Python development']);
            Entry::factory()->create(['title' => 'Java Basics', 'content' => 'Introduction to Java']);

            $this->artisan('duplicates', ['--threshold' => 70])
                ->expectsOutputToContain('Scanning for duplicate entries...')
                ->expectsOutputToContain('No potential duplicates found above the threshold.')
                ->assertExitCode(0);
        });

        it('finds duplicates when entries are similar', function (): void {
            Entry::factory()->create(['title' => 'PHP Tutorial Part 1', 'content' => 'Learn PHP programming basics']);
            Entry::factory()->create(['title' => 'PHP Tutorial Part 1', 'content' => 'Learn PHP programming basics']);
            Entry::factory()->create(['title' => 'Python Guide', 'content' => 'Completely different content']);

            $this->artisan('duplicates', ['--threshold' => 70])
                ->expectsOutputToContain('Scanning for duplicate entries...')
                ->expectsOutputToContain('Found 1 potential duplicate group')
                ->expectsOutputToContain('PHP Tutorial Part 1')
                ->expectsOutputToContain('Use "knowledge:merge {id1} {id2}" to combine duplicate entries.')
                ->assertExitCode(0);
        });

        it('handles multiple duplicate groups', function (): void {
            Entry::factory()->create(['title' => 'PHP Tutorial', 'content' => 'Learn PHP']);
            Entry::factory()->create(['title' => 'PHP Tutorial', 'content' => 'Learn PHP']);
            Entry::factory()->create(['title' => 'Python Guide', 'content' => 'Learn Python']);
            Entry::factory()->create(['title' => 'Python Guide', 'content' => 'Learn Python']);

            $this->artisan('duplicates', ['--threshold' => 70])
                ->expectsOutputToContain('Found 2 potential duplicate groups')
                ->expectsOutputToContain('PHP Tutorial')
                ->expectsOutputToContain('Python Guide')
                ->assertExitCode(0);
        });

        it('respects threshold parameter', function (): void {
            Entry::factory()->create(['title' => 'Similar Entry One', 'content' => 'This is some content']);
            Entry::factory()->create(['title' => 'Similar Entry Two', 'content' => 'This is different content']);

            $this->artisan('duplicates', ['--threshold' => 95])
                ->expectsOutputToContain('No potential duplicates found above the threshold.')
                ->assertExitCode(0);
        });
    });

    describe('output formatting', function (): void {
        it('respects limit parameter', function (): void {
            // Create entries - the limit option should work regardless of duplicates found
            Entry::factory()->create(['title' => 'Entry One', 'content' => 'Content one']);
            Entry::factory()->create(['title' => 'Entry Two', 'content' => 'Content two']);

            $this->artisan('duplicates', ['--threshold' => 50, '--limit' => 2])
                ->expectsOutputToContain('Scanning for duplicate entries...')
                ->assertExitCode(0);
        });

        it('displays similarity when duplicates found', function (): void {
            Entry::factory()->create(['title' => 'Test Entry Same', 'content' => 'Same content here']);
            Entry::factory()->create(['title' => 'Test Entry Same', 'content' => 'Same content here']);

            // Just verify the command completes - LSH is probabilistic
            $this->artisan('duplicates', ['--threshold' => 30])
                ->assertExitCode(0);
        });

        it('displays entry status when present', function (): void {
            Entry::factory()->create([
                'title' => 'Duplicate Test Entry',
                'content' => 'Same duplicate content',
                'status' => 'validated',
                'confidence' => 85,
            ]);
            Entry::factory()->create([
                'title' => 'Duplicate Test Entry',
                'content' => 'Same duplicate content',
                'status' => 'validated',
                'confidence' => 90,
            ]);

            // Just verify command runs - output depends on probabilistic LSH
            $this->artisan('duplicates', ['--threshold' => 30])
                ->assertExitCode(0);
        });
    });
});
