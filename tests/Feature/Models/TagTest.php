<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Tag;

describe('Tag model', function (): void {
    it('can be created with factory', function (): void {
        $tag = Tag::factory()->create();

        expect($tag)->toBeInstanceOf(Tag::class);
        expect($tag->id)->toBeInt();
        expect($tag->name)->toBeString();
    });

    it('casts usage_count to integer', function (): void {
        $tag = Tag::factory()->create(['usage_count' => 42]);

        expect($tag->usage_count)->toBeInt();
        expect($tag->usage_count)->toBe(42);
    });

    it('has entries relationship', function (): void {
        $tag = Tag::factory()->create();
        $entry = Entry::factory()->create();

        $entry->normalizedTags()->attach($tag);

        expect($tag->entries)->toHaveCount(1);
        expect($tag->entries->first()->id)->toBe($entry->id);
    });
});
