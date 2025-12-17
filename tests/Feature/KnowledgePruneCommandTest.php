<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;

describe('KnowledgePruneCommand', function (): void {
    describe('finding entries to prune', function (): void {
        it('finds entries older than threshold', function (): void {
            // Create old entries
            Entry::factory()->create([
                'title' => 'Old Entry',
                'created_at' => now()->subYears(2),
            ]);

            // Create recent entry
            Entry::factory()->create([
                'title' => 'Recent Entry',
                'created_at' => now()->subDays(30),
            ]);

            $this->artisan('prune', ['--older-than' => '1y', '--dry-run' => true])
                ->expectsOutputToContain('Found 1 entry')
                ->expectsOutputToContain('Old Entry')
                ->expectsOutputToContain('Dry run')
                ->assertSuccessful();

            // Verify nothing was deleted
            expect(Entry::count())->toBe(2);
        });

        it('shows no entries when none match', function (): void {
            Entry::factory()->create(['created_at' => now()]);

            $this->artisan('prune', ['--older-than' => '1y'])
                ->expectsOutputToContain('No entries found')
                ->assertSuccessful();
        });

        it('shows more indicator when more than 5 entries', function (): void {
            // Create 7 old entries
            Entry::factory()->count(7)->create([
                'created_at' => now()->subYears(2),
            ]);

            $this->artisan('prune', ['--older-than' => '1y', '--dry-run' => true])
                ->expectsOutputToContain('Found 7 entries')
                ->expectsOutputToContain('... and 2 more')
                ->assertSuccessful();
        });
    });

    describe('deprecated-only option', function (): void {
        it('only finds deprecated entries when option is used', function (): void {
            Entry::factory()->create([
                'title' => 'Deprecated Entry',
                'status' => 'deprecated',
                'created_at' => now()->subYears(2),
            ]);

            Entry::factory()->create([
                'title' => 'Draft Entry',
                'status' => 'draft',
                'created_at' => now()->subYears(2),
            ]);

            $this->artisan('prune', [
                '--older-than' => '1y',
                '--deprecated-only' => true,
                '--dry-run' => true,
            ])
                ->expectsOutputToContain('Found 1 entry')
                ->expectsOutputToContain('Deprecated Entry')
                ->assertSuccessful();
        });
    });

    describe('deleting entries', function (): void {
        it('deletes entries with force option', function (): void {
            Entry::factory()->create(['created_at' => now()->subYears(2)]);

            $this->artisan('prune', [
                '--older-than' => '1y',
                '--force' => true,
            ])
                ->expectsOutputToContain('Pruned 1 entry')
                ->assertSuccessful();

            expect(Entry::count())->toBe(0);
        });

        it('deletes associated relationships', function (): void {
            $entry1 = Entry::factory()->create(['created_at' => now()->subYears(2)]);
            $entry2 = Entry::factory()->create(['created_at' => now()]);

            Relationship::factory()->create([
                'from_entry_id' => $entry1->id,
                'to_entry_id' => $entry2->id,
                'type' => 'relates_to',
            ]);

            $this->artisan('prune', [
                '--older-than' => '1y',
                '--force' => true,
            ])->assertSuccessful();

            expect(Relationship::count())->toBe(0);
        });

        it('asks for confirmation without force', function (): void {
            Entry::factory()->create(['created_at' => now()->subYears(2)]);

            $this->artisan('prune', ['--older-than' => '1y'])
                ->expectsConfirmation('Are you sure you want to permanently delete these entries?', 'no')
                ->expectsOutputToContain('Operation cancelled')
                ->assertSuccessful();

            expect(Entry::count())->toBe(1);
        });
    });

    describe('threshold parsing', function (): void {
        it('parses days', function (): void {
            Entry::factory()->create(['created_at' => now()->subDays(60)]);

            $this->artisan('prune', ['--older-than' => '30d', '--dry-run' => true])
                ->expectsOutputToContain('Found 1 entry')
                ->assertSuccessful();
        });

        it('parses months', function (): void {
            Entry::factory()->create(['created_at' => now()->subMonths(8)]);

            $this->artisan('prune', ['--older-than' => '6m', '--dry-run' => true])
                ->expectsOutputToContain('Found 1 entry')
                ->assertSuccessful();
        });

        it('parses years', function (): void {
            Entry::factory()->create(['created_at' => now()->subYears(3)]);

            $this->artisan('prune', ['--older-than' => '2y', '--dry-run' => true])
                ->expectsOutputToContain('Found 1 entry')
                ->assertSuccessful();
        });

        it('fails with invalid threshold format', function (): void {
            $this->artisan('prune', ['--older-than' => 'invalid'])
                ->expectsOutputToContain('Invalid threshold format')
                ->assertFailed();
        });
    });

    describe('command signature', function (): void {
        it('has the correct signature', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgePruneCommand::class);
            expect($command->getName())->toBe('prune');
        });

        it('has older-than option with default', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgePruneCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasOption('older-than'))->toBeTrue();
            expect($definition->getOption('older-than')->getDefault())->toBe('1y');
        });

        it('has deprecated-only option', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgePruneCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasOption('deprecated-only'))->toBeTrue();
        });

        it('has dry-run option', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgePruneCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasOption('dry-run'))->toBeTrue();
        });

        it('has force option', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgePruneCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasOption('force'))->toBeTrue();
        });
    });
});
