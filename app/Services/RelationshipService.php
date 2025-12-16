<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entry;
use App\Models\Relationship;
use Illuminate\Support\Collection;

/**
 * Service for managing entry relationships and graph traversal.
 */
class RelationshipService
{
    /**
     * Create a relationship between two entries.
     *
     * @param  int  $fromId  The source entry ID
     * @param  int  $toId  The target entry ID
     * @param  string  $type  The relationship type
     * @param  array<string, mixed>|null  $metadata  Optional metadata
     * @return Relationship The created relationship
     *
     * @throws \InvalidArgumentException If type is invalid or entry doesn't exist
     * @throws \RuntimeException If circular dependency detected
     */
    public function createRelationship(int $fromId, int $toId, string $type, ?array $metadata = null): Relationship
    {
        // Validate relationship type
        if (! in_array($type, Relationship::types(), true)) {
            throw new \InvalidArgumentException("Invalid relationship type: {$type}");
        }

        // Validate entries exist
        /** @var Entry|null $fromEntry */
        $fromEntry = Entry::find($fromId);
        if ($fromEntry === null) {
            throw new \InvalidArgumentException("Entry {$fromId} not found");
        }

        /** @var Entry|null $toEntry */
        $toEntry = Entry::find($toId);
        if ($toEntry === null) {
            throw new \InvalidArgumentException("Entry {$toId} not found");
        }

        // Prevent self-references
        if ($fromId === $toId) {
            throw new \InvalidArgumentException('Cannot create relationship to self');
        }

        // Check for circular dependencies only for depends_on type
        if ($type === Relationship::TYPE_DEPENDS_ON && $this->wouldCreateCircularDependency($fromId, $toId)) {
            throw new \RuntimeException('This relationship would create a circular dependency');
        }

        // Create or update the relationship
        return Relationship::updateOrCreate(
            [
                'from_entry_id' => $fromId,
                'to_entry_id' => $toId,
                'type' => $type,
            ],
            [
                'metadata' => $metadata,
            ]
        );
    }

    /**
     * Create a bidirectional relationship between two entries.
     *
     * @param  int  $entryId1  First entry ID
     * @param  int  $entryId2  Second entry ID
     * @param  string  $type  The relationship type
     * @param  array<string, mixed>|null  $metadata  Optional metadata
     * @return array{0: Relationship, 1: Relationship} Both relationships
     */
    public function createBidirectionalRelationship(int $entryId1, int $entryId2, string $type, ?array $metadata = null): array
    {
        $rel1 = $this->createRelationship($entryId1, $entryId2, $type, $metadata);
        $rel2 = $this->createRelationship($entryId2, $entryId1, $type, $metadata);

        return [$rel1, $rel2];
    }

    /**
     * Delete a relationship by ID.
     *
     * @param  int  $relationshipId  The relationship ID to delete
     * @return bool True if deleted, false if not found
     */
    public function deleteRelationship(int $relationshipId): bool
    {
        /** @var Relationship|null $relationship */
        $relationship = Relationship::find($relationshipId);

        if ($relationship === null) {
            return false;
        }

        return (bool) $relationship->delete();
    }

    /**
     * Get all relationships for an entry (both incoming and outgoing).
     *
     * @param  int  $entryId  The entry ID
     * @return Collection<int, Relationship> Collection of relationships
     */
    public function getRelationships(int $entryId): Collection
    {
        return Relationship::query()
            ->where('from_entry_id', $entryId)
            ->orWhere('to_entry_id', $entryId)
            ->with(['fromEntry', 'toEntry'])
            ->get();
    }

    /**
     * Get relationships grouped by direction and type.
     *
     * @param  int  $entryId  The entry ID
     * @return array{outgoing: array<string, Collection<int, Relationship>>, incoming: array<string, Collection<int, Relationship>>}
     */
    public function getGroupedRelationships(int $entryId): array
    {
        $outgoing = Relationship::query()
            ->where('from_entry_id', $entryId)
            ->with('toEntry')
            ->get()
            ->groupBy('type');

        $incoming = Relationship::query()
            ->where('to_entry_id', $entryId)
            ->with('fromEntry')
            ->get()
            ->groupBy('type');

        return [
            'outgoing' => $outgoing->all(),
            'incoming' => $incoming->all(),
        ];
    }

    /**
     * Traverse the relationship graph starting from an entry.
     *
     * @param  int  $entryId  Starting entry ID
     * @param  int  $maxDepth  Maximum traversal depth (default 2)
     * @param  array<string>|null  $types  Filter by relationship types
     * @return array{nodes: array<int, array{id: int, entry: Entry, depth: int}>, edges: Collection<int, Relationship>}
     */
    public function traverseGraph(int $entryId, int $maxDepth = 2, ?array $types = null): array
    {
        $visited = [];
        $nodes = [];
        $edges = new Collection;

        $this->traverseGraphRecursive($entryId, 0, $maxDepth, $types, $visited, $nodes, $edges);

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * Check if creating a relationship would create a circular dependency.
     *
     * @param  int  $fromId  Source entry ID
     * @param  int  $toId  Target entry ID
     * @return bool True if circular dependency would be created
     */
    public function wouldCreateCircularDependency(int $fromId, int $toId): bool
    {
        // Check if toId depends on fromId (directly or indirectly)
        return $this->hasDependencyPath($toId, $fromId);
    }

    /**
     * Check if there's a dependency path from start to end.
     *
     * @param  int  $startId  Starting entry ID
     * @param  int  $endId  Target entry ID
     * @param  array<int, bool>  $visited  Visited entries
     * @return bool True if path exists
     */
    protected function hasDependencyPath(int $startId, int $endId, array &$visited = []): bool
    {
        if ($startId === $endId) {
            return true;
        }

        if (isset($visited[$startId])) {
            return false;
        }

        $visited[$startId] = true;

        $dependencies = Relationship::query()
            ->where('from_entry_id', $startId)
            ->where('type', Relationship::TYPE_DEPENDS_ON)
            ->pluck('to_entry_id');

        foreach ($dependencies as $dependencyId) {
            if ($this->hasDependencyPath($dependencyId, $endId, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively traverse the relationship graph.
     *
     * @param  int  $entryId  Current entry ID
     * @param  int  $currentDepth  Current depth
     * @param  int  $maxDepth  Maximum depth
     * @param  array<string>|null  $types  Filter by types
     * @param  array<int, bool>  $visited  Visited entries
     * @param  array<int, array{id: int, entry: Entry, depth: int}>  $nodes  Collected nodes
     * @param  Collection<int, Relationship>  $edges  Collected edges
     */
    protected function traverseGraphRecursive(
        int $entryId,
        int $currentDepth,
        int $maxDepth,
        ?array $types,
        array &$visited,
        array &$nodes,
        Collection &$edges
    ): void {
        if ($currentDepth > $maxDepth || isset($visited[$entryId])) {
            return;
        }

        $visited[$entryId] = true;

        /** @var Entry|null $entry */
        $entry = Entry::find($entryId);
        if ($entry === null) {
            return;
        }

        $nodes[$entryId] = [
            'id' => $entryId,
            'entry' => $entry,
            'depth' => $currentDepth,
        ];

        // Get outgoing relationships
        /** @var \Illuminate\Database\Eloquent\Builder<Relationship> $query */
        $query = Relationship::query()
            ->where('from_entry_id', $entryId)
            ->with('toEntry');

        if ($types !== null) {
            $query->whereIn('type', $types);
        }

        $relationships = $query->get();

        foreach ($relationships as $relationship) {
            // Only add edge if we haven't visited the target node yet
            // AND we're not exceeding max depth
            if (! isset($visited[$relationship->to_entry_id]) && $currentDepth + 1 <= $maxDepth) {
                $edges->push($relationship);
            }

            $this->traverseGraphRecursive(
                $relationship->to_entry_id,
                $currentDepth + 1,
                $maxDepth,
                $types,
                $visited,
                $nodes,
                $edges
            );
        }
    }

    /**
     * Suggest related entries based on existing relationships.
     *
     * @param  int  $entryId  Entry ID
     * @param  int  $limit  Maximum number of suggestions
     * @return Collection<int, array{entry: Entry, score: float, reason: string}> Suggested entries
     */
    public function suggestRelatedEntries(int $entryId, int $limit = 5): Collection
    {
        // Get entries that are related to entries related to this one
        $directlyRelated = Relationship::query()
            ->where('from_entry_id', $entryId)
            ->pluck('to_entry_id')
            ->toArray();

        $suggestions = new Collection;

        // Find entries that share relationships with directly related entries
        /** @var \Illuminate\Database\Eloquent\Builder<Relationship> $indirectQuery */
        $indirectQuery = Relationship::query()
            ->whereIn('from_entry_id', $directlyRelated)
            ->where('to_entry_id', '!=', $entryId)
            ->whereNotIn('to_entry_id', $directlyRelated)
            ->with('toEntry');

        $indirectlyRelated = $indirectQuery->get()->groupBy('to_entry_id');

        foreach ($indirectlyRelated as $targetId => $relationships) {
            $firstRel = $relationships->first();
            if ($firstRel === null || $firstRel->toEntry === null) {
                continue;
            }

            $suggestions->push([
                'entry' => $firstRel->toEntry,
                'score' => count($relationships) * 0.5, // Simple scoring
                'reason' => 'Connected through '.count($relationships).' shared relationships',
            ]);
        }

        return $suggestions
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }
}
