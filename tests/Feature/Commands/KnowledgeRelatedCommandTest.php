<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;

describe('knowledge:related command', function (): void {
    it('shows all relationships for an entry', function (): void {
        $entry = Entry::factory()->create(['title' => 'Main Entry']);
        $other1 = Entry::factory()->create(['title' => 'Related One']);
        $other2 = Entry::factory()->create(['title' => 'Related Two']);

        Relationship::factory()->create([
            'from_entry_id' => $entry->id,
            'to_entry_id' => $other1->id,
            'type' => Relationship::TYPE_RELATES_TO,
        ]);
        Relationship::factory()->create([
            'from_entry_id' => $other2->id,
            'to_entry_id' => $entry->id,
            'type' => Relationship::TYPE_REFERENCES,
        ]);

        $this->artisan('knowledge:related', ['id' => $entry->id])
            ->expectsOutputToContain('Main Entry')
            ->expectsOutputToContain('Outgoing Relationships')
            ->expectsOutputToContain('Incoming Relationships')
            ->expectsOutputToContain('relates_to')
            ->expectsOutputToContain('references')
            ->expectsOutputToContain('Related One')
            ->expectsOutputToContain('Related Two')
            ->assertSuccessful();
    });

    it('shows message when entry has no relationships', function (): void {
        $entry = Entry::factory()->create();

        $this->artisan('knowledge:related', ['id' => $entry->id])
            ->expectsOutputToContain('Outgoing Relationships')
            ->expectsOutputToContain('Incoming Relationships')
            ->assertSuccessful();

        // Verify no relationships exist
        expect($entry->outgoingRelationships()->count())->toBe(0);
        expect($entry->incomingRelationships()->count())->toBe(0);
    });

    it('groups relationships by type', function (): void {
        $entry = Entry::factory()->create();
        $other1 = Entry::factory()->create(['title' => 'Dependency']);
        $other2 = Entry::factory()->create(['title' => 'Related']);

        Relationship::factory()->create([
            'from_entry_id' => $entry->id,
            'to_entry_id' => $other1->id,
            'type' => Relationship::TYPE_DEPENDS_ON,
        ]);
        Relationship::factory()->create([
            'from_entry_id' => $entry->id,
            'to_entry_id' => $other2->id,
            'type' => Relationship::TYPE_RELATES_TO,
        ]);

        $this->artisan('knowledge:related', ['id' => $entry->id])
            ->expectsOutputToContain('depends_on')
            ->expectsOutputToContain('relates_to')
            ->expectsOutputToContain('Dependency')
            ->expectsOutputToContain('Related')
            ->assertSuccessful();
    });

    it('shows relationship metadata', function (): void {
        $entry = Entry::factory()->create();
        $other = Entry::factory()->create();

        $relationship = Relationship::factory()->create([
            'from_entry_id' => $entry->id,
            'to_entry_id' => $other->id,
            'metadata' => ['reason' => 'test metadata'],
        ]);

        $this->artisan('knowledge:related', ['id' => $entry->id])
            ->expectsOutputToContain('Metadata')
            ->assertSuccessful();

        // Verify metadata was stored
        expect($relationship->fresh()->metadata)->toBe(['reason' => 'test metadata']);
    });

    it('shows incoming relationship metadata', function (): void {
        $entry = Entry::factory()->create(['title' => 'Target Entry']);
        $source = Entry::factory()->create(['title' => 'Source Entry']);

        Relationship::factory()->create([
            'from_entry_id' => $source->id,
            'to_entry_id' => $entry->id,
            'metadata' => ['reason' => 'incoming test'],
        ]);

        // The command outputs the incoming relationships and their metadata
        $this->artisan('knowledge:related', ['id' => $entry->id])
            ->expectsOutputToContain('Incoming Relationships')
            ->expectsOutputToContain('Source Entry')
            ->assertSuccessful();

        // Verify metadata was actually stored
        $relationship = Relationship::where('to_entry_id', $entry->id)->first();
        expect($relationship->metadata)->toBe(['reason' => 'incoming test']);
    });

    it('fails when entry does not exist', function (): void {
        $this->artisan('knowledge:related', ['id' => 99999])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    });

    it('shows suggested related entries with --suggest flag', function (): void {
        $entry1 = Entry::factory()->create(['title' => 'Entry One']);
        $entry2 = Entry::factory()->create(['title' => 'Entry Two']);
        $entry3 = Entry::factory()->create(['title' => 'Entry Three']);

        // Create chain: 1 -> 2 -> 3
        Relationship::factory()->create(['from_entry_id' => $entry1->id, 'to_entry_id' => $entry2->id]);
        Relationship::factory()->create(['from_entry_id' => $entry2->id, 'to_entry_id' => $entry3->id]);

        $this->artisan('knowledge:related', ['id' => $entry1->id, '--suggest' => true])
            ->expectsOutputToContain('Suggested Related Entries')
            ->expectsOutputToContain('Entry Three')
            ->assertSuccessful();
    });

    it('shows no suggestions message when none available', function (): void {
        $entry = Entry::factory()->create();

        $this->artisan('knowledge:related', ['id' => $entry->id, '--suggest' => true])
            ->expectsOutputToContain('Suggested Related Entries')
            ->expectsOutputToContain('No suggestions available')
            ->assertSuccessful();
    });

    it('shows relationship IDs', function (): void {
        $entry = Entry::factory()->create();
        $other = Entry::factory()->create();

        $relationship = Relationship::factory()->create([
            'from_entry_id' => $entry->id,
            'to_entry_id' => $other->id,
        ]);

        $this->artisan('knowledge:related', ['id' => $entry->id])
            ->expectsOutputToContain("#{$relationship->id}")
            ->assertSuccessful();
    });

    it('distinguishes between incoming and outgoing relationships', function (): void {
        $entry = Entry::factory()->create(['title' => 'Main']);
        $outgoing = Entry::factory()->create(['title' => 'Outgoing Target']);
        $incoming = Entry::factory()->create(['title' => 'Incoming Source']);

        Relationship::factory()->create([
            'from_entry_id' => $entry->id,
            'to_entry_id' => $outgoing->id,
        ]);
        Relationship::factory()->create([
            'from_entry_id' => $incoming->id,
            'to_entry_id' => $entry->id,
        ]);

        $this->artisan('knowledge:related', ['id' => $entry->id])
            ->expectsOutputToContain('→') // Outgoing
            ->expectsOutputToContain('←') // Incoming
            ->assertSuccessful();
    });
});
