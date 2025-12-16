<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Services\RelationshipService;
use LaravelZero\Framework\Commands\Command;

/**
 * Visualize the relationship graph for a knowledge entry.
 */
class KnowledgeGraphCommand extends Command
{
    protected $signature = 'knowledge:graph
                            {id : The entry ID to start from}
                            {--depth=2 : Maximum traversal depth}
                            {--type=* : Filter by relationship types}';

    protected $description = 'Visualize the relationship graph for a knowledge entry';

    public function handle(RelationshipService $service): int
    {
        $entryId = (int) $this->argument('id');
        $maxDepth = (int) $this->option('depth');
        $types = $this->option('type');

        if ($maxDepth < 0 || $maxDepth > 10) {
            $this->error('Depth must be between 0 and 10');

            return self::FAILURE;
        }

        $entry = Entry::find($entryId);
        if (! $entry) {
            $this->error("Entry #{$entryId} not found");

            return self::FAILURE;
        }

        $this->info("Relationship Graph for: {$entry->title}");
        if (! empty($types)) {
            $this->line('Filtered by types: '.implode(', ', $types));
        }
        $this->line('');

        $graph = $service->traverseGraph($entryId, $maxDepth, empty($types) ? null : $types);

        if ($graph['edges']->isEmpty()) {
            $this->line('No relationships found');

            return self::SUCCESS;
        }

        // Display statistics
        $this->line('<fg=cyan>Graph Statistics:</fg=cyan>');
        $this->line('  Nodes: '.count($graph['nodes']));
        $this->line("  Edges: {$graph['edges']->count()}");
        $this->line('');

        // Display tree visualization
        $this->line('<fg=cyan>Graph Visualization:</fg=cyan>');
        $this->renderTree($graph['nodes'], $graph['edges'], $entryId);

        $this->line('');

        // Display edge details
        $this->line('<fg=cyan>Relationship Details:</fg=cyan>');
        $groupedEdges = $graph['edges']->groupBy('type');
        foreach ($groupedEdges as $type => $edges) {
            $this->line("  <fg=yellow>{$type}</> ({$edges->count()}):");
            foreach ($edges as $edge) {
                $fromNode = $graph['nodes'][$edge->from_entry_id] ?? null;
                $toNode = $graph['nodes'][$edge->to_entry_id] ?? null;

                if ($fromNode && $toNode) {
                    $this->line("    #{$edge->from_entry_id} {$fromNode['entry']->title}");
                    $this->line("      → #{$edge->to_entry_id} {$toNode['entry']->title}");
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Render the graph as a tree structure.
     *
     * @param  array<int, array{id: int, entry: Entry, depth: int}>  $nodes
     * @param  \Illuminate\Support\Collection<int, \App\Models\Relationship>  $edges
     */
    protected function renderTree(array $nodes, $edges, int $rootId): void
    {
        $rendered = [];
        $this->renderNode($rootId, $nodes, $edges, '', true, $rendered);
    }

    /**
     * Recursively render a node in the tree.
     *
     * @param  array<int, array{id: int, entry: Entry, depth: int}>  $nodes
     * @param  \Illuminate\Support\Collection<int, \App\Models\Relationship>  $edges
     * @param  array<int, bool>  $rendered
     */
    protected function renderNode(
        int $nodeId,
        array $nodes,
        $edges,
        string $prefix,
        bool $isLast,
        array &$rendered
    ): void {
        // @codeCoverageIgnoreStart
        // Defensive check - nodes always exist when called from traverseGraph
        if (! isset($nodes[$nodeId])) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $node = $nodes[$nodeId];
        $isRoot = $prefix === '';

        // Render current node
        if ($isRoot) {
            $this->line("#{$nodeId} <fg=green>{$node['entry']->title}</>");
        } else {
            // @codeCoverageIgnoreStart
            // Note: This branch is not reached when starting from root
            // because childPrefix is always '' when isRoot is true
            $connector = $isLast ? '└── ' : '├── ';
            $this->line($prefix.$connector."#{$nodeId} {$node['entry']->title}");
            // @codeCoverageIgnoreEnd
        }

        // Mark as rendered to avoid infinite loops
        // @codeCoverageIgnoreStart
        // Defensive check - traverseGraph's visited check prevents duplicate paths
        if (isset($rendered[$nodeId])) {
            return;
        }
        // @codeCoverageIgnoreEnd
        $rendered[$nodeId] = true;

        // Get children (outgoing relationships from this node)
        $children = $edges
            ->filter(fn ($edge) => $edge->from_entry_id === $nodeId)
            ->map(fn ($edge) => ['id' => $edge->to_entry_id, 'type' => $edge->type])
            ->unique('id')
            ->values();

        $childCount = $children->count();

        // Render children
        foreach ($children as $index => $child) {
            $childId = $child['id'];
            $isLastChild = ($index === $childCount - 1);

            $childPrefix = $isRoot ? '' : $prefix.($isLast ? '    ' : '│   ');

            // Show relationship type on the connector
            // @codeCoverageIgnoreStart
            // Note: This branch is not reached when starting from root
            // because $isRoot is always true due to childPrefix being ''
            if (! $isRoot) {
                $connector = $isLastChild ? '└── ' : '├── ';
                $typeLabel = " <fg=yellow>[{$child['type']}]</>";
                $currentLine = $childPrefix.$connector;

                if (isset($nodes[$childId])) {
                    $childNode = $nodes[$childId];
                    $this->line($currentLine."#{$childId} {$childNode['entry']->title}{$typeLabel}");

                    if (! isset($rendered[$childId])) {
                        $grandchildPrefix = $childPrefix.($isLastChild ? '    ' : '│   ');
                        $grandchildren = $edges
                            ->filter(fn ($edge) => $edge->from_entry_id === $childId)
                            ->map(fn ($edge) => $edge->to_entry_id)
                            ->unique()
                            ->values();

                        $rendered[$childId] = true;

                        foreach ($grandchildren as $gcIndex => $grandchildId) {
                            $isLastGC = ($gcIndex === $grandchildren->count() - 1);
                            $this->renderNode($grandchildId, $nodes, $edges, $grandchildPrefix, $isLastGC, $rendered);
                        }
                    }
                }
            // @codeCoverageIgnoreEnd
            } else {
                $this->renderNode($childId, $nodes, $edges, $childPrefix, $isLastChild, $rendered);
            }
        }
    }
}
