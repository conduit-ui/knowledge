<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;

describe('knowledge:graph command', function (): void {
    it('visualizes relationship graph for an entry', function (): void {
        $entry1 = Entry::factory()->create(['title' => 'Root']);
        $entry2 = Entry::factory()->create(['title' => 'Child']);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
        ]);

        $this->artisan('knowledge:graph', ['id' => $entry1->id])
            ->expectsOutputToContain('Relationship Graph for: Root')
            ->expectsOutputToContain('Graph Statistics')
            ->expectsOutputToContain('Nodes: 2')
            ->expectsOutputToContain('Graph Visualization')
            ->assertSuccessful();
    });

    it('respects depth parameter', function (): void {
        $entry1 = Entry::factory()->create(['title' => 'Level 0']);
        $entry2 = Entry::factory()->create(['title' => 'Level 1']);
        $entry3 = Entry::factory()->create(['title' => 'Level 2']);

        Relationship::factory()->create(['from_entry_id' => $entry1->id, 'to_entry_id' => $entry2->id]);
        Relationship::factory()->create(['from_entry_id' => $entry2->id, 'to_entry_id' => $entry3->id]);

        $this->artisan('knowledge:graph', ['id' => $entry1->id, '--depth' => 1])
            ->expectsOutputToContain('Nodes: 2')
            ->expectsOutputToContain('Level 1')
            ->assertSuccessful();

        $this->artisan('knowledge:graph', ['id' => $entry1->id, '--depth' => 2])
            ->expectsOutputToContain('Nodes: 3')
            ->expectsOutputToContain('Level 2')
            ->assertSuccessful();
    });

    it('filters by relationship types', function (): void {
        $entry1 = Entry::factory()->create(['title' => 'Root']);
        $entry2 = Entry::factory()->create(['title' => 'Dependency']);
        $entry3 = Entry::factory()->create(['title' => 'Related']);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => Relationship::TYPE_DEPENDS_ON,
        ]);
        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry3->id,
            'type' => Relationship::TYPE_RELATES_TO,
        ]);

        $this->artisan('knowledge:graph', [
            'id' => $entry1->id,
            '--type' => [Relationship::TYPE_DEPENDS_ON],
        ])
            ->expectsOutputToContain('Filtered by types: depends_on')
            ->expectsOutputToContain('Dependency')
            ->expectsOutputToContain('Nodes: 2')
            ->assertSuccessful();
    });

    it('shows relationship details', function (): void {
        $entry1 = Entry::factory()->create(['title' => 'Entry One']);
        $entry2 = Entry::factory()->create(['title' => 'Entry Two']);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => Relationship::TYPE_EXTENDS,
        ]);

        $this->artisan('knowledge:graph', ['id' => $entry1->id])
            ->expectsOutputToContain('Relationship Details')
            ->expectsOutputToContain('extends')
            ->expectsOutputToContain('Entry One')
            ->expectsOutputToContain('Entry Two')
            ->assertSuccessful();
    });

    it('handles entries with no relationships', function (): void {
        $entry = Entry::factory()->create(['title' => 'Lonely Entry']);

        $this->artisan('knowledge:graph', ['id' => $entry->id])
            ->expectsOutputToContain('Lonely Entry')
            ->expectsOutputToContain('No relationships found')
            ->assertSuccessful();
    });

    it('fails when entry does not exist', function (): void {
        $this->artisan('knowledge:graph', ['id' => 99999])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    });

    it('validates depth parameter', function (): void {
        $entry = Entry::factory()->create();

        $this->artisan('knowledge:graph', ['id' => $entry->id, '--depth' => -1])
            ->expectsOutputToContain('Depth must be between 0 and 10')
            ->assertFailed();

        $this->artisan('knowledge:graph', ['id' => $entry->id, '--depth' => 11])
            ->expectsOutputToContain('Depth must be between 0 and 10')
            ->assertFailed();
    });

    it('shows edge count in statistics', function (): void {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();
        $entry3 = Entry::factory()->create();

        Relationship::factory()->create(['from_entry_id' => $entry1->id, 'to_entry_id' => $entry2->id]);
        Relationship::factory()->create(['from_entry_id' => $entry1->id, 'to_entry_id' => $entry3->id]);

        $this->artisan('knowledge:graph', ['id' => $entry1->id])
            ->expectsOutputToContain('Edges: 2')
            ->assertSuccessful();
    });

    it('groups relationships by type in details section', function (): void {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();
        $entry3 = Entry::factory()->create();

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => Relationship::TYPE_DEPENDS_ON,
        ]);
        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry3->id,
            'type' => Relationship::TYPE_DEPENDS_ON,
        ]);

        $this->artisan('knowledge:graph', ['id' => $entry1->id])
            ->expectsOutputToContain('depends_on (2)')
            ->assertSuccessful();
    });

    it('handles complex graphs with multiple levels', function (): void {
        $root = Entry::factory()->create(['title' => 'Root']);
        $level1a = Entry::factory()->create(['title' => 'Level 1A']);
        $level1b = Entry::factory()->create(['title' => 'Level 1B']);
        $level2 = Entry::factory()->create(['title' => 'Level 2']);

        Relationship::factory()->create(['from_entry_id' => $root->id, 'to_entry_id' => $level1a->id]);
        Relationship::factory()->create(['from_entry_id' => $root->id, 'to_entry_id' => $level1b->id]);
        Relationship::factory()->create(['from_entry_id' => $level1a->id, 'to_entry_id' => $level2->id]);

        $this->artisan('knowledge:graph', ['id' => $root->id, '--depth' => 2])
            ->expectsOutputToContain('Nodes: 4')
            ->expectsOutputToContain('Edges: 3')
            ->expectsOutputToContain('Level 1A')
            ->expectsOutputToContain('Level 1B')
            ->expectsOutputToContain('Level 2')
            ->assertSuccessful();
    });

    it('supports multiple type filters', function (): void {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();
        $entry3 = Entry::factory()->create();
        $entry4 = Entry::factory()->create();

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => Relationship::TYPE_DEPENDS_ON,
        ]);
        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry3->id,
            'type' => Relationship::TYPE_EXTENDS,
        ]);
        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry4->id,
            'type' => Relationship::TYPE_RELATES_TO,
        ]);

        $this->artisan('knowledge:graph', [
            'id' => $entry1->id,
            '--type' => [Relationship::TYPE_DEPENDS_ON, Relationship::TYPE_EXTENDS],
        ])
            ->expectsOutputToContain('Nodes: 3') // entry1, entry2, entry3
            ->assertSuccessful();
    });

    it('renders deep graphs with proper tree structure', function (): void {
        $level0 = Entry::factory()->create(['title' => 'Root']);
        $level1 = Entry::factory()->create(['title' => 'Level 1']);
        $level2a = Entry::factory()->create(['title' => 'Level 2A']);
        $level2b = Entry::factory()->create(['title' => 'Level 2B']);

        Relationship::factory()->create(['from_entry_id' => $level0->id, 'to_entry_id' => $level1->id]);
        Relationship::factory()->create(['from_entry_id' => $level1->id, 'to_entry_id' => $level2a->id]);
        Relationship::factory()->create(['from_entry_id' => $level1->id, 'to_entry_id' => $level2b->id]);

        $this->artisan('knowledge:graph', ['id' => $level0->id, '--depth' => 3])
            ->expectsOutputToContain('Root')
            ->expectsOutputToContain('Level 1')
            ->expectsOutputToContain('Level 2A')
            ->expectsOutputToContain('Level 2B')
            ->assertSuccessful();
    });
});
