<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\Entry;

describe('knowledge:collection:remove command', function (): void {
    it('removes an entry from a collection', function (): void {
        $collection = Collection::factory()->create(['name' => 'My Collection']);
        $entry = Entry::factory()->create();
        $collection->entries()->attach($entry, ['sort_order' => 0]);

        $this->artisan('collection:remove', [
            'collection' => 'My Collection',
            'entry_id' => $entry->id,
        ])
            ->expectsOutput("Entry #{$entry->id} removed from collection \"My Collection\".")
            ->assertExitCode(0);

        expect($collection->entries()->count())->toBe(0);
    });

    it('shows error when collection not found', function (): void {
        $entry = Entry::factory()->create();

        $this->artisan('collection:remove', [
            'collection' => 'Nonexistent',
            'entry_id' => $entry->id,
        ])
            ->expectsOutput('Error: Collection "Nonexistent" not found.')
            ->assertExitCode(1);
    });

    it('shows error when entry not found', function (): void {
        $collection = Collection::factory()->create(['name' => 'My Collection']);

        $this->artisan('collection:remove', [
            'collection' => 'My Collection',
            'entry_id' => 99999,
        ])
            ->expectsOutput('Error: Entry #99999 not found.')
            ->assertExitCode(1);
    });

    it('shows error when entry not in collection', function (): void {
        $collection = Collection::factory()->create(['name' => 'My Collection']);
        $entry = Entry::factory()->create();

        $this->artisan('collection:remove', [
            'collection' => 'My Collection',
            'entry_id' => $entry->id,
        ])
            ->expectsOutput("Error: Entry #{$entry->id} is not in collection \"My Collection\".")
            ->assertExitCode(1);
    });
});
