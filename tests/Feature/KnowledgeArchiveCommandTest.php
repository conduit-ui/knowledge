<?php

declare(strict_types=1);

use App\Models\Entry;

describe('KnowledgeArchiveCommand', function (): void {
    describe('archiving entries', function (): void {
        it('archives an entry', function (): void {
            $entry = Entry::factory()->create([
                'status' => 'draft',
                'confidence' => 80,
            ]);

            $this->artisan('archive', ['id' => $entry->id])
                ->expectsOutputToContain("Entry #{$entry->id} has been archived")
                ->expectsOutputToContain('Status: draft -> deprecated')
                ->expectsOutputToContain('--restore')
                ->assertSuccessful();

            $entry->refresh();
            expect($entry->status)->toBe('deprecated');
            expect($entry->confidence)->toBe(0);
        });

        it('warns when entry is already archived', function (): void {
            $entry = Entry::factory()->create(['status' => 'deprecated']);

            $this->artisan('archive', ['id' => $entry->id])
                ->expectsOutputToContain("Entry #{$entry->id} is already archived")
                ->assertSuccessful();
        });
    });

    describe('restoring entries', function (): void {
        it('restores an archived entry', function (): void {
            $entry = Entry::factory()->create([
                'status' => 'deprecated',
                'confidence' => 0,
            ]);

            $this->artisan('archive', [
                'id' => $entry->id,
                '--restore' => true,
            ])
                ->expectsOutputToContain("Entry #{$entry->id} has been restored")
                ->expectsOutputToContain('Status: deprecated -> draft')
                ->expectsOutputToContain('Confidence: 50%')
                ->assertSuccessful();

            $entry->refresh();
            expect($entry->status)->toBe('draft');
            expect($entry->confidence)->toBe(50);
        });

        it('warns when entry is not archived', function (): void {
            $entry = Entry::factory()->create(['status' => 'validated']);

            $this->artisan('archive', [
                'id' => $entry->id,
                '--restore' => true,
            ])
                ->expectsOutputToContain('is not archived')
                ->assertSuccessful();
        });
    });

    describe('validation', function (): void {
        it('fails with non-numeric id', function (): void {
            $this->artisan('archive', ['id' => 'abc'])
                ->expectsOutputToContain('Entry ID must be a number')
                ->assertFailed();
        });

        it('fails when entry not found', function (): void {
            $this->artisan('archive', ['id' => 99999])
                ->expectsOutputToContain('Entry not found')
                ->assertFailed();
        });
    });

    describe('command signature', function (): void {
        it('has the correct signature', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeArchiveCommand::class);
            expect($command->getName())->toBe('archive');
        });

        it('has restore option', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeArchiveCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasOption('restore'))->toBeTrue();
        });
    });
});
