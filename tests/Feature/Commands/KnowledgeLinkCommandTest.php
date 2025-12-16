<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;

describe('knowledge:link command', function (): void {
    it('creates a relationship between two entries', function (): void {
        $entry1 = Entry::factory()->create(['title' => 'Entry One']);
        $entry2 = Entry::factory()->create(['title' => 'Entry Two']);

        $this->artisan('knowledge:link', [
            'from' => $entry1->id,
            'to' => $entry2->id,
        ])
            ->expectsOutput('Created relates_to relationship #1')
            ->assertSuccessful();

        expect(Relationship::count())->toBe(1);

        $relationship = Relationship::first();
        expect($relationship->from_entry_id)->toBe($entry1->id);
        expect($relationship->to_entry_id)->toBe($entry2->id);
        expect($relationship->type)->toBe(Relationship::TYPE_RELATES_TO);
    });

    it('creates a relationship with specified type', function (): void {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();

        $this->artisan('knowledge:link', [
            'from' => $entry1->id,
            'to' => $entry2->id,
            '--type' => Relationship::TYPE_DEPENDS_ON,
        ])
            ->expectsOutputToContain('depends_on')
            ->assertSuccessful();

        $relationship = Relationship::first();
        expect($relationship->type)->toBe(Relationship::TYPE_DEPENDS_ON);
    });

    it('creates bidirectional relationship when flag is set', function (): void {
        $entry1 = Entry::factory()->create(['title' => 'Entry One']);
        $entry2 = Entry::factory()->create(['title' => 'Entry Two']);

        $this->artisan('knowledge:link', [
            'from' => $entry1->id,
            'to' => $entry2->id,
            '--bidirectional' => true,
        ])
            ->expectsOutputToContain('bidirectional')
            ->assertSuccessful();

        expect(Relationship::count())->toBe(2);

        $rel1 = Relationship::where('from_entry_id', $entry1->id)->first();
        $rel2 = Relationship::where('from_entry_id', $entry2->id)->first();

        expect($rel1->to_entry_id)->toBe($entry2->id);
        expect($rel2->to_entry_id)->toBe($entry1->id);
    });

    it('creates relationship with metadata', function (): void {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();
        $metadata = json_encode(['reason' => 'testing']);

        $this->artisan('knowledge:link', [
            'from' => $entry1->id,
            'to' => $entry2->id,
            '--metadata' => $metadata,
        ])
            ->expectsOutputToContain('Metadata')
            ->expectsOutputToContain('reason')
            ->assertSuccessful();

        $relationship = Relationship::first();
        expect($relationship->metadata)->toBe(['reason' => 'testing']);
        expect($relationship->metadata['reason'])->toBe('testing');
    });

    it('fails with invalid relationship type', function (): void {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();

        $this->artisan('knowledge:link', [
            'from' => $entry1->id,
            'to' => $entry2->id,
            '--type' => 'invalid_type',
        ])
            ->expectsOutputToContain('Invalid relationship type')
            ->expectsOutputToContain('Valid types:')
            ->assertFailed();
    });

    it('fails when from entry does not exist', function (): void {
        $entry = Entry::factory()->create();

        $this->artisan('knowledge:link', [
            'from' => 99999,
            'to' => $entry->id,
        ])
            ->expectsOutputToContain('Entry 99999 not found')
            ->assertFailed();
    });

    it('fails when to entry does not exist', function (): void {
        $entry = Entry::factory()->create();

        $this->artisan('knowledge:link', [
            'from' => $entry->id,
            'to' => 99999,
        ])
            ->expectsOutputToContain('Entry 99999 not found')
            ->assertFailed();
    });

    it('fails with invalid JSON metadata', function (): void {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();

        $this->artisan('knowledge:link', [
            'from' => $entry1->id,
            'to' => $entry2->id,
            '--metadata' => 'invalid json',
        ])
            ->expectsOutputToContain('Invalid JSON metadata')
            ->assertFailed();
    });

    it('prevents circular dependencies', function (): void {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();

        // Create dependency: 1 depends on 2
        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => Relationship::TYPE_DEPENDS_ON,
        ]);

        // Try to create reverse dependency: 2 depends on 1
        $this->artisan('knowledge:link', [
            'from' => $entry2->id,
            'to' => $entry1->id,
            '--type' => Relationship::TYPE_DEPENDS_ON,
        ])
            ->expectsOutputToContain('circular dependency')
            ->assertFailed();
    });

    it('shows entry titles in output', function (): void {
        $entry1 = Entry::factory()->create(['title' => 'First Entry']);
        $entry2 = Entry::factory()->create(['title' => 'Second Entry']);

        $this->artisan('knowledge:link', [
            'from' => $entry1->id,
            'to' => $entry2->id,
        ])
            ->expectsOutputToContain('First Entry')
            ->expectsOutputToContain('Second Entry')
            ->assertSuccessful();
    });
});
