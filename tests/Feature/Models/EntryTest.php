<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\Entry;
use App\Models\Relationship;
use App\Models\Tag;

describe('Entry model', function (): void {
    it('can be created with factory', function (): void {
        $entry = Entry::factory()->create();

        expect($entry)->toBeInstanceOf(Entry::class);
        expect($entry->id)->toBeInt();
        expect($entry->title)->toBeString();
        expect($entry->content)->toBeString();
    });

    it('casts tags to array', function (): void {
        $entry = Entry::factory()->create(['tags' => ['php', 'laravel']]);

        expect($entry->tags)->toBeArray();
        expect($entry->tags)->toContain('php', 'laravel');
    });

    it('casts files to array', function (): void {
        $entry = Entry::factory()->create(['files' => ['app/Models/User.php']]);

        expect($entry->files)->toBeArray();
        expect($entry->files)->toContain('app/Models/User.php');
    });

    it('casts confidence to integer', function (): void {
        $entry = Entry::factory()->create(['confidence' => 85]);

        expect($entry->confidence)->toBeInt();
        expect($entry->confidence)->toBe(85);
    });

    it('casts dates properly', function (): void {
        $entry = Entry::factory()->create([
            'last_used' => now(),
            'validation_date' => now(),
        ]);

        expect($entry->last_used)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($entry->validation_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('has normalized tags relationship', function (): void {
        $entry = Entry::factory()->create();
        $tag = Tag::factory()->create();

        $entry->normalizedTags()->attach($tag);

        expect($entry->normalizedTags)->toHaveCount(1);
        expect($entry->normalizedTags->first()->id)->toBe($tag->id);
    });

    it('has collections relationship', function (): void {
        $entry = Entry::factory()->create();
        $collection = Collection::factory()->create();

        $collection->entries()->attach($entry, ['sort_order' => 0]);

        expect($entry->collections)->toHaveCount(1);
        expect($entry->collections->first()->id)->toBe($collection->id);
    });

    it('has outgoing relationships', function (): void {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => Relationship::TYPE_RELATES_TO,
        ]);

        expect($entry1->outgoingRelationships)->toHaveCount(1);
    });

    it('has incoming relationships', function (): void {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => Relationship::TYPE_DEPENDS_ON,
        ]);

        expect($entry2->incomingRelationships)->toHaveCount(1);
    });

    it('can increment usage', function (): void {
        $entry = Entry::factory()->create(['usage_count' => 5, 'last_used' => null]);

        $entry->incrementUsage();

        expect($entry->fresh()->usage_count)->toBe(6);
        expect($entry->fresh()->last_used)->not->toBeNull();
    });

    it('can be created as validated', function (): void {
        $entry = Entry::factory()->validated()->create();

        expect($entry->status)->toBe('validated');
        expect($entry->confidence)->toBeGreaterThanOrEqual(80);
        expect($entry->validation_date)->not->toBeNull();
    });

    it('can be created as draft', function (): void {
        $entry = Entry::factory()->draft()->create();

        expect($entry->status)->toBe('draft');
        expect($entry->validation_date)->toBeNull();
    });

    it('can be created as critical', function (): void {
        $entry = Entry::factory()->critical()->create();

        expect($entry->priority)->toBe('critical');
        expect($entry->confidence)->toBeGreaterThanOrEqual(90);
    });
});
