<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;

describe('Relationship model', function (): void {
    it('can be created with factory', function (): void {
        $relationship = Relationship::factory()->create();

        expect($relationship)->toBeInstanceOf(Relationship::class);
        expect($relationship->id)->toBeInt();
        expect($relationship->type)->toBeString();
    });

    it('casts metadata to array', function (): void {
        $relationship = Relationship::factory()->create([
            'metadata' => ['reason' => 'test', 'strength' => 0.8],
        ]);

        expect($relationship->metadata)->toBeArray();
        expect($relationship->metadata['reason'])->toBe('test');
    });

    it('has fromEntry relationship', function (): void {
        $entry = Entry::factory()->create();
        $relationship = Relationship::factory()->create(['from_entry_id' => $entry->id]);

        expect($relationship->fromEntry->id)->toBe($entry->id);
    });

    it('has toEntry relationship', function (): void {
        $entry = Entry::factory()->create();
        $relationship = Relationship::factory()->create(['to_entry_id' => $entry->id]);

        expect($relationship->toEntry->id)->toBe($entry->id);
    });

    it('provides all valid types', function (): void {
        $types = Relationship::types();

        expect($types)->toContain(Relationship::TYPE_DEPENDS_ON);
        expect($types)->toContain(Relationship::TYPE_RELATES_TO);
        expect($types)->toContain(Relationship::TYPE_CONFLICTS_WITH);
        expect($types)->toContain(Relationship::TYPE_EXTENDS);
        expect($types)->toContain(Relationship::TYPE_IMPLEMENTS);
        expect($types)->toContain(Relationship::TYPE_REFERENCES);
        expect($types)->toContain(Relationship::TYPE_SIMILAR_TO);
        expect($types)->toHaveCount(7);
    });
});
