<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;

describe('KnowledgeConflictsCommand', function (): void {
    describe('finding explicit conflicts', function (): void {
        it('finds entries with conflicts_with relationships', function (): void {
            $entry1 = Entry::factory()->create(['title' => 'Always use eager loading']);
            $entry2 = Entry::factory()->create(['title' => 'Avoid eager loading for single records']);

            Relationship::factory()->create([
                'from_entry_id' => $entry1->id,
                'to_entry_id' => $entry2->id,
                'type' => 'conflicts_with',
            ]);

            $this->artisan('knowledge:conflicts')
                ->expectsOutputToContain('Found 1 explicit conflict')
                ->expectsOutputToContain('Always use eager loading')
                ->expectsOutputToContain('conflicts with')
                ->assertSuccessful();
        });

        it('shows no conflicts when none exist', function (): void {
            Entry::factory()->create();
            Entry::factory()->create();

            $this->artisan('knowledge:conflicts')
                ->expectsOutputToContain('No conflicts found')
                ->assertSuccessful();
        });
    });

    describe('finding potential conflicts', function (): void {
        it('finds entries with conflicting priorities in same category/module', function (): void {
            Entry::factory()->create([
                'title' => 'Laravel authentication setup guide',
                'category' => 'security',
                'module' => 'auth',
                'priority' => 'critical',
                'tags' => ['laravel', 'auth', 'security'],
                'status' => 'draft',
            ]);

            Entry::factory()->create([
                'title' => 'Laravel authentication optional steps',
                'category' => 'security',
                'module' => 'auth',
                'priority' => 'low',
                'tags' => ['laravel', 'auth', 'optional'],
                'status' => 'draft',
            ]);

            $this->artisan('knowledge:conflicts')
                ->expectsOutputToContain('potential conflict')
                ->expectsOutputToContain('conflicting priorities')
                ->assertSuccessful();
        });

        it('excludes deprecated entries from potential conflicts', function (): void {
            Entry::factory()->create([
                'title' => 'Important security setup',
                'category' => 'security',
                'module' => 'auth',
                'priority' => 'critical',
                'tags' => ['security', 'auth'],
                'status' => 'deprecated',
            ]);

            Entry::factory()->create([
                'title' => 'Optional security setup',
                'category' => 'security',
                'module' => 'auth',
                'priority' => 'low',
                'tags' => ['security', 'auth'],
                'status' => 'draft',
            ]);

            $this->artisan('knowledge:conflicts')
                ->expectsOutputToContain('No conflicts found')
                ->assertSuccessful();
        });

        it('finds potential conflicts by title similarity', function (): void {
            Entry::factory()->create([
                'title' => 'Database connection pooling optimization',
                'category' => 'performance',
                'module' => 'database',
                'priority' => 'critical',
                'tags' => [], // No tag overlap
                'status' => 'draft',
            ]);

            Entry::factory()->create([
                'title' => 'Database connection pooling considerations',
                'category' => 'performance',
                'module' => 'database',
                'priority' => 'low',
                'tags' => [], // No tag overlap
                'status' => 'draft',
            ]);

            $this->artisan('knowledge:conflicts')
                ->expectsOutputToContain('potential conflict')
                ->assertSuccessful();
        });
    });

    describe('filtering', function (): void {
        it('filters by category', function (): void {
            $entry1 = Entry::factory()->create(['category' => 'security']);
            $entry2 = Entry::factory()->create(['category' => 'security']);

            Relationship::factory()->create([
                'from_entry_id' => $entry1->id,
                'to_entry_id' => $entry2->id,
                'type' => 'conflicts_with',
            ]);

            $other1 = Entry::factory()->create(['category' => 'testing']);
            $other2 = Entry::factory()->create(['category' => 'testing']);

            Relationship::factory()->create([
                'from_entry_id' => $other1->id,
                'to_entry_id' => $other2->id,
                'type' => 'conflicts_with',
            ]);

            $this->artisan('knowledge:conflicts', ['--category' => 'security'])
                ->expectsOutputToContain('Found 1 explicit conflict')
                ->assertSuccessful();
        });

        it('filters by module', function (): void {
            $entry1 = Entry::factory()->create(['module' => 'auth']);
            $entry2 = Entry::factory()->create(['module' => 'auth']);

            Relationship::factory()->create([
                'from_entry_id' => $entry1->id,
                'to_entry_id' => $entry2->id,
                'type' => 'conflicts_with',
            ]);

            $this->artisan('knowledge:conflicts', ['--module' => 'auth'])
                ->expectsOutputToContain('Found 1 explicit conflict')
                ->assertSuccessful();

            $this->artisan('knowledge:conflicts', ['--module' => 'api'])
                ->expectsOutputToContain('No conflicts found')
                ->assertSuccessful();
        });
    });

    describe('output', function (): void {
        it('shows resolution suggestions', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();

            Relationship::factory()->create([
                'from_entry_id' => $entry1->id,
                'to_entry_id' => $entry2->id,
                'type' => 'conflicts_with',
            ]);

            $this->artisan('knowledge:conflicts')
                ->expectsOutputToContain('knowledge:deprecate')
                ->expectsOutputToContain('knowledge:merge')
                ->assertSuccessful();
        });
    });

    describe('command signature', function (): void {
        it('has the correct signature', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeConflictsCommand::class);
            expect($command->getName())->toBe('knowledge:conflicts');
        });

        it('has category option', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeConflictsCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasOption('category'))->toBeTrue();
        });

        it('has module option', function (): void {
            $command = $this->app->make(\App\Commands\KnowledgeConflictsCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasOption('module'))->toBeTrue();
        });
    });
});
