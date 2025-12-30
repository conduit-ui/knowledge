<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;

describe('knowledge:merge command', function (): void {
    it('merges two entries successfully', function (): void {
        $primary = Entry::factory()->create([
            'title' => 'Primary Entry',
            'content' => 'Primary content',
            'tags' => ['tag1'],
            'files' => ['file1.php'],
            'confidence' => 80,
            'priority' => 'high',
            'usage_count' => 5,
        ]);

        $secondary = Entry::factory()->create([
            'title' => 'Secondary Entry',
            'content' => 'Secondary content',
            'tags' => ['tag2'],
            'files' => ['file2.php'],
            'confidence' => 60,
            'priority' => 'medium',
            'usage_count' => 3,
        ]);

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => $secondary->id,
        ])
            ->expectsOutputToContain('Merging entries')
            ->expectsOutputToContain('Primary Entry')
            ->expectsOutputToContain('Secondary Entry')
            ->expectsOutputToContain('Entries merged successfully')
            ->assertSuccessful();

        $primary->refresh();
        $secondary->refresh();

        // Check merged tags
        expect($primary->tags)->toContain('tag1', 'tag2');

        // Check merged files
        expect($primary->files)->toContain('file1.php', 'file2.php');

        // Check higher confidence is used
        expect($primary->confidence)->toBe(80);

        // Check higher priority is used
        expect($primary->priority)->toBe('high');

        // Check usage counts are combined
        expect($primary->usage_count)->toBe(8);

        // Check content note was added
        expect($primary->content)->toContain("Merged from entry #{$secondary->id}");

        // Check secondary is deprecated
        expect($secondary->status)->toBe('deprecated');
        expect($secondary->confidence)->toBe(0);
    });

    it('creates replaced_by relationship when merging', function (): void {
        $primary = Entry::factory()->create();
        $secondary = Entry::factory()->create();

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => $secondary->id,
        ])->assertSuccessful();

        $relationship = Relationship::where('type', Relationship::TYPE_REPLACED_BY)->first();

        expect($relationship)->not->toBeNull();
        expect($relationship->from_entry_id)->toBe($secondary->id);
        expect($relationship->to_entry_id)->toBe($primary->id);
    });

    it('transfers outgoing relationships from secondary to primary', function (): void {
        $primary = Entry::factory()->create();
        $secondary = Entry::factory()->create();
        $target = Entry::factory()->create();

        // Create outgoing relationship from secondary
        Relationship::factory()->create([
            'from_entry_id' => $secondary->id,
            'to_entry_id' => $target->id,
            'type' => Relationship::TYPE_RELATES_TO,
        ]);

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => $secondary->id,
        ])
            ->expectsOutputToContain('Transferred 1 relationship')
            ->assertSuccessful();

        // Check relationship was transferred
        $transferred = Relationship::where('from_entry_id', $primary->id)
            ->where('to_entry_id', $target->id)
            ->where('type', Relationship::TYPE_RELATES_TO)
            ->first();

        expect($transferred)->not->toBeNull();
    });

    it('transfers incoming relationships from secondary to primary', function (): void {
        $primary = Entry::factory()->create();
        $secondary = Entry::factory()->create();
        $source = Entry::factory()->create();

        // Create incoming relationship to secondary
        Relationship::factory()->create([
            'from_entry_id' => $source->id,
            'to_entry_id' => $secondary->id,
            'type' => Relationship::TYPE_DEPENDS_ON,
        ]);

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => $secondary->id,
        ])
            ->expectsOutputToContain('Transferred 1 relationship')
            ->assertSuccessful();

        // Check relationship was transferred
        $transferred = Relationship::where('from_entry_id', $source->id)
            ->where('to_entry_id', $primary->id)
            ->where('type', Relationship::TYPE_DEPENDS_ON)
            ->first();

        expect($transferred)->not->toBeNull();
    });

    it('skips transferring relationships that already exist', function (): void {
        $primary = Entry::factory()->create();
        $secondary = Entry::factory()->create();
        $target = Entry::factory()->create();

        // Create same relationship from both primary and secondary
        Relationship::factory()->create([
            'from_entry_id' => $primary->id,
            'to_entry_id' => $target->id,
            'type' => Relationship::TYPE_RELATES_TO,
        ]);

        Relationship::factory()->create([
            'from_entry_id' => $secondary->id,
            'to_entry_id' => $target->id,
            'type' => Relationship::TYPE_RELATES_TO,
        ]);

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => $secondary->id,
        ])->assertSuccessful();

        // Should only have 2 relationships: the original and the replaced_by
        $count = Relationship::where('from_entry_id', $primary->id)
            ->where('to_entry_id', $target->id)
            ->where('type', Relationship::TYPE_RELATES_TO)
            ->count();

        expect($count)->toBe(1);
    });

    it('skips transferring relationship when target is the primary entry', function (): void {
        $primary = Entry::factory()->create();
        $secondary = Entry::factory()->create();

        // Create relationship from secondary to primary
        Relationship::factory()->create([
            'from_entry_id' => $secondary->id,
            'to_entry_id' => $primary->id,
            'type' => Relationship::TYPE_RELATES_TO,
        ]);

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => $secondary->id,
        ])->assertSuccessful();

        // Should not transfer self-referencing relationship
        $selfRef = Relationship::where('from_entry_id', $primary->id)
            ->where('to_entry_id', $primary->id)
            ->where('type', Relationship::TYPE_RELATES_TO)
            ->count();

        expect($selfRef)->toBe(0);
    });

    it('supports keep-both flag to link without merging content', function (): void {
        $primary = Entry::factory()->create([
            'title' => 'Primary Entry',
            'content' => 'Primary content',
            'tags' => ['tag1'],
        ]);

        $secondary = Entry::factory()->create([
            'title' => 'Secondary Entry',
            'content' => 'Secondary content',
            'tags' => ['tag2'],
        ]);

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

        // Primary should not be modified
        expect($primary->tags)->toBe(['tag1']);
        expect($primary->content)->toBe('Primary content');

        // Secondary should be deprecated
        expect($secondary->status)->toBe('deprecated');

        // Replaced_by relationship should exist
        $relationship = Relationship::where('type', Relationship::TYPE_REPLACED_BY)->first();
        expect($relationship->from_entry_id)->toBe($secondary->id);
        expect($relationship->to_entry_id)->toBe($primary->id);
    });

    it('fails when primary ID is not a number', function (): void {
        $this->artisan('merge', [
            'primary' => 'not-a-number',
            'secondary' => '1',
        ])
            ->expectsOutputToContain('Primary ID must be a number')
            ->assertFailed();
    });

    it('fails when secondary ID is not a number', function (): void {
        $this->artisan('merge', [
            'primary' => '1',
            'secondary' => 'not-a-number',
        ])
            ->expectsOutputToContain('Secondary ID must be a number')
            ->assertFailed();
    });

    it('fails when trying to merge entry with itself', function (): void {
        $entry = Entry::factory()->create();

        $this->artisan('merge', [
            'primary' => $entry->id,
            'secondary' => $entry->id,
        ])
            ->expectsOutputToContain('Cannot merge an entry with itself')
            ->assertFailed();
    });

    it('fails when primary entry does not exist', function (): void {
        $secondary = Entry::factory()->create();

        $this->artisan('merge', [
            'primary' => 99999,
            'secondary' => $secondary->id,
        ])
            ->expectsOutputToContain('Primary entry not found with ID: 99999')
            ->assertFailed();
    });

    it('fails when secondary entry does not exist', function (): void {
        $primary = Entry::factory()->create();

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => 99999,
        ])
            ->expectsOutputToContain('Secondary entry not found with ID: 99999')
            ->assertFailed();
    });

    it('uses higher confidence from either entry', function (): void {
        $primary = Entry::factory()->create(['confidence' => 60]);
        $secondary = Entry::factory()->create(['confidence' => 90]);

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => $secondary->id,
        ])->assertSuccessful();

        $primary->refresh();
        expect($primary->confidence)->toBe(90);
    });

    it('uses higher priority from either entry', function (): void {
        $primary = Entry::factory()->create(['priority' => 'low']);
        $secondary = Entry::factory()->create(['priority' => 'critical']);

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => $secondary->id,
        ])->assertSuccessful();

        $primary->refresh();
        expect($primary->priority)->toBe('critical');
    });

    it('merges unique tags from both entries', function (): void {
        $primary = Entry::factory()->create(['tags' => ['tag1', 'tag2', 'common']]);
        $secondary = Entry::factory()->create(['tags' => ['tag3', 'common']]);

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => $secondary->id,
        ])->assertSuccessful();

        $primary->refresh();
        expect($primary->tags)->toBe(['tag1', 'tag2', 'common', 'tag3']);
        expect(count($primary->tags))->toBe(4); // No duplicates
    });

    it('merges unique files from both entries', function (): void {
        $primary = Entry::factory()->create(['files' => ['file1.php', 'common.php']]);
        $secondary = Entry::factory()->create(['files' => ['file2.php', 'common.php']]);

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => $secondary->id,
        ])->assertSuccessful();

        $primary->refresh();
        expect($primary->files)->toBe(['file1.php', 'common.php', 'file2.php']);
        expect(count($primary->files))->toBe(3); // No duplicates
    });

    it('preserves relationship metadata when transferring', function (): void {
        $primary = Entry::factory()->create();
        $secondary = Entry::factory()->create();
        $target = Entry::factory()->create();

        $metadata = ['reason' => 'testing', 'confidence' => 95];

        Relationship::factory()->create([
            'from_entry_id' => $secondary->id,
            'to_entry_id' => $target->id,
            'type' => Relationship::TYPE_RELATES_TO,
            'metadata' => $metadata,
        ]);

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => $secondary->id,
        ])->assertSuccessful();

        $transferred = Relationship::where('from_entry_id', $primary->id)
            ->where('to_entry_id', $target->id)
            ->where('type', Relationship::TYPE_RELATES_TO)
            ->first();

        expect($transferred->metadata)->toBe($metadata);
    });

    it('does not transfer replaced_by relationships', function (): void {
        $primary = Entry::factory()->create();
        $secondary = Entry::factory()->create();
        $old = Entry::factory()->create();

        // Secondary already replaced another entry
        Relationship::factory()->create([
            'from_entry_id' => $old->id,
            'to_entry_id' => $secondary->id,
            'type' => Relationship::TYPE_REPLACED_BY,
        ]);

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => $secondary->id,
        ])->assertSuccessful();

        // Should not transfer the old replaced_by relationship
        $transferred = Relationship::where('from_entry_id', $old->id)
            ->where('to_entry_id', $primary->id)
            ->where('type', Relationship::TYPE_REPLACED_BY)
            ->count();

        expect($transferred)->toBe(0);
    });

    it('handles entries with null tags and files gracefully', function (): void {
        $primary = Entry::factory()->create(['tags' => null, 'files' => null]);
        $secondary = Entry::factory()->create(['tags' => null, 'files' => null]);

        $this->artisan('merge', [
            'primary' => $primary->id,
            'secondary' => $secondary->id,
        ])->assertSuccessful();

        $primary->refresh();
        expect($primary->tags)->toBe([]);
        expect($primary->files)->toBe([]);
    });
});
