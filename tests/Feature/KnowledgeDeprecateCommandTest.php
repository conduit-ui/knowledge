<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;

describe('KnowledgeDeprecateCommand', function (): void {
    describe('deprecating entries', function (): void {
        it('deprecates an entry', function (): void {
            $entry = Entry::factory()->create(['status' => 'draft', 'confidence' => 80]);

            $this->artisan('deprecate', ['id' => $entry->id])
                ->expectsOutputToContain("Entry #{$entry->id} has been deprecated")
                ->expectsOutputToContain('Status: draft -> deprecated')
                ->expectsOutputToContain('Confidence: 0%')
                ->assertSuccessful();

            $entry->refresh();
            expect($entry->status)->toBe('deprecated');
            expect($entry->confidence)->toBe(0);
        });

        it('deprecates an entry with replacement', function (): void {
            $entry = Entry::factory()->create(['status' => 'draft']);
            $replacement = Entry::factory()->create(['title' => 'Better Pattern']);

            $this->artisan('deprecate', [
                'id' => $entry->id,
                '--replacement' => $replacement->id,
            ])
                ->expectsOutputToContain("Entry #{$entry->id} has been deprecated")
                ->expectsOutputToContain("Linked to replacement: #{$replacement->id}")
                ->assertSuccessful();

            // Check relationship was created
            $relationship = Relationship::where('from_entry_id', $entry->id)
                ->where('to_entry_id', $replacement->id)
                ->where('type', 'replaced_by')
                ->first();

            expect($relationship)->not->toBeNull();
        });

        it('warns when entry is already deprecated', function (): void {
            $entry = Entry::factory()->create(['status' => 'deprecated']);

            $this->artisan('deprecate', ['id' => $entry->id])
                ->expectsOutputToContain("Entry #{$entry->id} is already deprecated")
                ->assertSuccessful();
        });
    });

    describe('validation', function (): void {
        it('fails with non-numeric id', function (): void {
            $this->artisan('deprecate', ['id' => 'abc'])
                ->expectsOutputToContain('Entry ID must be a number')
                ->assertFailed();
        });

        it('fails when entry not found', function (): void {
            $this->artisan('deprecate', ['id' => 99999])
                ->expectsOutputToContain('Entry not found')
                ->assertFailed();
        });

        it('fails with non-numeric replacement id', function (): void {
            $entry = Entry::factory()->create(['status' => 'draft']);

            $this->artisan('deprecate', [
                'id' => $entry->id,
                '--replacement' => 'abc',
            ])
                ->expectsOutputToContain('Replacement ID must be a number')
                ->assertFailed();
        });

        it('fails when replacement entry not found', function (): void {
            $entry = Entry::factory()->create(['status' => 'draft']);

            $this->artisan('deprecate', [
                'id' => $entry->id,
                '--replacement' => 99999,
            ])
                ->expectsOutputToContain('Replacement entry not found')
                ->assertFailed();
        });

        it('fails when entry tries to replace itself', function (): void {
            $entry = Entry::factory()->create(['status' => 'draft']);

            $this->artisan('deprecate', [
                'id' => $entry->id,
                '--replacement' => $entry->id,
            ])
                ->expectsOutputToContain('An entry cannot replace itself')
                ->assertFailed();
        });
    });

    describe('command signature', function (): void {
        it('has the correct signature', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeDeprecateCommand::class);
            expect($command->getName())->toBe('deprecate');
        });

        it('has replacement option', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeDeprecateCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasOption('replacement'))->toBeTrue();
        });
    });
});
