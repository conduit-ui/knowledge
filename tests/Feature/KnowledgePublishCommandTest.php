<?php

declare(strict_types=1);

use App\Models\Entry;

beforeEach(function () {
    Entry::query()->delete();
});

describe('knowledge:publish command', function () {
    it('publishes a static site with all pages', function () {
        Entry::factory()->count(3)->create();

        $outputDir = sys_get_temp_dir().'/publish-site-'.time();

        $this->artisan('publish', [
            '--site' => $outputDir,
        ])->assertSuccessful();

        expect(file_exists("{$outputDir}/index.html"))->toBeTrue();
        expect(file_exists("{$outputDir}/categories.html"))->toBeTrue();
        expect(file_exists("{$outputDir}/tags.html"))->toBeTrue();

        // Check individual entry pages
        $entryFiles = glob("{$outputDir}/entry-*.html");
        expect(count($entryFiles))->toBe(3);

        // Cleanup
        array_map('unlink', $entryFiles);
        unlink("{$outputDir}/index.html");
        unlink("{$outputDir}/categories.html");
        unlink("{$outputDir}/tags.html");
        rmdir($outputDir);
    });

    it('generates valid HTML in index page', function () {
        Entry::factory()->create([
            'title' => 'Test Entry',
            'content' => 'Test content',
            'category' => 'testing',
            'tags' => ['php', 'test'],
        ]);

        $outputDir = sys_get_temp_dir().'/publish-html-'.time();

        $this->artisan('publish', [
            '--site' => $outputDir,
        ])->assertSuccessful();

        $html = file_get_contents("{$outputDir}/index.html");

        expect($html)->toContain('<!DOCTYPE html>');
        expect($html)->toContain('<title>Knowledge Base - Home</title>');
        expect($html)->toContain('Test Entry');
        expect($html)->toContain('testing');
        expect($html)->toContain('php');
        expect($html)->toContain('test');

        // Cleanup
        $files = glob("{$outputDir}/*");
        array_map('unlink', $files);
        rmdir($outputDir);
    });

    it('generates individual entry pages with full content', function () {
        $entry = Entry::factory()->create([
            'title' => 'Detailed Entry',
            'content' => 'This is detailed content',
            'category' => 'docs',
            'module' => 'core',
            'priority' => 'high',
            'confidence' => 95,
            'tags' => ['important'],
            'source' => 'manual',
            'author' => 'Test Author',
        ]);

        $outputDir = sys_get_temp_dir().'/publish-entry-'.time();

        $this->artisan('publish', [
            '--site' => $outputDir,
        ])->assertSuccessful();

        $html = file_get_contents("{$outputDir}/entry-{$entry->id}.html");

        expect($html)->toContain('Detailed Entry');
        expect($html)->toContain('This is detailed content');
        expect($html)->toContain('docs');
        expect($html)->toContain('core');
        expect($html)->toContain('high');
        expect($html)->toContain('95%');
        expect($html)->toContain('important');
        expect($html)->toContain('manual');
        expect($html)->toContain('Test Author');

        // Cleanup
        $files = glob("{$outputDir}/*");
        array_map('unlink', $files);
        rmdir($outputDir);
    });

    it('generates categories page with grouped entries', function () {
        Entry::factory()->create([
            'title' => 'Entry 1',
            'category' => 'testing',
        ]);

        Entry::factory()->create([
            'title' => 'Entry 2',
            'category' => 'testing',
        ]);

        Entry::factory()->create([
            'title' => 'Entry 3',
            'category' => 'production',
        ]);

        $outputDir = sys_get_temp_dir().'/publish-categories-'.time();

        $this->artisan('publish', [
            '--site' => $outputDir,
        ])->assertSuccessful();

        $html = file_get_contents("{$outputDir}/categories.html");

        expect($html)->toContain('testing');
        expect($html)->toContain('2 entries');
        expect($html)->toContain('production');
        expect($html)->toContain('1 entries');
        expect($html)->toContain('Entry 1');
        expect($html)->toContain('Entry 2');
        expect($html)->toContain('Entry 3');

        // Cleanup
        $files = glob("{$outputDir}/*");
        array_map('unlink', $files);
        rmdir($outputDir);
    });

    it('generates tags page with all tags', function () {
        Entry::factory()->create([
            'title' => 'Entry 1',
            'tags' => ['php', 'laravel'],
        ]);

        Entry::factory()->create([
            'title' => 'Entry 2',
            'tags' => ['php', 'testing'],
        ]);

        $outputDir = sys_get_temp_dir().'/publish-tags-'.time();

        $this->artisan('publish', [
            '--site' => $outputDir,
        ])->assertSuccessful();

        $html = file_get_contents("{$outputDir}/tags.html");

        expect($html)->toContain('php');
        expect($html)->toContain('laravel');
        expect($html)->toContain('testing');
        expect($html)->toContain('Entry 1');
        expect($html)->toContain('Entry 2');

        // Cleanup
        $files = glob("{$outputDir}/*");
        array_map('unlink', $files);
        rmdir($outputDir);
    });

    it('creates output directory if it does not exist', function () {
        Entry::factory()->create();

        $outputDir = sys_get_temp_dir().'/publish-new-dir-'.time();

        $this->artisan('publish', [
            '--site' => $outputDir,
        ])->assertSuccessful();

        expect(is_dir($outputDir))->toBeTrue();

        // Cleanup
        $files = glob("{$outputDir}/*");
        array_map('unlink', $files);
        rmdir($outputDir);
    });

    it('includes search functionality in index page', function () {
        Entry::factory()->create();

        $outputDir = sys_get_temp_dir().'/publish-search-'.time();

        $this->artisan('publish', [
            '--site' => $outputDir,
        ])->assertSuccessful();

        $html = file_get_contents("{$outputDir}/index.html");

        expect($html)->toContain('search');
        expect($html)->toContain('filterEntries');
        expect($html)->toContain('function filterEntries()');

        // Cleanup
        $files = glob("{$outputDir}/*");
        array_map('unlink', $files);
        rmdir($outputDir);
    });

    it('includes responsive CSS in pages', function () {
        Entry::factory()->create();

        $outputDir = sys_get_temp_dir().'/publish-responsive-'.time();

        $this->artisan('publish', [
            '--site' => $outputDir,
        ])->assertSuccessful();

        $html = file_get_contents("{$outputDir}/index.html");

        expect($html)->toContain('viewport');
        expect($html)->toContain('@media');

        // Cleanup
        $files = glob("{$outputDir}/*");
        array_map('unlink', $files);
        rmdir($outputDir);
    });

    it('includes navigation links in all pages', function () {
        Entry::factory()->create();

        $outputDir = sys_get_temp_dir().'/publish-nav-'.time();

        $this->artisan('publish', [
            '--site' => $outputDir,
        ])->assertSuccessful();

        $indexHtml = file_get_contents("{$outputDir}/index.html");
        expect($indexHtml)->toContain('index.html');
        expect($indexHtml)->toContain('categories.html');
        expect($indexHtml)->toContain('tags.html');

        $categoriesHtml = file_get_contents("{$outputDir}/categories.html");
        expect($categoriesHtml)->toContain('index.html');
        expect($categoriesHtml)->toContain('tags.html');

        // Cleanup
        $files = glob("{$outputDir}/*");
        array_map('unlink', $files);
        rmdir($outputDir);
    });

    it('handles entries with no tags or category', function () {
        Entry::factory()->create([
            'title' => 'Simple Entry',
            'content' => 'Simple content',
            'tags' => null,
            'category' => null,
        ]);

        $outputDir = sys_get_temp_dir().'/publish-simple-'.time();

        $this->artisan('publish', [
            '--site' => $outputDir,
        ])->assertSuccessful();

        expect(file_exists("{$outputDir}/index.html"))->toBeTrue();

        // Cleanup
        $files = glob("{$outputDir}/*");
        array_map('unlink', $files);
        rmdir($outputDir);
    });
});
