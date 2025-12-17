<?php

declare(strict_types=1);

use App\Models\Entry;

describe('KnowledgeDuplicatesCommand', function (): void {
    describe('finding duplicates', function (): void {
        it('finds duplicate entries with similar content', function (): void {
            Entry::factory()->create([
                'title' => 'How to configure Laravel authentication',
                'content' => 'This guide explains how to configure Laravel authentication using the built-in auth scaffolding.',
            ]);

            Entry::factory()->create([
                'title' => 'Configure Laravel authentication guide',
                'content' => 'This guide explains how to configure Laravel authentication using the built-in auth features.',
            ]);

            $this->artisan('knowledge:duplicates')
                ->expectsOutputToContain('Found 1 potential duplicate group')
                ->expectsOutputToContain('Similarity:')
                ->assertSuccessful();
        });

        it('shows no duplicates when entries are different', function (): void {
            Entry::factory()->create([
                'title' => 'Laravel Authentication',
                'content' => 'How to set up Laravel authentication.',
            ]);

            Entry::factory()->create([
                'title' => 'Docker Compose Setup',
                'content' => 'How to configure Docker Compose for development.',
            ]);

            $this->artisan('knowledge:duplicates')
                ->expectsOutputToContain('No potential duplicates found')
                ->assertSuccessful();
        });

        it('shows message when not enough entries', function (): void {
            Entry::factory()->create();

            $this->artisan('knowledge:duplicates')
                ->expectsOutputToContain('Not enough entries to compare')
                ->assertSuccessful();
        });

        it('shows message when no entries', function (): void {
            $this->artisan('knowledge:duplicates')
                ->expectsOutputToContain('Not enough entries to compare')
                ->assertSuccessful();
        });

        it('handles entries with only stop words', function (): void {
            // Create entries with only stop words (filtered out by tokenizer)
            Entry::factory()->create(['title' => 'a an the', 'content' => 'in on at to for of']);
            Entry::factory()->create(['title' => 'the a an', 'content' => 'of for to at on in']);

            // These should have 0% similarity (no tokenizable words)
            $this->artisan('knowledge:duplicates')
                ->expectsOutputToContain('No potential duplicates found')
                ->assertSuccessful();
        });
    });

    describe('threshold option', function (): void {
        it('respects threshold option', function (): void {
            Entry::factory()->create([
                'title' => 'Laravel Authentication',
                'content' => 'Setting up authentication in Laravel applications.',
            ]);

            Entry::factory()->create([
                'title' => 'Laravel Auth Setup',
                'content' => 'How to configure Laravel auth.',
            ]);

            // High threshold should find no matches
            $this->artisan('knowledge:duplicates', ['--threshold' => 95])
                ->expectsOutputToContain('No potential duplicates found')
                ->assertSuccessful();
        });

        it('fails with invalid threshold', function (): void {
            $this->artisan('knowledge:duplicates', ['--threshold' => 150])
                ->expectsOutputToContain('Threshold must be between 0 and 100')
                ->assertFailed();
        });

        it('fails with negative threshold', function (): void {
            $this->artisan('knowledge:duplicates', ['--threshold' => -10])
                ->expectsOutputToContain('Threshold must be between 0 and 100')
                ->assertFailed();
        });
    });

    describe('limit option', function (): void {
        it('limits output when there are many groups', function (): void {
            // Create identical pairs that will definitely match
            Entry::factory()->create(['title' => 'AAA', 'content' => 'AAA AAA AAA']);
            Entry::factory()->create(['title' => 'AAA', 'content' => 'AAA AAA AAA']);
            Entry::factory()->create(['title' => 'BBB', 'content' => 'BBB BBB BBB']);
            Entry::factory()->create(['title' => 'BBB', 'content' => 'BBB BBB BBB']);
            Entry::factory()->create(['title' => 'CCC', 'content' => 'CCC CCC CCC']);
            Entry::factory()->create(['title' => 'CCC', 'content' => 'CCC CCC CCC']);

            // With limit 1, we should see indication of more groups
            $this->artisan('knowledge:duplicates', ['--limit' => 1])
                ->expectsOutputToContain('Similarity:')
                ->assertSuccessful();
        });
    });

    describe('output', function (): void {
        it('shows merge suggestion', function (): void {
            $content = 'This is identical content that appears in both entries for testing purposes.';
            Entry::factory()->create(['title' => 'Entry A', 'content' => $content]);
            Entry::factory()->create(['title' => 'Entry A', 'content' => $content]);

            $this->artisan('knowledge:duplicates')
                ->expectsOutputToContain('knowledge:merge')
                ->assertSuccessful();
        });
    });

    describe('command signature', function (): void {
        it('has the correct signature', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeDuplicatesCommand::class);
            expect($command->getName())->toBe('knowledge:duplicates');
        });

        it('has threshold option with default', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeDuplicatesCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasOption('threshold'))->toBeTrue();
            expect($definition->getOption('threshold')->getDefault())->toBe('70');
        });

        it('has limit option with default', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeDuplicatesCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasOption('limit'))->toBeTrue();
            expect($definition->getOption('limit')->getDefault())->toBe('10');
        });
    });
});
