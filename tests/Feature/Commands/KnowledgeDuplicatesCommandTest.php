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
        it('limits output', function (): void {
            for ($i = 0; $i < 3; $i++) {
                Entry::factory()->create(['title' => "Group $i Entry 1", 'content' => "Content for group $i"]);
                Entry::factory()->create(['title' => "Group $i Entry 1", 'content' => "Content for group $i"]);
            }

            $this->artisan('duplicates', ['--threshold' => 70, '--limit' => 2])
                ->expectsOutputToContain('Found 3 potential duplicate groups')
                ->expectsOutputToContain('... and 1 more group')
                ->assertExitCode(0);
        });

        it('displays similarity percentage', function (): void {
            Entry::factory()->create(['title' => 'Test Entry', 'content' => 'Same content']);
            Entry::factory()->create(['title' => 'Test Entry', 'content' => 'Same content']);

            $this->artisan('duplicates', ['--threshold' => 70])
                ->expectsOutputToContain('Similarity:')
                ->assertExitCode(0);
        });

        it('displays entry details', function (): void {
            $entry1 = Entry::factory()->create([
                'title' => 'Test Entry',
                'content' => 'Same content',
                'status' => 'validated',
                'confidence' => 85,
            ]);
            $entry2 = Entry::factory()->create([
                'title' => 'Test Entry',
                'content' => 'Same content',
                'status' => 'validated',
                'confidence' => 90,
            ]);

            $this->artisan('duplicates', ['--threshold' => 70])
                ->expectsOutputToContain("#{$entry1->id} Test Entry")
                ->expectsOutputToContain("#{$entry2->id} Test Entry")
                ->expectsOutputToContain('Status: validated')
                ->expectsOutputToContain('Confidence: 85%')
                ->expectsOutputToContain('Confidence: 90%')
                ->assertExitCode(0);
        });
    });
});
