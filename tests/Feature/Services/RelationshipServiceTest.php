<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;
use App\Services\RelationshipService;

describe('RelationshipService', function (): void {
    beforeEach(function (): void {
        $this->service = new RelationshipService;
    });

    describe('createRelationship', function (): void {
        it('creates a relationship between two entries', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();

            $relationship = $this->service->createRelationship(
                $entry1->id,
                $entry2->id,
                Relationship::TYPE_RELATES_TO
            );

            expect($relationship)->toBeInstanceOf(Relationship::class);
            expect($relationship->from_entry_id)->toBe($entry1->id);
            expect($relationship->to_entry_id)->toBe($entry2->id);
            expect($relationship->type)->toBe(Relationship::TYPE_RELATES_TO);
        });

        it('creates a relationship with metadata', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();
            $metadata = ['reason' => 'testing', 'strength' => 0.9];

            $relationship = $this->service->createRelationship(
                $entry1->id,
                $entry2->id,
                Relationship::TYPE_REFERENCES,
                $metadata
            );

            expect($relationship->metadata)->toBe($metadata);
        });

        it('updates existing relationship if one exists', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();

            $rel1 = $this->service->createRelationship(
                $entry1->id,
                $entry2->id,
                Relationship::TYPE_RELATES_TO,
                ['version' => 1]
            );

            $rel2 = $this->service->createRelationship(
                $entry1->id,
                $entry2->id,
                Relationship::TYPE_RELATES_TO,
                ['version' => 2]
            );

            expect($rel1->id)->toBe($rel2->id);
            expect($rel2->metadata)->toBe(['version' => 2]);
            expect(Relationship::count())->toBe(1);
        });

        it('throws exception for invalid type', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();

            $this->service->createRelationship($entry1->id, $entry2->id, 'invalid_type');
        })->throws(\InvalidArgumentException::class, 'Invalid relationship type: invalid_type');

        it('throws exception when from entry does not exist', function (): void {
            $entry = Entry::factory()->create();

            $this->service->createRelationship(99999, $entry->id, Relationship::TYPE_RELATES_TO);
        })->throws(\InvalidArgumentException::class, 'Entry 99999 not found');

        it('throws exception when to entry does not exist', function (): void {
            $entry = Entry::factory()->create();

            $this->service->createRelationship($entry->id, 99999, Relationship::TYPE_RELATES_TO);
        })->throws(\InvalidArgumentException::class, 'Entry 99999 not found');

        it('throws exception for self-reference', function (): void {
            $entry = Entry::factory()->create();

            $this->service->createRelationship($entry->id, $entry->id, Relationship::TYPE_RELATES_TO);
        })->throws(\InvalidArgumentException::class, 'Cannot create relationship to self');

        it('detects circular dependencies for depends_on type', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();
            $entry3 = Entry::factory()->create();

            // Create chain: 1 -> 2 -> 3
            $this->service->createRelationship($entry1->id, $entry2->id, Relationship::TYPE_DEPENDS_ON);
            $this->service->createRelationship($entry2->id, $entry3->id, Relationship::TYPE_DEPENDS_ON);

            // Try to create: 3 -> 1 (would create cycle)
            $this->service->createRelationship($entry3->id, $entry1->id, Relationship::TYPE_DEPENDS_ON);
        })->throws(\RuntimeException::class, 'circular dependency');

        it('allows circular relationships for non-depends_on types', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();

            $this->service->createRelationship($entry1->id, $entry2->id, Relationship::TYPE_RELATES_TO);
            $rel = $this->service->createRelationship($entry2->id, $entry1->id, Relationship::TYPE_RELATES_TO);

            expect($rel)->toBeInstanceOf(Relationship::class);
        });
    });

    describe('createBidirectionalRelationship', function (): void {
        it('creates two relationships', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();

            [$rel1, $rel2] = $this->service->createBidirectionalRelationship(
                $entry1->id,
                $entry2->id,
                Relationship::TYPE_SIMILAR_TO
            );

            expect($rel1->from_entry_id)->toBe($entry1->id);
            expect($rel1->to_entry_id)->toBe($entry2->id);
            expect($rel2->from_entry_id)->toBe($entry2->id);
            expect($rel2->to_entry_id)->toBe($entry1->id);
            expect(Relationship::count())->toBe(2);
        });

        it('creates bidirectional relationships with metadata', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();
            $metadata = ['similarity' => 0.85];

            [$rel1, $rel2] = $this->service->createBidirectionalRelationship(
                $entry1->id,
                $entry2->id,
                Relationship::TYPE_SIMILAR_TO,
                $metadata
            );

            expect($rel1->metadata)->toBe($metadata);
            expect($rel2->metadata)->toBe($metadata);
        });
    });

    describe('deleteRelationship', function (): void {
        it('deletes a relationship', function (): void {
            $relationship = Relationship::factory()->create();

            $result = $this->service->deleteRelationship($relationship->id);

            expect($result)->toBeTrue();
            expect(Relationship::find($relationship->id))->toBeNull();
        });

        it('returns false when relationship does not exist', function (): void {
            $result = $this->service->deleteRelationship(99999);

            expect($result)->toBeFalse();
        });
    });

    describe('getRelationships', function (): void {
        it('returns all relationships for an entry', function (): void {
            $entry = Entry::factory()->create();
            $other1 = Entry::factory()->create();
            $other2 = Entry::factory()->create();

            Relationship::factory()->create(['from_entry_id' => $entry->id, 'to_entry_id' => $other1->id]);
            Relationship::factory()->create(['from_entry_id' => $other2->id, 'to_entry_id' => $entry->id]);
            Relationship::factory()->create(); // Unrelated

            $relationships = $this->service->getRelationships($entry->id);

            expect($relationships)->toHaveCount(2);
        });

        it('returns empty collection when entry has no relationships', function (): void {
            $entry = Entry::factory()->create();

            $relationships = $this->service->getRelationships($entry->id);

            expect($relationships)->toBeEmpty();
        });
    });

    describe('getGroupedRelationships', function (): void {
        it('groups relationships by direction and type', function (): void {
            $entry = Entry::factory()->create();
            $other1 = Entry::factory()->create();
            $other2 = Entry::factory()->create();
            $other3 = Entry::factory()->create();

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
            Relationship::factory()->create([
                'from_entry_id' => $other3->id,
                'to_entry_id' => $entry->id,
                'type' => Relationship::TYPE_REFERENCES,
            ]);

            $grouped = $this->service->getGroupedRelationships($entry->id);

            expect($grouped)->toHaveKeys(['outgoing', 'incoming']);
            expect($grouped['outgoing'])->toHaveKeys([Relationship::TYPE_DEPENDS_ON, Relationship::TYPE_RELATES_TO]);
            expect($grouped['incoming'])->toHaveKey(Relationship::TYPE_REFERENCES);
        });

        it('returns empty arrays when entry has no relationships', function (): void {
            $entry = Entry::factory()->create();

            $grouped = $this->service->getGroupedRelationships($entry->id);

            expect($grouped['outgoing'])->toBeEmpty();
            expect($grouped['incoming'])->toBeEmpty();
        });
    });

    describe('traverseGraph', function (): void {
        it('traverses relationship graph to specified depth', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();
            $entry3 = Entry::factory()->create();

            Relationship::factory()->create(['from_entry_id' => $entry1->id, 'to_entry_id' => $entry2->id]);
            Relationship::factory()->create(['from_entry_id' => $entry2->id, 'to_entry_id' => $entry3->id]);

            $graph = $this->service->traverseGraph($entry1->id, 2);

            expect($graph['nodes'])->toHaveCount(3);
            expect($graph['edges'])->toHaveCount(2);
        });

        it('respects maximum depth limit', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();
            $entry3 = Entry::factory()->create();

            Relationship::factory()->create(['from_entry_id' => $entry1->id, 'to_entry_id' => $entry2->id]);
            Relationship::factory()->create(['from_entry_id' => $entry2->id, 'to_entry_id' => $entry3->id]);

            $graph = $this->service->traverseGraph($entry1->id, 1);

            expect($graph['nodes'])->toHaveCount(2);
            expect($graph['edges'])->toHaveCount(1);
        });

        it('filters by relationship types', function (): void {
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
                'type' => Relationship::TYPE_RELATES_TO,
            ]);

            $graph = $this->service->traverseGraph($entry1->id, 2, [Relationship::TYPE_DEPENDS_ON]);

            expect($graph['nodes'])->toHaveCount(2);
            expect($graph['edges'])->toHaveCount(1);
        });

        it('handles circular graphs without infinite loops', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();

            Relationship::factory()->create([
                'from_entry_id' => $entry1->id,
                'to_entry_id' => $entry2->id,
                'type' => Relationship::TYPE_RELATES_TO,
            ]);
            Relationship::factory()->create([
                'from_entry_id' => $entry2->id,
                'to_entry_id' => $entry1->id,
                'type' => Relationship::TYPE_RELATES_TO,
            ]);

            $graph = $this->service->traverseGraph($entry1->id, 5);

            expect($graph['nodes'])->toHaveCount(2);
            expect($graph['edges'])->toHaveCount(1); // Only first direction traversed
        });

        it('returns correct depth for each node', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();
            $entry3 = Entry::factory()->create();

            Relationship::factory()->create(['from_entry_id' => $entry1->id, 'to_entry_id' => $entry2->id]);
            Relationship::factory()->create(['from_entry_id' => $entry2->id, 'to_entry_id' => $entry3->id]);

            $graph = $this->service->traverseGraph($entry1->id, 2);

            expect($graph['nodes'][$entry1->id]['depth'])->toBe(0);
            expect($graph['nodes'][$entry2->id]['depth'])->toBe(1);
            expect($graph['nodes'][$entry3->id]['depth'])->toBe(2);
        });
    });

    describe('wouldCreateCircularDependency', function (): void {
        it('detects direct circular dependency', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();

            $this->service->createRelationship($entry1->id, $entry2->id, Relationship::TYPE_DEPENDS_ON);

            $result = $this->service->wouldCreateCircularDependency($entry2->id, $entry1->id);

            expect($result)->toBeTrue();
        });

        it('detects indirect circular dependency', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();
            $entry3 = Entry::factory()->create();

            $this->service->createRelationship($entry1->id, $entry2->id, Relationship::TYPE_DEPENDS_ON);
            $this->service->createRelationship($entry2->id, $entry3->id, Relationship::TYPE_DEPENDS_ON);

            $result = $this->service->wouldCreateCircularDependency($entry3->id, $entry1->id);

            expect($result)->toBeTrue();
        });

        it('returns false when no circular dependency exists', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();
            $entry3 = Entry::factory()->create();

            $this->service->createRelationship($entry1->id, $entry2->id, Relationship::TYPE_DEPENDS_ON);

            $result = $this->service->wouldCreateCircularDependency($entry3->id, $entry2->id);

            expect($result)->toBeFalse();
        });
    });

    describe('suggestRelatedEntries', function (): void {
        it('suggests entries connected through shared relationships', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();
            $entry3 = Entry::factory()->create();

            // 1 -> 2 -> 3
            Relationship::factory()->create(['from_entry_id' => $entry1->id, 'to_entry_id' => $entry2->id]);
            Relationship::factory()->create(['from_entry_id' => $entry2->id, 'to_entry_id' => $entry3->id]);

            $suggestions = $this->service->suggestRelatedEntries($entry1->id);

            expect($suggestions)->not->toBeEmpty();
            expect($suggestions->pluck('entry.id'))->toContain($entry3->id);
        });

        it('respects the limit parameter', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();

            // Create link from entry1 to entry2 (only once)
            Relationship::factory()->create([
                'from_entry_id' => $entry1->id,
                'to_entry_id' => $entry2->id,
                'type' => Relationship::TYPE_RELATES_TO,
            ]);

            // Create multiple entries linked from entry2
            for ($i = 0; $i < 10; $i++) {
                $entry = Entry::factory()->create();
                Relationship::factory()->create([
                    'from_entry_id' => $entry2->id,
                    'to_entry_id' => $entry->id,
                    'type' => Relationship::TYPE_RELATES_TO,
                ]);
            }

            $suggestions = $this->service->suggestRelatedEntries($entry1->id, 3);

            expect($suggestions)->toHaveCount(3);
        });

        it('returns empty collection when no suggestions available', function (): void {
            $entry = Entry::factory()->create();

            $suggestions = $this->service->suggestRelatedEntries($entry->id);

            expect($suggestions)->toBeEmpty();
        });

        it('excludes directly related entries', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();
            $entry3 = Entry::factory()->create();

            Relationship::factory()->create(['from_entry_id' => $entry1->id, 'to_entry_id' => $entry2->id]);
            Relationship::factory()->create(['from_entry_id' => $entry2->id, 'to_entry_id' => $entry3->id]);

            $suggestions = $this->service->suggestRelatedEntries($entry1->id);

            expect($suggestions->pluck('entry.id'))->not->toContain($entry2->id);
        });

        it('includes score and reason', function (): void {
            $entry1 = Entry::factory()->create();
            $entry2 = Entry::factory()->create();
            $entry3 = Entry::factory()->create();

            Relationship::factory()->create(['from_entry_id' => $entry1->id, 'to_entry_id' => $entry2->id]);
            Relationship::factory()->create(['from_entry_id' => $entry2->id, 'to_entry_id' => $entry3->id]);

            $suggestions = $this->service->suggestRelatedEntries($entry1->id);

            expect($suggestions->first())->toHaveKeys(['entry', 'score', 'reason']);
            expect($suggestions->first()['score'])->toBeFloat();
            expect($suggestions->first()['reason'])->toBeString();
        });
    });
});
