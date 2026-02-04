<?php

declare(strict_types=1);

use App\Services\MarkdownExporter;

beforeEach(function (): void {
    $this->exporter = new MarkdownExporter;
});

describe('exportArray', function (): void {
    it('exports entry with all fields', function (): void {
        $entry = [
            'id' => '123',
            'title' => 'Test Entry',
            'content' => 'This is test content',
            'category' => 'testing',
            'module' => 'TestModule',
            'priority' => 'high',
            'confidence' => 85,
            'status' => 'validated',
            'tags' => ['laravel', 'pest', 'testing'],
            'usage_count' => 10,
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T12:00:00Z',
        ];

        $result = $this->exporter->exportArray($entry);

        expect($result)->toContain('---');
        expect($result)->toContain('id: 123');
        expect($result)->toContain('title: "Test Entry"');
        expect($result)->toContain('category: "testing"');
        expect($result)->toContain('module: "TestModule"');
        expect($result)->toContain('priority: "high"');
        expect($result)->toContain('confidence: 85');
        expect($result)->toContain('status: "validated"');
        expect($result)->toContain('tags:');
        expect($result)->toContain('  - "laravel"');
        expect($result)->toContain('  - "pest"');
        expect($result)->toContain('  - "testing"');
        expect($result)->toContain('usage_count: 10');
        expect($result)->toContain('# Test Entry');
        expect($result)->toContain('This is test content');
    });

    it('exports entry with minimal fields', function (): void {
        $entry = [
            'id' => '456',
            'title' => 'Minimal Entry',
            'content' => 'Minimal content',
            'priority' => 'low',
            'confidence' => 50,
            'status' => 'draft',
            'usage_count' => 0,
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
        ];

        $result = $this->exporter->exportArray($entry);

        expect($result)->toContain('id: 456');
        expect($result)->toContain('title: "Minimal Entry"');
        expect($result)->toContain('# Minimal Entry');
        expect($result)->toContain('Minimal content');
        expect($result)->not->toContain('category:');
        expect($result)->not->toContain('module:');
        expect($result)->not->toContain('tags:');
    });

    it('escapes special characters in YAML', function (): void {
        $entry = [
            'id' => '789',
            'title' => 'Title with "quotes"',
            'content' => 'Content here',
            'category' => 'Category with "quotes"',
            'module' => 'Module with "quotes"',
            'priority' => 'medium',
            'confidence' => 75,
            'status' => 'validated',
            'tags' => ['tag"with"quotes'],
            'usage_count' => 5,
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
        ];

        $result = $this->exporter->exportArray($entry);

        expect($result)->toContain('title: "Title with \\"quotes\\""');
        expect($result)->toContain('category: "Category with \\"quotes\\""');
        expect($result)->toContain('module: "Module with \\"quotes\\""');
        expect($result)->toContain('  - "tag\\"with\\"quotes"');
    });

    it('handles empty tags array', function (): void {
        $entry = [
            'id' => '111',
            'title' => 'No Tags',
            'content' => 'Content',
            'priority' => 'low',
            'confidence' => 60,
            'status' => 'draft',
            'tags' => [],
            'usage_count' => 0,
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
        ];

        $result = $this->exporter->exportArray($entry);

        expect($result)->not->toContain('tags:');
    });

    it('handles empty content', function (): void {
        $entry = [
            'id' => '222',
            'title' => 'Empty Content',
            'content' => '',
            'priority' => 'medium',
            'confidence' => 70,
            'status' => 'validated',
            'usage_count' => 1,
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
        ];

        $result = $this->exporter->exportArray($entry);

        expect($result)->toContain('# Empty Content');
        expect($result)->toContain("\n\n\n"); // Empty content section
    });

    it('handles missing content key', function (): void {
        $entry = [
            'id' => '333',
            'title' => 'Missing Content Key',
            'priority' => 'high',
            'confidence' => 80,
            'status' => 'validated',
            'usage_count' => 2,
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
        ];

        $result = $this->exporter->exportArray($entry);

        expect($result)->toContain('# Missing Content Key');
        expect($result)->toContain("\n\n\n"); // Empty content section
    });

    it('formats front matter correctly', function (): void {
        $entry = [
            'id' => '444',
            'title' => 'Format Test',
            'content' => 'Test',
            'priority' => 'critical',
            'confidence' => 95,
            'status' => 'validated',
            'usage_count' => 15,
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-02T00:00:00Z',
        ];

        $result = $this->exporter->exportArray($entry);

        // Check proper YAML structure
        expect($result)->toStartWith('---');
        expect($result)->toMatch('/---\n.*\n---\n\n# /s');
    });

    it('includes all required fields', function (): void {
        $entry = [
            'id' => '555',
            'title' => 'Required Fields',
            'content' => 'Content',
            'priority' => 'medium',
            'confidence' => 70,
            'status' => 'draft',
            'usage_count' => 3,
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
        ];

        $result = $this->exporter->exportArray($entry);

        // All required fields should be present
        expect($result)->toContain('id:');
        expect($result)->toContain('title:');
        expect($result)->toContain('priority:');
        expect($result)->toContain('confidence:');
        expect($result)->toContain('status:');
        expect($result)->toContain('usage_count:');
        expect($result)->toContain('created_at:');
        expect($result)->toContain('updated_at:');
    });

    it('handles multiple tags correctly', function (): void {
        $entry = [
            'id' => '666',
            'title' => 'Multiple Tags',
            'content' => 'Content',
            'priority' => 'high',
            'confidence' => 85,
            'status' => 'validated',
            'tags' => ['tag1', 'tag2', 'tag3', 'tag4', 'tag5'],
            'usage_count' => 8,
            'created_at' => '2025-01-01T00:00:00Z',
            'updated_at' => '2025-01-01T00:00:00Z',
        ];

        $result = $this->exporter->exportArray($entry);

        expect($result)->toContain('tags:');
        expect($result)->toContain('  - "tag1"');
        expect($result)->toContain('  - "tag2"');
        expect($result)->toContain('  - "tag3"');
        expect($result)->toContain('  - "tag4"');
        expect($result)->toContain('  - "tag5"');
    });
});
