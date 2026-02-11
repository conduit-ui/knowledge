<?php

declare(strict_types=1);

use App\Services\MarkdownExporter;
use App\Services\QdrantService;

describe('KnowledgeExportCommand', function (): void {
    beforeEach(function (): void {
        $this->qdrant = mock(QdrantService::class);
        $this->markdownExporter = mock(MarkdownExporter::class);

        app()->instance(QdrantService::class, $this->qdrant);
        app()->instance(MarkdownExporter::class, $this->markdownExporter);
        mockProjectDetector();

        // Create temp directory for tests
        if (! is_dir('/tmp/export-tests')) {
            mkdir('/tmp/export-tests', 0755, true);
        }
    });

    afterEach(function (): void {
        // Clean up test files
        if (is_dir('/tmp/export-tests')) {
            array_map('unlink', glob('/tmp/export-tests/*'));
            rmdir('/tmp/export-tests');
        }
    });

    it('validates ID is numeric', function (): void {
        $this->artisan('export', ['id' => 'not-numeric'])
            ->expectsOutput('The ID must be a valid number.')
            ->assertFailed();
    });

    it('fails when entry not found', function (): void {
        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with(999, 'default')
            ->andReturn(null);

        $this->artisan('export', ['id' => '999'])
            ->expectsOutput('Entry not found.')
            ->assertFailed();
    });

    it('exports entry as markdown to stdout by default', function (): void {
        $entry = [
            'id' => 1,
            'title' => 'Test Entry',
            'content' => 'Test content',
            'category' => 'tutorial',
            'tags' => ['laravel', 'testing'],
            'module' => 'Core',
            'priority' => 'high',
            'confidence' => 95,
            'status' => 'validated',
        ];

        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with(1, 'default')
            ->andReturn($entry);

        $this->markdownExporter->shouldReceive('exportArray')
            ->once()
            ->with($entry)
            ->andReturn('# Test Entry

Test content');

        $this->artisan('export', ['id' => '1'])
            ->expectsOutput('# Test Entry

Test content')
            ->assertSuccessful();
    });

    it('exports entry as markdown to file', function (): void {
        $entry = [
            'id' => 1,
            'title' => 'Test Entry',
            'content' => 'Test content',
            'category' => 'tutorial',
            'tags' => ['laravel'],
            'module' => null,
            'priority' => 'medium',
            'confidence' => 80,
            'status' => 'draft',
        ];

        $outputPath = '/tmp/export-tests/test-entry.md';

        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with(1, 'default')
            ->andReturn($entry);

        $this->markdownExporter->shouldReceive('exportArray')
            ->once()
            ->with($entry)
            ->andReturn('# Test Entry

Test content');

        $this->artisan('export', [
            'id' => '1',
            '--output' => $outputPath,
        ])
            ->expectsOutputToContain("Exported entry #1 to: {$outputPath}")
            ->assertSuccessful();

        expect(file_exists($outputPath))->toBeTrue();
        expect(file_get_contents($outputPath))->toContain('# Test Entry');
    });

    it('exports entry as json to stdout', function (): void {
        $entry = [
            'id' => 1,
            'title' => 'Test Entry',
            'content' => 'Test content',
            'category' => 'guide',
            'tags' => ['php'],
            'module' => null,
            'priority' => 'low',
            'confidence' => 50,
            'status' => 'draft',
        ];

        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with(1, 'default')
            ->andReturn($entry);

        $expectedJson = json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->artisan('export', [
            'id' => '1',
            '--format' => 'json',
        ])
            ->expectsOutput($expectedJson)
            ->assertSuccessful();
    });

    it('exports entry as json to file', function (): void {
        $entry = [
            'id' => 2,
            'title' => 'JSON Export',
            'content' => 'Content for JSON',
            'category' => null,
            'tags' => [],
            'module' => null,
            'priority' => 'medium',
            'confidence' => 70,
            'status' => 'draft',
        ];

        $outputPath = '/tmp/export-tests/test-entry.json';

        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with(2, 'default')
            ->andReturn($entry);

        $this->artisan('export', [
            'id' => '2',
            '--format' => 'json',
            '--output' => $outputPath,
        ])
            ->expectsOutputToContain("Exported entry #2 to: {$outputPath}")
            ->assertSuccessful();

        expect(file_exists($outputPath))->toBeTrue();
        $json = json_decode(file_get_contents($outputPath), true);
        expect($json['title'])->toBe('JSON Export');
    });

    it('creates output directory if it does not exist', function (): void {
        $entry = [
            'id' => 3,
            'title' => 'New Directory Test',
            'content' => 'Content',
            'category' => null,
            'tags' => [],
            'module' => null,
            'priority' => 'medium',
            'confidence' => 60,
            'status' => 'draft',
        ];

        $outputPath = '/tmp/export-tests/nested/dir/test.md';

        $this->qdrant->shouldReceive('getById')
            ->once()
            ->with(3, 'default')
            ->andReturn($entry);

        $this->markdownExporter->shouldReceive('exportArray')
            ->once()
            ->with($entry)
            ->andReturn('# New Directory Test');

        $this->artisan('export', [
            'id' => '3',
            '--output' => $outputPath,
        ])
            ->expectsOutputToContain("Exported entry #3 to: {$outputPath}")
            ->assertSuccessful();

        expect(file_exists($outputPath))->toBeTrue();

        // Clean up nested directories
        unlink($outputPath);
        rmdir('/tmp/export-tests/nested/dir');
        rmdir('/tmp/export-tests/nested');
    });
});
