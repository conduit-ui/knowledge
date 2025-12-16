<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;
use App\Services\GraphExporter;

beforeEach(function () {
    Entry::query()->delete();
    Relationship::query()->delete();
});

describe('GraphExporter', function () {
    it('exports graph with nodes and links', function () {
        $entry1 = Entry::factory()->create(['title' => 'Node 1']);
        $entry2 = Entry::factory()->create(['title' => 'Node 2']);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => 'depends_on',
        ]);

        $exporter = new GraphExporter;
        $graph = $exporter->exportGraph();

        expect($graph)->toHaveKey('nodes');
        expect($graph)->toHaveKey('links');
        expect($graph)->toHaveKey('metadata');
        expect(count($graph['nodes']))->toBe(2);
        expect(count($graph['links']))->toBe(1);
    });

    it('includes complete node data', function () {
        $entry = Entry::factory()->create([
            'title' => 'Test Node',
            'category' => 'testing',
            'module' => 'core',
            'priority' => 'high',
            'confidence' => 90,
            'status' => 'active',
            'tags' => ['php', 'test'],
            'usage_count' => 5,
        ]);

        $exporter = new GraphExporter;
        $graph = $exporter->exportGraph();

        $node = $graph['nodes'][0];
        expect($node['id'])->toBe($entry->id);
        expect($node['label'])->toBe('Test Node');
        expect($node['category'])->toBe('testing');
        expect($node['module'])->toBe('core');
        expect($node['priority'])->toBe('high');
        expect($node['confidence'])->toBe(90);
        expect($node['status'])->toBe('active');
        expect($node['tags'])->toBe(['php', 'test']);
        expect($node['usage_count'])->toBe(5);
        expect($node)->toHaveKey('created_at');
    });

    it('includes complete link data', function () {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => 'relates_to',
            'metadata' => ['strength' => 'weak'],
        ]);

        $exporter = new GraphExporter;
        $graph = $exporter->exportGraph();

        $link = $graph['links'][0];
        expect($link['source'])->toBe($entry1->id);
        expect($link['target'])->toBe($entry2->id);
        expect($link['type'])->toBe('relates_to');
        expect($link['metadata'])->toBe(['strength' => 'weak']);
    });

    it('includes metadata with counts', function () {
        Entry::factory()->count(3)->create();
        $entries = Entry::all();

        Relationship::factory()->create([
            'from_entry_id' => $entries[0]->id,
            'to_entry_id' => $entries[1]->id,
            'type' => 'depends_on',
        ]);

        $exporter = new GraphExporter;
        $graph = $exporter->exportGraph();

        expect($graph['metadata']['total_nodes'])->toBe(3);
        expect($graph['metadata']['total_links'])->toBe(1);
        expect($graph['metadata'])->toHaveKey('generated_at');
    });

    it('exports cytoscape format correctly', function () {
        $entry1 = Entry::factory()->create(['title' => 'Node 1']);
        $entry2 = Entry::factory()->create(['title' => 'Node 2']);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => 'extends',
        ]);

        $exporter = new GraphExporter;
        $graph = $exporter->exportCytoscapeGraph();

        expect($graph)->toHaveKey('elements');
        expect($graph)->toHaveKey('metadata');
        expect($graph['metadata']['format'])->toBe('cytoscape');

        $elements = $graph['elements'];
        $nodes = array_filter($elements, fn ($e) => $e['group'] === 'nodes');
        $edges = array_filter($elements, fn ($e) => $e['group'] === 'edges');

        expect(count($nodes))->toBe(2);
        expect(count($edges))->toBe(1);
    });

    it('includes correct cytoscape node structure', function () {
        $entry = Entry::factory()->create([
            'title' => 'Test Node',
            'category' => 'testing',
            'priority' => 'high',
            'confidence' => 85,
        ]);

        $exporter = new GraphExporter;
        $graph = $exporter->exportCytoscapeGraph();

        $node = $graph['elements'][0];
        expect($node['group'])->toBe('nodes');
        expect($node['data']['id'])->toBe((string) $entry->id);
        expect($node['data']['label'])->toBe('Test Node');
        expect($node['data']['category'])->toBe('testing');
        expect($node['data']['priority'])->toBe('high');
        expect($node['data']['confidence'])->toBe(85);
    });

    it('includes correct cytoscape edge structure', function () {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();

        $relationship = Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => 'implements',
        ]);

        $exporter = new GraphExporter;
        $graph = $exporter->exportCytoscapeGraph();

        $edge = array_values(array_filter($graph['elements'], fn ($e) => $e['group'] === 'edges'))[0];
        expect($edge['group'])->toBe('edges');
        expect($edge['data']['id'])->toBe("e{$relationship->id}");
        expect($edge['data']['source'])->toBe((string) $entry1->id);
        expect($edge['data']['target'])->toBe((string) $entry2->id);
        expect($edge['data']['label'])->toBe('implements');
        expect($edge['data']['type'])->toBe('implements');
    });

    it('exports dot format correctly', function () {
        $entry1 = Entry::factory()->create(['title' => 'Node 1', 'priority' => 'high']);
        $entry2 = Entry::factory()->create(['title' => 'Node 2', 'priority' => 'low']);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => 'depends_on',
        ]);

        $exporter = new GraphExporter;
        $dot = $exporter->exportDotGraph();

        expect($dot)->toContain('digraph Knowledge');
        expect($dot)->toContain('n'.$entry1->id);
        expect($dot)->toContain('n'.$entry2->id);
        expect($dot)->toContain('Node 1');
        expect($dot)->toContain('Node 2');
        expect($dot)->toContain('->');
        expect($dot)->toContain('depends_on');
    });

    it('escapes quotes in dot labels', function () {
        Entry::factory()->create(['title' => 'Node with "quotes"']);

        $exporter = new GraphExporter;
        $dot = $exporter->exportDotGraph();

        expect($dot)->toContain('Node with \"quotes\"');
    });

    it('applies correct colors based on priority', function () {
        Entry::factory()->create(['priority' => 'high']);
        Entry::factory()->create(['priority' => 'medium']);
        Entry::factory()->create(['priority' => 'low']);

        $exporter = new GraphExporter;
        $dot = $exporter->exportDotGraph();

        expect($dot)->toContain('#e74c3c'); // high
        expect($dot)->toContain('#f39c12'); // medium
        expect($dot)->toContain('#3498db'); // low
    });

    it('applies correct edge styles based on type', function () {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();
        $entry3 = Entry::factory()->create();

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => 'depends_on',
        ]);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry3->id,
            'type' => 'relates_to',
        ]);

        $exporter = new GraphExporter;
        $dot = $exporter->exportDotGraph();

        expect($dot)->toContain('style=solid');
        expect($dot)->toContain('style=dashed');
    });

    it('handles empty graph', function () {
        $exporter = new GraphExporter;
        $graph = $exporter->exportGraph();

        expect(count($graph['nodes']))->toBe(0);
        expect(count($graph['links']))->toBe(0);
        expect($graph['metadata']['total_nodes'])->toBe(0);
        expect($graph['metadata']['total_links'])->toBe(0);
    });

    it('handles graph with nodes but no relationships', function () {
        Entry::factory()->count(5)->create();

        $exporter = new GraphExporter;
        $graph = $exporter->exportGraph();

        expect(count($graph['nodes']))->toBe(5);
        expect(count($graph['links']))->toBe(0);
    });

    it('handles multiple relationships between same nodes', function () {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => 'depends_on',
        ]);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => 'relates_to',
        ]);

        $exporter = new GraphExporter;
        $graph = $exporter->exportGraph();

        expect(count($graph['links']))->toBe(2);
    });
});
