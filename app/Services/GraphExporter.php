<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Entry;
use App\Models\Relationship;

class GraphExporter
{
    /**
     * Export the knowledge graph in D3.js compatible format.
     *
     * @return array<string, mixed>
     */
    public function exportGraph(): array
    {
        $entries = Entry::all();
        $relationships = Relationship::with(['fromEntry', 'toEntry'])->get();

        $nodes = $this->buildNodes($entries);
        $links = $this->buildLinks($relationships);

        return [
            'nodes' => $nodes,
            'links' => $links,
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'total_nodes' => count($nodes),
                'total_links' => count($links),
            ],
        ];
    }

    /**
     * Build nodes array for the graph.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Entry>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function buildNodes($entries): array
    {
        $nodes = [];

        foreach ($entries as $entry) {
            $nodes[] = [
                'id' => $entry->id,
                'label' => $entry->title,
                'category' => $entry->category,
                'module' => $entry->module,
                'priority' => $entry->priority,
                'confidence' => $entry->confidence,
                'status' => $entry->status,
                'tags' => $entry->tags ?? [],
                'usage_count' => $entry->usage_count,
                'created_at' => $entry->created_at->toIso8601String(),
            ];
        }

        return $nodes;
    }

    /**
     * Build links array for the graph.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Relationship>  $relationships
     * @return array<int, array<string, mixed>>
     */
    private function buildLinks($relationships): array
    {
        $links = [];

        foreach ($relationships as $relationship) {
            $links[] = [
                'source' => $relationship->from_entry_id,
                'target' => $relationship->to_entry_id,
                'type' => $relationship->type,
                'metadata' => $relationship->metadata ?? [],
            ];
        }

        return $links;
    }

    /**
     * Export the graph in Cytoscape.js format.
     *
     * @return array<string, mixed>
     */
    public function exportCytoscapeGraph(): array
    {
        $entries = Entry::all();
        $relationships = Relationship::all();

        $elements = [];

        // Add nodes
        foreach ($entries as $entry) {
            $elements[] = [
                'group' => 'nodes',
                'data' => [
                    'id' => (string) $entry->id,
                    'label' => $entry->title,
                    'category' => $entry->category,
                    'priority' => $entry->priority,
                    'confidence' => $entry->confidence,
                ],
            ];
        }

        // Add edges
        foreach ($relationships as $relationship) {
            $elements[] = [
                'group' => 'edges',
                'data' => [
                    'id' => "e{$relationship->id}",
                    'source' => (string) $relationship->from_entry_id,
                    'target' => (string) $relationship->to_entry_id,
                    'label' => $relationship->type,
                    'type' => $relationship->type,
                ],
            ];
        }

        return [
            'elements' => $elements,
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'format' => 'cytoscape',
            ],
        ];
    }

    /**
     * Export the graph in Graphviz DOT format.
     */
    public function exportDotGraph(): string
    {
        $entries = Entry::all();
        $relationships = Relationship::all();

        $dot = "digraph Knowledge {\n";
        $dot .= "  rankdir=LR;\n";
        $dot .= "  node [shape=box, style=rounded];\n\n";

        // Add nodes
        foreach ($entries as $entry) {
            $label = $this->escapeDot($entry->title);
            $color = $this->getNodeColor($entry->priority);
            $dot .= "  n{$entry->id} [label=\"{$label}\", color=\"{$color}\"];\n";
        }

        $dot .= "\n";

        // Add edges
        foreach ($relationships as $relationship) {
            $style = $this->getEdgeStyle($relationship->type);
            $dot .= "  n{$relationship->from_entry_id} -> n{$relationship->to_entry_id}";
            $dot .= " [label=\"{$relationship->type}\", {$style}];\n";
        }

        $dot .= "}\n";

        return $dot;
    }

    /**
     * Escape string for DOT format.
     */
    private function escapeDot(string $text): string
    {
        return str_replace('"', '\\"', $text);
    }

    /**
     * Get node color based on priority.
     */
    private function getNodeColor(string $priority): string
    {
        return match ($priority) {
            'high' => '#e74c3c',
            'medium' => '#f39c12',
            'low' => '#3498db',
            default => '#95a5a6',
        };
    }

    /**
     * Get edge style based on relationship type.
     */
    private function getEdgeStyle(string $type): string
    {
        return match ($type) {
            'depends_on' => 'style=solid, color="#e74c3c"',
            'relates_to' => 'style=dashed, color="#3498db"',
            'conflicts_with' => 'style=dotted, color="#e74c3c"',
            'extends' => 'style=solid, color="#2ecc71"',
            'implements' => 'style=solid, color="#9b59b6"',
            'references' => 'style=dashed, color="#95a5a6"',
            'similar_to' => 'style=dotted, color="#3498db"',
            default => 'style=solid, color="#95a5a6"',
        };
    }
}
