<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Relationship;

beforeEach(function () {
    Entry::query()->delete();
    Relationship::query()->delete();
});

describe('knowledge:export:graph command', function () {
    it('exports knowledge graph in json format', function () {
        $entry1 = Entry::factory()->create(['title' => 'Entry 1']);
        $entry2 = Entry::factory()->create(['title' => 'Entry 2']);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => 'depends_on',
        ]);

        $outputFile = sys_get_temp_dir().'/graph-test-'.time().'.json';

        $this->artisan('knowledge:export:graph', [
            '--format' => 'json',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);
        $data = json_decode($output, true);

        expect($data)->not->toBeNull();
        expect($data)->toHaveKey('nodes');
        expect($data)->toHaveKey('links');
        expect($data)->toHaveKey('metadata');
        expect(count($data['nodes']))->toBe(2);
        expect(count($data['links']))->toBe(1);

        unlink($outputFile);
    });

    it('includes complete node information', function () {
        Entry::factory()->create([
            'title' => 'Test Node',
            'category' => 'testing',
            'module' => 'core',
            'priority' => 'high',
            'confidence' => 90,
            'tags' => ['php', 'test'],
            'usage_count' => 5,
        ]);

        $outputFile = sys_get_temp_dir().'/graph-node-'.time().'.json';

        $this->artisan('knowledge:export:graph', [
            '--format' => 'json',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);
        $data = json_decode($output, true);
        $node = $data['nodes'][0];

        expect($node)->toHaveKey('id');
        expect($node)->toHaveKey('label');
        expect($node['label'])->toBe('Test Node');
        expect($node['category'])->toBe('testing');
        expect($node['module'])->toBe('core');
        expect($node['priority'])->toBe('high');
        expect($node['confidence'])->toBe(90);
        expect($node['tags'])->toBe(['php', 'test']);
        expect($node['usage_count'])->toBe(5);
        expect($node)->toHaveKey('created_at');

        unlink($outputFile);
    });

    it('includes complete link information', function () {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => 'depends_on',
            'metadata' => ['strength' => 'strong'],
        ]);

        $outputFile = sys_get_temp_dir().'/graph-link-'.time().'.json';

        $this->artisan('knowledge:export:graph', [
            '--format' => 'json',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);
        $data = json_decode($output, true);
        $link = $data['links'][0];

        expect($link)->toHaveKey('source');
        expect($link)->toHaveKey('target');
        expect($link['source'])->toBe($entry1->id);
        expect($link['target'])->toBe($entry2->id);
        expect($link['type'])->toBe('depends_on');
        expect($link['metadata'])->toBe(['strength' => 'strong']);

        unlink($outputFile);
    });

    it('exports graph in cytoscape format', function () {
        $entry1 = Entry::factory()->create(['title' => 'Node 1']);
        $entry2 = Entry::factory()->create(['title' => 'Node 2']);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => 'relates_to',
        ]);

        $outputFile = sys_get_temp_dir().'/graph-cyto-'.time().'.json';

        $this->artisan('knowledge:export:graph', [
            '--format' => 'cytoscape',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);
        $data = json_decode($output, true);

        expect($data)->toHaveKey('elements');
        expect($data)->toHaveKey('metadata');
        expect($data['metadata']['format'])->toBe('cytoscape');

        $elements = $data['elements'];
        $nodes = array_filter($elements, fn ($e) => $e['group'] === 'nodes');
        $edges = array_filter($elements, fn ($e) => $e['group'] === 'edges');

        expect(count($nodes))->toBe(2);
        expect(count($edges))->toBe(1);

        unlink($outputFile);
    });

    it('exports graph in dot format', function () {
        $entry1 = Entry::factory()->create([
            'title' => 'Test Node 1',
            'priority' => 'high',
        ]);

        $entry2 = Entry::factory()->create([
            'title' => 'Test Node 2',
            'priority' => 'low',
        ]);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => 'extends',
        ]);

        $outputFile = sys_get_temp_dir().'/graph-dot-'.time().'.dot';

        $this->artisan('knowledge:export:graph', [
            '--format' => 'dot',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);

        expect($output)->toContain('digraph Knowledge');
        expect($output)->toContain('n'.$entry1->id);
        expect($output)->toContain('n'.$entry2->id);
        expect($output)->toContain('Test Node 1');
        expect($output)->toContain('Test Node 2');
        expect($output)->toContain('->');
        expect($output)->toContain('extends');

        unlink($outputFile);
    });

    it('exports graph to a file', function () {
        Entry::factory()->create();

        $outputFile = sys_get_temp_dir().'/graph-export.json';

        $this->artisan('knowledge:export:graph', [
            '--format' => 'json',
            '--output' => $outputFile,
        ])->assertSuccessful();

        expect(file_exists($outputFile))->toBeTrue();

        $data = json_decode(file_get_contents($outputFile), true);
        expect($data)->not->toBeNull();
        expect($data)->toHaveKey('nodes');

        unlink($outputFile);
    });

    it('creates output directory if it does not exist', function () {
        Entry::factory()->create();

        $outputFile = sys_get_temp_dir().'/graph-dir-'.time().'/graph.json';

        $this->artisan('knowledge:export:graph', [
            '--output' => $outputFile,
        ])->assertSuccessful();

        expect(file_exists($outputFile))->toBeTrue();

        unlink($outputFile);
        rmdir(dirname($outputFile));
    });

    it('handles graph with no relationships', function () {
        Entry::factory()->count(3)->create();

        $outputFile = sys_get_temp_dir().'/graph-norel-'.time().'.json';

        $this->artisan('knowledge:export:graph', [
            '--format' => 'json',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);
        $data = json_decode($output, true);

        expect(count($data['nodes']))->toBe(3);
        expect(count($data['links']))->toBe(0);

        unlink($outputFile);
    });

    it('handles empty graph', function () {
        $outputFile = sys_get_temp_dir().'/graph-empty-'.time().'.json';

        $this->artisan('knowledge:export:graph', [
            '--format' => 'json',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);
        $data = json_decode($output, true);

        expect(count($data['nodes']))->toBe(0);
        expect(count($data['links']))->toBe(0);

        unlink($outputFile);
    });

    it('includes metadata in graph export', function () {
        Entry::factory()->create();

        $outputFile = sys_get_temp_dir().'/graph-meta-'.time().'.json';

        $this->artisan('knowledge:export:graph', [
            '--format' => 'json',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);
        $data = json_decode($output, true);
        $metadata = $data['metadata'];

        expect($metadata)->toHaveKey('generated_at');
        expect($metadata)->toHaveKey('total_nodes');
        expect($metadata)->toHaveKey('total_links');
        expect($metadata['total_nodes'])->toBe(1);
        expect($metadata['total_links'])->toBe(0);

        unlink($outputFile);
    });

    it('handles complex relationship types', function () {
        $entry1 = Entry::factory()->create();
        $entry2 = Entry::factory()->create();
        $entry3 = Entry::factory()->create();

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry2->id,
            'type' => 'depends_on',
        ]);

        Relationship::factory()->create([
            'from_entry_id' => $entry2->id,
            'to_entry_id' => $entry3->id,
            'type' => 'conflicts_with',
        ]);

        Relationship::factory()->create([
            'from_entry_id' => $entry1->id,
            'to_entry_id' => $entry3->id,
            'type' => 'similar_to',
        ]);

        $outputFile = sys_get_temp_dir().'/graph-complex-'.time().'.json';

        $this->artisan('knowledge:export:graph', [
            '--format' => 'json',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);
        $data = json_decode($output, true);

        expect(count($data['links']))->toBe(3);

        $types = array_column($data['links'], 'type');
        expect($types)->toContain('depends_on');
        expect($types)->toContain('conflicts_with');
        expect($types)->toContain('similar_to');

        unlink($outputFile);
    });

    it('escapes special characters in dot format', function () {
        Entry::factory()->create([
            'title' => 'Title with "quotes"',
        ]);

        $outputFile = sys_get_temp_dir().'/graph-escape-'.time().'.dot';

        $this->artisan('knowledge:export:graph', [
            '--format' => 'dot',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);
        expect($output)->toContain('Title with \"quotes\"');

        unlink($outputFile);
    });

    it('applies correct colors in dot format', function () {
        Entry::factory()->create(['priority' => 'high']);
        Entry::factory()->create(['priority' => 'medium']);
        Entry::factory()->create(['priority' => 'low']);

        $outputFile = sys_get_temp_dir().'/graph-colors-'.time().'.dot';

        $this->artisan('knowledge:export:graph', [
            '--format' => 'dot',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);
        expect($output)->toContain('#e74c3c'); // high priority
        expect($output)->toContain('#f39c12'); // medium priority
        expect($output)->toContain('#3498db'); // low priority

        unlink($outputFile);
    });

    it('fails with unsupported format', function () {
        Entry::factory()->create();

        $this->artisan('knowledge:export:graph', [
            '--format' => 'invalid',
        ])->assertFailed();
    });
});
