<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Services\MarkdownExporter;

describe('MarkdownExporter', function () {
    it('exports entry with basic fields', function () {
        $entry = Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content',
            'priority' => 'high',
            'confidence' => 90,
            'status' => 'active',
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->toContain('---');
        expect($markdown)->toContain('title: "Test Entry"');
        expect($markdown)->toContain('priority: "high"');
        expect($markdown)->toContain('confidence: 90');
        expect($markdown)->toContain('status: "active"');
        expect($markdown)->toContain('# Test Entry');
        expect($markdown)->toContain('Test content');
    });

    it('includes category when present', function () {
        $entry = Entry::factory()->create([
            'title' => 'Entry',
            'content' => 'Content',
            'category' => 'testing',
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->toContain('category: "testing"');
    });

    it('includes module when present', function () {
        $entry = Entry::factory()->create([
            'title' => 'Entry',
            'content' => 'Content',
            'module' => 'core',
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->toContain('module: "core"');
    });

    it('includes tags as yaml array', function () {
        $entry = Entry::factory()->create([
            'title' => 'Entry',
            'content' => 'Content',
            'tags' => ['php', 'laravel', 'testing'],
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->toContain('tags:');
        expect($markdown)->toContain('- "php"');
        expect($markdown)->toContain('- "laravel"');
        expect($markdown)->toContain('- "testing"');
    });

    it('includes files as yaml array', function () {
        $entry = Entry::factory()->create([
            'title' => 'Entry',
            'content' => 'Content',
            'files' => ['file1.php', 'file2.php'],
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->toContain('files:');
        expect($markdown)->toContain('- "file1.php"');
        expect($markdown)->toContain('- "file2.php"');
    });

    it('includes git metadata', function () {
        $entry = Entry::factory()->create([
            'title' => 'Entry',
            'content' => 'Content',
            'repo' => 'test/repo',
            'branch' => 'main',
            'commit' => 'abc123',
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->toContain('repo: "test/repo"');
        expect($markdown)->toContain('branch: "main"');
        expect($markdown)->toContain('commit: "abc123"');
    });

    it('includes source and ticket', function () {
        $entry = Entry::factory()->create([
            'title' => 'Entry',
            'content' => 'Content',
            'source' => 'manual',
            'ticket' => 'TICK-123',
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->toContain('source: "manual"');
        expect($markdown)->toContain('ticket: "TICK-123"');
    });

    it('includes author', function () {
        $entry = Entry::factory()->create([
            'title' => 'Entry',
            'content' => 'Content',
            'author' => 'John Doe',
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->toContain('author: "John Doe"');
    });

    it('includes usage statistics', function () {
        $entry = Entry::factory()->create([
            'title' => 'Entry',
            'content' => 'Content',
            'usage_count' => 42,
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->toContain('usage_count: 42');
    });

    it('includes timestamps in ISO format', function () {
        $entry = Entry::factory()->create([
            'title' => 'Entry',
            'content' => 'Content',
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->toContain('created_at:');
        expect($markdown)->toContain('updated_at:');
        expect($markdown)->toMatch('/created_at: "\d{4}-\d{2}-\d{2}T/');
    });

    it('escapes quotes in yaml values', function () {
        $entry = Entry::factory()->create([
            'title' => 'Entry with "quotes"',
            'content' => 'Content',
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->toContain('title: "Entry with \"quotes\""');
    });

    it('handles multiline content', function () {
        $entry = Entry::factory()->create([
            'title' => 'Entry',
            'content' => "Line 1\nLine 2\nLine 3",
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->toContain("Line 1\nLine 2\nLine 3");
    });

    it('omits optional fields when null', function () {
        $entry = Entry::factory()->create([
            'title' => 'Minimal Entry',
            'content' => 'Minimal content',
            'category' => null,
            'module' => null,
            'tags' => null,
            'source' => null,
            'ticket' => null,
            'author' => null,
            'files' => null,
            'repo' => null,
            'branch' => null,
            'commit' => null,
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->not->toContain('category:');
        expect($markdown)->not->toContain('module:');
        expect($markdown)->not->toContain('tags:');
        expect($markdown)->not->toContain('source:');
        expect($markdown)->not->toContain('ticket:');
        expect($markdown)->not->toContain('author:');
        expect($markdown)->not->toContain('files:');
        expect($markdown)->not->toContain('repo:');
        expect($markdown)->not->toContain('branch:');
        expect($markdown)->not->toContain('commit:');
    });

    it('includes id in front matter', function () {
        $entry = Entry::factory()->create([
            'title' => 'Entry',
            'content' => 'Content',
        ]);

        $exporter = new MarkdownExporter;
        $markdown = $exporter->export($entry);

        expect($markdown)->toContain("id: {$entry->id}");
    });
});
