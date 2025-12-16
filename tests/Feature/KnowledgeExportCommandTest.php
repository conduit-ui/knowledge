<?php

declare(strict_types=1);

use App\Models\Entry;

beforeEach(function () {
    Entry::query()->delete();
});

describe('knowledge:export command', function () {
    it('exports a single entry to markdown format', function () {
        $entry = Entry::factory()->create([
            'title' => 'Test Export',
            'content' => 'Export content',
            'category' => 'testing',
            'tags' => ['php', 'export'],
            'priority' => 'high',
            'confidence' => 90,
        ]);

        $outputFile = sys_get_temp_dir().'/test-markdown-'.time().'.md';

        $this->artisan('knowledge:export', [
            'id' => $entry->id,
            '--format' => 'markdown',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);

        expect($output)->toContain('---');
        expect($output)->toContain('title: "Test Export"');
        expect($output)->toContain('# Test Export');
        expect($output)->toContain('Export content');
        expect($output)->toContain('category: "testing"');
        expect($output)->toContain('priority: "high"');
        expect($output)->toContain('confidence: 90');
        expect($output)->toContain('tags:');
        expect($output)->toContain('- "php"');
        expect($output)->toContain('- "export"');

        unlink($outputFile);
    });

    it('exports a single entry to json format', function () {
        $entry = Entry::factory()->create([
            'title' => 'JSON Export',
            'content' => 'JSON content',
        ]);

        $outputFile = sys_get_temp_dir().'/test-json-'.time().'.json';

        $this->artisan('knowledge:export', [
            'id' => $entry->id,
            '--format' => 'json',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);
        $json = json_decode($output, true);
        expect($json)->not->toBeNull();
        expect($json['title'])->toBe('JSON Export');
        expect($json['content'])->toBe('JSON content');

        unlink($outputFile);
    });

    it('exports entry to a file', function () {
        $entry = Entry::factory()->create([
            'title' => 'File Export',
            'content' => 'File content',
        ]);

        $outputFile = sys_get_temp_dir().'/test-export.md';

        $this->artisan('knowledge:export', [
            'id' => $entry->id,
            '--format' => 'markdown',
            '--output' => $outputFile,
        ])->assertSuccessful();

        expect(file_exists($outputFile))->toBeTrue();
        $content = file_get_contents($outputFile);
        expect($content)->toContain('# File Export');
        expect($content)->toContain('File content');

        unlink($outputFile);
    });

    it('creates output directory if it does not exist', function () {
        $entry = Entry::factory()->create([
            'title' => 'Directory Test',
            'content' => 'Content',
        ]);

        $outputFile = sys_get_temp_dir().'/test-dir-'.time().'/export.md';

        $this->artisan('knowledge:export', [
            'id' => $entry->id,
            '--output' => $outputFile,
        ])->assertSuccessful();

        expect(file_exists($outputFile))->toBeTrue();

        unlink($outputFile);
        rmdir(dirname($outputFile));
    });

    it('fails when entry does not exist', function () {
        $this->artisan('knowledge:export', [
            'id' => 99999,
            '--format' => 'markdown',
        ])->assertFailed();
    });

    it('fails with invalid ID', function () {
        $this->artisan('knowledge:export', [
            'id' => 'invalid',
            '--format' => 'markdown',
        ])->assertFailed();
    });

    it('exports entry with all metadata fields', function () {
        $entry = Entry::factory()->create([
            'title' => 'Full Metadata',
            'content' => 'Content',
            'category' => 'test',
            'module' => 'core',
            'tags' => ['tag1', 'tag2'],
            'source' => 'test-source',
            'ticket' => 'TICK-123',
            'author' => 'John Doe',
            'files' => ['file1.php', 'file2.php'],
            'repo' => 'test/repo',
            'branch' => 'main',
            'commit' => 'abc123',
        ]);

        $outputFile = sys_get_temp_dir().'/test-metadata-'.time().'.md';

        $this->artisan('knowledge:export', [
            'id' => $entry->id,
            '--format' => 'markdown',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);

        expect($output)->toContain('module: "core"');
        expect($output)->toContain('source: "test-source"');
        expect($output)->toContain('ticket: "TICK-123"');
        expect($output)->toContain('author: "John Doe"');
        expect($output)->toContain('files:');
        expect($output)->toContain('- "file1.php"');
        expect($output)->toContain('- "file2.php"');
        expect($output)->toContain('repo: "test/repo"');
        expect($output)->toContain('branch: "main"');
        expect($output)->toContain('commit: "abc123"');

        unlink($outputFile);
    });

    it('escapes special characters in yaml', function () {
        $entry = Entry::factory()->create([
            'title' => 'Title with "quotes"',
            'content' => 'Content',
        ]);

        $outputFile = sys_get_temp_dir().'/test-escape-'.time().'.md';

        $this->artisan('knowledge:export', [
            'id' => $entry->id,
            '--format' => 'markdown',
            '--output' => $outputFile,
        ])->assertSuccessful();

        $output = file_get_contents($outputFile);
        expect($output)->toContain('title: "Title with \"quotes\""');

        unlink($outputFile);
    });
});
