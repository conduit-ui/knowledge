<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;

describe('KnowledgeMergeCommand', function (): void {
    describe('merging entries', function (): void {
        it('merges two entries', function (): void {
            $primary = Entry::factory()->create([
                'title' => 'Primary Entry',
                'content' => 'Primary content.',
                'tags' => ['php', 'laravel'],
                'confidence' => 70,
                'priority' => 'medium',
                'usage_count' => 5,
            ]);

            $secondary = Entry::factory()->create([
                'title' => 'Secondary Entry',
                'content' => 'Secondary content.',
                'tags' => ['laravel', 'pest'],
                'confidence' => 80,
                'priority' => 'high',
                'usage_count' => 3,
            ]);

            $this->artisan('merge', [
                'primary' => $primary->id,
                'secondary' => $secondary->id,
            ])
                ->expectsOutputToContain('Merging entries')
                ->expectsOutputToContain('Entries merged successfully')
                ->assertSuccessful();

            $primary->refresh();
            $secondary->refresh();

            // Primary should have merged data
            expect($primary->tags)->toContain('php');
            expect($primary->tags)->toContain('laravel');
            expect($primary->tags)->toContain('pest');
            expect($primary->confidence)->toBe(80);
            expect($primary->priority)->toBe('high');
            expect($primary->usage_count)->toBe(8);
            expect($primary->content)->toContain('Merged from entry');

            // Secondary should be deprecated
            expect($secondary->status)->toBe('deprecated');
            expect($secondary->confidence)->toBe(0);
        });

        it('creates replaced_by relationship', function (): void {
            $primary = Entry::factory()->create();
            $secondary = Entry::factory()->create();

            $this->artisan('merge', [
                'primary' => $primary->id,
                'secondary' => $secondary->id,
            ])->assertSuccessful();

            $relationship = Relationship::where('from_entry_id', $secondary->id)
                ->where('to_entry_id', $primary->id)
                ->where('type', 'replaced_by')
                ->first();

            expect($relationship)->not->toBeNull();
        });

        it('transfers relationships from secondary to primary', function (): void {
            $primary = Entry::factory()->create();
            $secondary = Entry::factory()->create();
            $related = Entry::factory()->create();

            // Create relationship from secondary to related
            Relationship::factory()->create([
                'from_entry_id' => $secondary->id,
                'to_entry_id' => $related->id,
                'type' => 'relates_to',
            ]);

            $this->artisan('merge', [
                'primary' => $primary->id,
                'secondary' => $secondary->id,
            ])
                ->expectsOutputToContain('Transferred 1 relationship')
                ->assertSuccessful();

            // Primary should now have the relationship
            $relationship = Relationship::where('from_entry_id', $primary->id)
                ->where('to_entry_id', $related->id)
                ->where('type', 'relates_to')
                ->first();

            expect($relationship)->not->toBeNull();
        });

        it('transfers incoming relationships from secondary to primary', function (): void {
            $primary = Entry::factory()->create();
            $secondary = Entry::factory()->create();
            $related = Entry::factory()->create();

            // Create incoming relationship to secondary
            Relationship::factory()->create([
                'from_entry_id' => $related->id,
                'to_entry_id' => $secondary->id,
                'type' => 'depends_on',
            ]);

            $this->artisan('merge', [
                'primary' => $primary->id,
                'secondary' => $secondary->id,
            ])
                ->expectsOutputToContain('Transferred 1 relationship')
                ->assertSuccessful();

            // Primary should now have the incoming relationship
            $relationship = Relationship::where('from_entry_id', $related->id)
                ->where('to_entry_id', $primary->id)
                ->where('type', 'depends_on')
                ->first();

            expect($relationship)->not->toBeNull();
        });

        it('skips outgoing relationships that point to primary', function (): void {
            $primary = Entry::factory()->create();
            $secondary = Entry::factory()->create();

            // Create relationship from secondary to primary (should be skipped)
            Relationship::factory()->create([
                'from_entry_id' => $secondary->id,
                'to_entry_id' => $primary->id,
                'type' => 'relates_to',
            ]);

            $this->artisan('merge', [
                'primary' => $primary->id,
                'secondary' => $secondary->id,
            ])
                ->assertSuccessful();

            // Should not create duplicate self-referential relationship
            $count = Relationship::where('from_entry_id', $primary->id)
                ->where('to_entry_id', $primary->id)
                ->where('type', 'relates_to')
                ->count();

            expect($count)->toBe(0);
        });

        it('skips incoming relationships from primary', function (): void {
            $primary = Entry::factory()->create();
            $secondary = Entry::factory()->create();

            // Create relationship from primary to secondary (should be skipped)
            Relationship::factory()->create([
                'from_entry_id' => $primary->id,
                'to_entry_id' => $secondary->id,
                'type' => 'relates_to',
            ]);

            $this->artisan('merge', [
                'primary' => $primary->id,
                'secondary' => $secondary->id,
            ])
                ->assertSuccessful();

            // Should not create duplicate self-referential relationship
            $count = Relationship::where('from_entry_id', $primary->id)
                ->where('to_entry_id', $primary->id)
                ->where('type', 'relates_to')
                ->count();

            expect($count)->toBe(0);
        });

        it('skips duplicate relationships', function (): void {
            $primary = Entry::factory()->create();
            $secondary = Entry::factory()->create();
            $related = Entry::factory()->create();

            // Create relationship from secondary to related
            Relationship::factory()->create([
                'from_entry_id' => $secondary->id,
                'to_entry_id' => $related->id,
                'type' => 'relates_to',
            ]);

            // Also create the same relationship from primary (already exists)
            Relationship::factory()->create([
                'from_entry_id' => $primary->id,
                'to_entry_id' => $related->id,
                'type' => 'relates_to',
            ]);

            $this->artisan('merge', [
                'primary' => $primary->id,
                'secondary' => $secondary->id,
            ])
                ->assertSuccessful();

            // Should only have one relationship
            $count = Relationship::where('from_entry_id', $primary->id)
                ->where('to_entry_id', $related->id)
                ->where('type', 'relates_to')
                ->count();

            expect($count)->toBe(1);
        });
    });

    describe('keep-both option', function (): void {
        it('keeps both entries but links them', function (): void {
            $primary = Entry::factory()->create(['confidence' => 80]);
            $secondary = Entry::factory()->create(['confidence' => 90]);

            $this->artisan('merge', [
                'primary' => $primary->id,
                'secondary' => $secondary->id,
                '--keep-both' => true,
            ])
                ->expectsOutputToContain('Entries linked')
                ->expectsOutputToContain('deprecated')
                ->assertSuccessful();

            $primary->refresh();
            $secondary->refresh();

            // Primary should not have merged confidence
            expect($primary->confidence)->toBe(80);

            // Secondary should be deprecated but keep its confidence
            expect($secondary->status)->toBe('deprecated');
        });
    });

    describe('validation', function (): void {
        it('fails with non-numeric primary id', function (): void {
            $this->artisan('merge', [
                'primary' => 'abc',
                'secondary' => '1',
            ])
                ->expectsOutputToContain('Primary ID must be a number')
                ->assertFailed();
        });

        it('fails with non-numeric secondary id', function (): void {
            $this->artisan('merge', [
                'primary' => '1',
                'secondary' => 'abc',
            ])
                ->expectsOutputToContain('Secondary ID must be a number')
                ->assertFailed();
        });

        it('fails when merging entry with itself', function (): void {
            $entry = Entry::factory()->create();

            $this->artisan('merge', [
                'primary' => $entry->id,
                'secondary' => $entry->id,
            ])
                ->expectsOutputToContain('Cannot merge an entry with itself')
                ->assertFailed();
        });

        it('fails when primary entry not found', function (): void {
            $secondary = Entry::factory()->create();

            $this->artisan('merge', [
                'primary' => 99999,
                'secondary' => $secondary->id,
            ])
                ->expectsOutputToContain('Primary entry not found')
                ->assertFailed();
        });

        it('fails when secondary entry not found', function (): void {
            $primary = Entry::factory()->create();

            $this->artisan('merge', [
                'primary' => $primary->id,
                'secondary' => 99999,
            ])
                ->expectsOutputToContain('Secondary entry not found')
                ->assertFailed();
        });
    });

    describe('command signature', function (): void {
        it('has the correct signature', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeMergeCommand::class);
            expect($command->getName())->toBe('merge');
        });

        it('has keep-both option', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeMergeCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasOption('keep-both'))->toBeTrue();
        });
    });
});
