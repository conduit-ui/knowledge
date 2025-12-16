<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\Entry;

beforeEach(function () {
    Entry::query()->delete();
    Collection::query()->delete();
});

describe('knowledge:export:all command', function () {
    it('exports all entries to markdown files', function () {
        Entry::factory()->count(3)->create();

        $outputDir = sys_get_temp_dir().'/export-all-'.time();

        $this->artisan('knowledge:export:all', [
            '--format' => 'markdown',
            '--output' => $outputDir,
        ])->assertSuccessful();

        expect(is_dir($outputDir))->toBeTrue();
        $files = glob("{$outputDir}/*.md");
        expect(count($files))->toBe(3);

        // Cleanup
        array_map('unlink', $files);
        rmdir($outputDir);
    });

    it('exports all entries to json files', function () {
        Entry::factory()->count(2)->create();

        $outputDir = sys_get_temp_dir().'/export-json-'.time();

        $this->artisan('knowledge:export:all', [
            '--format' => 'json',
            '--output' => $outputDir,
        ])->assertSuccessful();

        $files = glob("{$outputDir}/*.json");
        expect(count($files))->toBe(2);

        // Validate JSON
        $json = json_decode(file_get_contents($files[0]), true);
        expect($json)->not->toBeNull();
        expect($json)->toHaveKey('title');

        // Cleanup
        array_map('unlink', $files);
        rmdir($outputDir);
    });

    it('creates output directory if it does not exist', function () {
        Entry::factory()->create();

        $outputDir = sys_get_temp_dir().'/new-dir-'.time();

        $this->artisan('knowledge:export:all', [
            '--output' => $outputDir,
        ])->assertSuccessful();

        expect(is_dir($outputDir))->toBeTrue();

        // Cleanup
        $files = glob("{$outputDir}/*");
        array_map('unlink', $files);
        rmdir($outputDir);
    });

    it('generates proper filenames with slugs', function () {
        Entry::factory()->create([
            'title' => 'Test Entry with Spaces',
        ]);

        $outputDir = sys_get_temp_dir().'/export-slugs-'.time();

        $this->artisan('knowledge:export:all', [
            '--output' => $outputDir,
        ])->assertSuccessful();

        $files = glob("{$outputDir}/*.md");
        expect(count($files))->toBe(1);

        $filename = basename($files[0]);
        expect($filename)->toMatch('/^\d+-test-entry-with-spaces\.md$/');

        // Cleanup
        unlink($files[0]);
        rmdir($outputDir);
    });

    it('filters entries by collection', function () {
        $collection = Collection::factory()->create(['name' => 'Test Collection']);

        $entry1 = Entry::factory()->create(['title' => 'In Collection']);
        $entry2 = Entry::factory()->create(['title' => 'Not In Collection']);

        $collection->entries()->attach($entry1->id);

        $outputDir = sys_get_temp_dir().'/export-collection-'.time();

        $this->artisan('knowledge:export:all', [
            '--collection' => 'Test Collection',
            '--output' => $outputDir,
        ])->assertSuccessful();

        $files = glob("{$outputDir}/*.md");
        expect(count($files))->toBe(1);

        $content = file_get_contents($files[0]);
        expect($content)->toContain('In Collection');

        // Cleanup
        unlink($files[0]);
        rmdir($outputDir);
    });

    it('filters entries by category', function () {
        Entry::factory()->create([
            'title' => 'Category A',
            'category' => 'testing',
        ]);

        Entry::factory()->create([
            'title' => 'Category B',
            'category' => 'production',
        ]);

        $outputDir = sys_get_temp_dir().'/export-category-'.time();

        $this->artisan('knowledge:export:all', [
            '--category' => 'testing',
            '--output' => $outputDir,
        ])->assertSuccessful();

        $files = glob("{$outputDir}/*.md");
        expect(count($files))->toBe(1);

        $content = file_get_contents($files[0]);
        expect($content)->toContain('Category A');

        // Cleanup
        unlink($files[0]);
        rmdir($outputDir);
    });

    it('fails when collection does not exist', function () {
        Entry::factory()->create();

        $this->artisan('knowledge:export:all', [
            '--collection' => 'Nonexistent Collection',
            '--output' => sys_get_temp_dir().'/test',
        ])->assertFailed();
    });

    it('handles empty result set gracefully', function () {
        $outputDir = sys_get_temp_dir().'/export-empty-'.time();

        $this->artisan('knowledge:export:all', [
            '--output' => $outputDir,
        ])->assertSuccessful();

        // Should not create directory if no entries
        expect(is_dir($outputDir))->toBeFalse();
    });

    it('displays progress bar during export', function () {
        Entry::factory()->count(5)->create();

        $outputDir = sys_get_temp_dir().'/export-progress-'.time();

        $this->artisan('knowledge:export:all', [
            '--output' => $outputDir,
        ])->expectsOutput('Export completed successfully!')
            ->assertSuccessful();

        // Verify files were created
        $files = glob("{$outputDir}/*.md");
        expect(count($files))->toBe(5);

        // Cleanup
        array_map('unlink', $files);
        rmdir($outputDir);
    });

    it('handles special characters in filenames', function () {
        Entry::factory()->create([
            'title' => 'Title/with\\special:characters?',
        ]);

        $outputDir = sys_get_temp_dir().'/export-special-'.time();

        $this->artisan('knowledge:export:all', [
            '--output' => $outputDir,
        ])->assertSuccessful();

        $files = glob("{$outputDir}/*.md");
        expect(count($files))->toBe(1);

        // Filename should be sanitized
        $filename = basename($files[0]);
        expect($filename)->not->toContain('/');
        expect($filename)->not->toContain('\\');
        expect($filename)->not->toContain(':');
        expect($filename)->not->toContain('?');

        // Cleanup
        unlink($files[0]);
        rmdir($outputDir);
    });

    it('truncates long filenames', function () {
        Entry::factory()->create([
            'title' => str_repeat('Very Long Title ', 20),
        ]);

        $outputDir = sys_get_temp_dir().'/export-long-'.time();

        $this->artisan('knowledge:export:all', [
            '--output' => $outputDir,
        ])->assertSuccessful();

        $files = glob("{$outputDir}/*.md");
        expect(count($files))->toBe(1);

        $filename = basename($files[0], '.md');
        $slug = substr($filename, strpos($filename, '-') + 1);
        expect(strlen($slug))->toBeLessThanOrEqual(50);

        // Cleanup
        unlink($files[0]);
        rmdir($outputDir);
    });
});
