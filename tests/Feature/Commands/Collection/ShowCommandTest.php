<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\Entry;

describe('knowledge:collection:show command', function (): void {
    it('shows collection details with entries', function (): void {
        $collection = Collection::factory()->create([
            'name' => 'My Collection',
            'description' => 'Test description',
        ]);

        $entry1 = Entry::factory()->create(['title' => 'First Entry']);
        $entry2 = Entry::factory()->create(['title' => 'Second Entry']);
        $entry3 = Entry::factory()->create(['title' => 'Third Entry']);

        $collection->entries()->attach([
            $entry1->id => ['sort_order' => 0],
            $entry2->id => ['sort_order' => 1],
            $entry3->id => ['sort_order' => 2],
        ]);

        $this->artisan('knowledge:collection:show', ['name' => 'My Collection'])
            ->expectsOutputToContain('My Collection')
            ->expectsOutputToContain('Test description')
            ->expectsOutputToContain('First Entry')
            ->expectsOutputToContain('Second Entry')
            ->expectsOutputToContain('Third Entry')
            ->assertExitCode(0);
    });

    it('shows collection without description', function (): void {
        $collection = Collection::factory()->create([
            'name' => 'No Description',
            'description' => null,
        ]);

        $this->artisan('knowledge:collection:show', ['name' => 'No Description'])
            ->expectsOutputToContain('No Description')
            ->assertExitCode(0);
    });

    it('displays entries in sort order', function (): void {
        $collection = Collection::factory()->create(['name' => 'Sorted Collection']);

        $entry1 = Entry::factory()->create(['title' => 'Entry A']);
        $entry2 = Entry::factory()->create(['title' => 'Entry B']);
        $entry3 = Entry::factory()->create(['title' => 'Entry C']);

        $collection->entries()->attach([
            $entry3->id => ['sort_order' => 2],
            $entry1->id => ['sort_order' => 0],
            $entry2->id => ['sort_order' => 1],
        ]);

        $this->artisan('knowledge:collection:show', ['name' => 'Sorted Collection'])
            ->expectsOutputToContain('Entry A')
            ->expectsOutputToContain('Entry B')
            ->expectsOutputToContain('Entry C')
            ->assertExitCode(0);
    });

    it('shows message when collection is empty', function (): void {
        $collection = Collection::factory()->create(['name' => 'Empty Collection']);

        $this->artisan('knowledge:collection:show', ['name' => 'Empty Collection'])
            ->expectsOutputToContain('Empty Collection')
            ->expectsOutputToContain('No entries in this collection')
            ->assertExitCode(0);
    });

    it('shows error when collection not found', function (): void {
        $this->artisan('knowledge:collection:show', ['name' => 'Nonexistent'])
            ->expectsOutput('Error: Collection "Nonexistent" not found.')
            ->assertExitCode(1);
    });

    it('displays entry IDs and sort order', function (): void {
        $collection = Collection::factory()->create(['name' => 'Test Collection']);
        $entry = Entry::factory()->create(['title' => 'Test Entry']);

        $collection->entries()->attach($entry, ['sort_order' => 5]);

        $this->artisan('knowledge:collection:show', ['name' => 'Test Collection'])
            ->expectsOutputToContain((string) $entry->id)
            ->assertExitCode(0);
    });
});
