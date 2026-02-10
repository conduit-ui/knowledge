<?php

declare(strict_types=1);

use App\Services\MarkdownExporter;
use App\Services\QdrantService;

describe('KnowledgeExportAllCommand', function (): void {
    beforeEach(function (): void {
        $this->qdrant = mock(QdrantService::class);
        $this->markdownExporter = mock(MarkdownExporter::class);

        app()->instance(QdrantService::class, $this->qdrant);
        app()->instance(MarkdownExporter::class, $this->markdownExporter);
        mockProjectDetector();

        // Clean and recreate temp directory for tests
        if (is_dir('/tmp/export-all-tests')) {
            array_map('unlink', glob('/tmp/export-all-tests/*') ?: []);
            rmdir('/tmp/export-all-tests');
        }
        mkdir('/tmp/export-all-tests', 0755, true);
    });

    afterEach(function (): void {
        // Clean up test files
        if (is_dir('/tmp/export-all-tests')) {
            array_map('unlink', glob('/tmp/export-all-tests/*'));
            rmdir('/tmp/export-all-tests');
        }
    });

    it('exports all entries as markdown', function (): void {
        $entries = collect([
            [
                'id' => 1,
                'title' => 'First Entry',
                'content' => 'First content',
                'category' => 'tutorial',
                'tags' => ['laravel'],
                'module' => null,
                'priority' => 'high',
                'confidence' => 95,
                'status' => 'validated',
            ],
            [
                'id' => 2,
                'title' => 'Second Entry',
                'content' => 'Second content',
                'category' => 'guide',
                'tags' => ['php'],
                'module' => null,
                'priority' => 'medium',
                'confidence' => 80,
                'status' => 'draft',
            ],
        ]);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000, 'default')
            ->andReturn($entries);

        $this->markdownExporter->shouldReceive('exportArray')
            ->twice()
            ->andReturnUsing(fn ($entry): string => "# {$entry['title']}");

        $this->artisan('export:all', [
            '--output' => '/tmp/export-all-tests',
        ])
            ->expectsOutputToContain('Exporting 2 entries to: /tmp/export-all-tests')
            ->expectsOutputToContain('Export completed successfully!')
            ->assertSuccessful();

        expect(file_exists('/tmp/export-all-tests/1-first-entry.md'))->toBeTrue();
        expect(file_exists('/tmp/export-all-tests/2-second-entry.md'))->toBeTrue();
    });

    it('exports all entries as json', function (): void {
        $entries = collect([
            [
                'id' => 1,
                'title' => 'JSON Entry',
                'content' => 'JSON content',
                'category' => null,
                'tags' => [],
                'module' => null,
                'priority' => 'low',
                'confidence' => 50,
                'status' => 'draft',
            ],
        ]);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000, 'default')
            ->andReturn($entries);

        $this->artisan('export:all', [
            '--format' => 'json',
            '--output' => '/tmp/export-all-tests',
        ])
            ->expectsOutputToContain('Exporting 1 entries to: /tmp/export-all-tests')
            ->expectsOutputToContain('Export completed successfully!')
            ->assertSuccessful();

        expect(file_exists('/tmp/export-all-tests/1-json-entry.json'))->toBeTrue();
    });

    it('filters by category when specified', function (): void {
        $entries = collect([
            [
                'id' => 1,
                'title' => 'Tutorial Entry',
                'content' => 'Tutorial content',
                'category' => 'tutorial',
                'tags' => [],
                'module' => null,
                'priority' => 'medium',
                'confidence' => 70,
                'status' => 'draft',
            ],
        ]);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', ['category' => 'tutorial'], 10000, 'default')
            ->andReturn($entries);

        $this->markdownExporter->shouldReceive('exportArray')
            ->once()
            ->andReturn('# Tutorial Entry');

        $this->artisan('export:all', [
            '--category' => 'tutorial',
            '--output' => '/tmp/export-all-tests',
        ])
            ->expectsOutputToContain('Exporting 1 entries to: /tmp/export-all-tests')
            ->assertSuccessful();
    });

    it('warns when no entries found', function (): void {
        $this->qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000, 'default')
            ->andReturn(collect([]));

        $this->artisan('export:all', [
            '--output' => '/tmp/export-all-tests',
        ])
            ->expectsOutput('No entries found to export.')
            ->assertSuccessful();
    });

    it('creates output directory if not exists', function (): void {
        $entries = collect([
            [
                'id' => 1,
                'title' => 'Test Entry',
                'content' => 'Content',
                'category' => null,
                'tags' => [],
                'module' => null,
                'priority' => 'medium',
                'confidence' => 60,
                'status' => 'draft',
            ],
        ]);

        $outputDir = '/tmp/export-all-tests/new-dir';

        $this->qdrant->shouldReceive('search')
            ->once()
            ->andReturn($entries);

        $this->markdownExporter->shouldReceive('exportArray')
            ->once()
            ->andReturn('# Test Entry');

        $this->artisan('export:all', [
            '--output' => $outputDir,
        ])
            ->assertSuccessful();

        expect(is_dir($outputDir))->toBeTrue();

        // Clean up
        unlink($outputDir.'/1-test-entry.md');
        rmdir($outputDir);
    });

    it('uses default output directory when not specified', function (): void {
        $entries = collect([
            [
                'id' => 1,
                'title' => 'Default Dir Test',
                'content' => 'Content',
                'category' => null,
                'tags' => [],
                'module' => null,
                'priority' => 'medium',
                'confidence' => 50,
                'status' => 'draft',
            ],
        ]);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->andReturn($entries);

        $this->markdownExporter->shouldReceive('exportArray')
            ->once()
            ->andReturn('# Default Dir Test');

        $this->artisan('export:all')
            ->expectsOutputToContain('Exporting 1 entries to: ./docs')
            ->assertSuccessful();

        // Clean up if created
        if (file_exists('./docs/1-default-dir-test.md')) {
            unlink('./docs/1-default-dir-test.md');
            if (is_dir('./docs') && count(scandir('./docs')) === 2) {
                rmdir('./docs');
            }
        }
    });

    it('generates proper filename slug', function (): void {
        $entries = collect([
            [
                'id' => 123,
                'title' => 'Complex Title With Spaces & Special-Characters!',
                'content' => 'Content',
                'category' => null,
                'tags' => [],
                'module' => null,
                'priority' => 'medium',
                'confidence' => 50,
                'status' => 'draft',
            ],
        ]);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->andReturn($entries);

        $this->markdownExporter->shouldReceive('exportArray')
            ->once()
            ->andReturn('# Test');

        $this->artisan('export:all', [
            '--output' => '/tmp/export-all-tests',
        ])
            ->assertSuccessful();

        // Should create a slugified filename
        $files = glob('/tmp/export-all-tests/*.md');
        expect($files)->toHaveCount(1);
        expect(basename($files[0]))->toContain('123-');
    });

    it('handles entries with empty title', function (): void {
        $entries = collect([
            [
                'id' => 1,
                'title' => '',
                'content' => 'Content without title',
                'category' => null,
                'tags' => [],
                'module' => null,
                'priority' => 'medium',
                'confidence' => 50,
                'status' => 'draft',
            ],
        ]);

        $this->qdrant->shouldReceive('search')
            ->once()
            ->andReturn($entries);

        $this->markdownExporter->shouldReceive('exportArray')
            ->once()
            ->andReturn('# Untitled');

        $this->artisan('export:all', [
            '--output' => '/tmp/export-all-tests',
        ])
            ->assertSuccessful();

        // Should handle empty title gracefully
        $files = glob('/tmp/export-all-tests/*.md');
        expect($files)->toHaveCount(1);
    });
});
