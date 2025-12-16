<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\Entry;

describe('knowledge:collection:add command', function (): void {
    it('adds an entry to a collection', function (): void {
        $collection = Collection::factory()->create(['name' => 'My Collection']);
        $entry = Entry::factory()->create();

        $this->artisan('knowledge:collection:add', [
            'collection' => 'My Collection',
            'entry_id' => $entry->id,
        ])
            ->expectsOutput("Entry #{$entry->id} added to collection \"My Collection\".")
            ->assertExitCode(0);

        expect($collection->entries()->count())->toBe(1);
        expect($collection->entries->first()->id)->toBe($entry->id);
    });

    it('adds entry with custom sort order', function (): void {
        $collection = Collection::factory()->create(['name' => 'Test Collection']);
        $entry = Entry::factory()->create();

        $this->artisan('knowledge:collection:add', [
            'collection' => 'Test Collection',
            'entry_id' => $entry->id,
            '--order' => 5,
        ])
            ->assertExitCode(0);

        $collection->refresh();
        expect($collection->entries->first()->pivot->sort_order)->toBe(5);
    });

    it('shows error when collection not found', function (): void {
        $entry = Entry::factory()->create();

        $this->artisan('knowledge:collection:add', [
            'collection' => 'Nonexistent',
            'entry_id' => $entry->id,
        ])
            ->expectsOutput('Error: Collection "Nonexistent" not found.')
            ->assertExitCode(1);
    });

    it('shows error when entry not found', function (): void {
        $collection = Collection::factory()->create(['name' => 'My Collection']);

        $this->artisan('knowledge:collection:add', [
            'collection' => 'My Collection',
            'entry_id' => 99999,
        ])
            ->expectsOutput('Error: Entry #99999 not found.')
            ->assertExitCode(1);
    });

    it('shows error when entry already in collection', function (): void {
        $collection = Collection::factory()->create(['name' => 'My Collection']);
        $entry = Entry::factory()->create();
        $collection->entries()->attach($entry, ['sort_order' => 0]);

        $this->artisan('knowledge:collection:add', [
            'collection' => 'My Collection',
            'entry_id' => $entry->id,
        ])
            ->expectsOutput("Error: Entry #{$entry->id} is already in collection \"My Collection\".")
            ->assertExitCode(1);
    });

    it('auto-assigns sort order when not provided', function (): void {
        $collection = Collection::factory()->create(['name' => 'Test Collection']);
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();
        $entry3 = Entry::factory()->create();

        $this->artisan('knowledge:collection:add', [
            'collection' => 'Test Collection',
            'entry_id' => $entry1->id,
        ])->assertExitCode(0);

        $this->artisan('knowledge:collection:add', [
            'collection' => 'Test Collection',
            'entry_id' => $entry2->id,
        ])->assertExitCode(0);

        $this->artisan('knowledge:collection:add', [
            'collection' => 'Test Collection',
            'entry_id' => $entry3->id,
        ])->assertExitCode(0);

        $collection->refresh();
        $entries = $collection->entries;

        expect($entries[0]->pivot->sort_order)->toBe(0);
        expect($entries[1]->pivot->sort_order)->toBe(1);
        expect($entries[2]->pivot->sort_order)->toBe(2);
    });
});
