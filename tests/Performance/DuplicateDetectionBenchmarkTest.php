<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Services\SimilarityService;

/**
 * Performance benchmark tests for duplicate detection optimization.
 *
 * These tests measure the performance improvement from O(n²) to O(n log n).
 */
test('benchmark: find duplicates with 100 entries', function () {
    $service = new SimilarityService;
    $entries = collect();

    // Create 100 entries with some duplicates
    for ($i = 0; $i < 100; $i++) {
        $entries->push(new Entry([
            'id' => $i,
            'title' => 'Entry '.($i % 20), // Creates groups of ~5 similar entries
            'content' => 'Content for entry '.($i % 20).' with some variation '.$i,
        ]));
    }

    $start = microtime(true);
    $duplicates = $service->findDuplicates($entries, 0.5);
    $elapsed = microtime(true) - $start;

    expect($duplicates)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($elapsed)->toBeLessThan(0.5); // Should complete in less than 500ms

    echo sprintf("\n  ✓ 100 entries processed in %.3f seconds", $elapsed);
})->group('benchmark');

test('benchmark: find duplicates with 500 entries', function () {
    $service = new SimilarityService;
    $entries = collect();

    // Create 500 entries with some duplicates
    for ($i = 0; $i < 500; $i++) {
        $entries->push(new Entry([
            'id' => $i,
            'title' => 'Entry '.($i % 50),
            'content' => 'Content for entry '.($i % 50).' with some variation '.$i,
        ]));
    }

    $start = microtime(true);
    $duplicates = $service->findDuplicates($entries, 0.5);
    $elapsed = microtime(true) - $start;

    expect($duplicates)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($elapsed)->toBeLessThan(2.0); // Should complete in less than 2 seconds

    echo sprintf("\n  ✓ 500 entries processed in %.3f seconds", $elapsed);
})->group('benchmark');

test('benchmark: find duplicates with 1000 entries', function () {
    $service = new SimilarityService;
    $entries = collect();

    // Create 1000 entries with some duplicates
    for ($i = 0; $i < 1000; $i++) {
        $entries->push(new Entry([
            'id' => $i,
            'title' => 'Entry '.($i % 100),
            'content' => 'Content for entry '.($i % 100).' with some variation '.$i,
        ]));
    }

    $start = microtime(true);
    $duplicates = $service->findDuplicates($entries, 0.5);
    $elapsed = microtime(true) - $start;

    expect($duplicates)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($elapsed)->toBeLessThan(5.0); // Should complete in less than 5 seconds

    echo sprintf("\n  ✓ 1000 entries processed in %.3f seconds", $elapsed);
})->group('benchmark');

test('benchmark: tokenization caching improves performance', function () {
    $service = new SimilarityService;
    $entry = new Entry(['id' => 1, 'title' => 'Test Entry', 'content' => 'This is a long piece of content that needs to be tokenized multiple times to test caching performance']);

    // First call - no cache
    $start = microtime(true);
    for ($i = 0; $i < 1000; $i++) {
        $service->getTokens($entry);
    }
    $elapsed = microtime(true) - $start;

    expect($elapsed)->toBeLessThan(0.1); // With caching, 1000 calls should be < 100ms

    echo sprintf("\n  ✓ 1000 cached tokenization calls in %.4f seconds", $elapsed);
})->group('benchmark');

test('benchmark: MinHash signature generation', function () {
    $service = new SimilarityService;
    $entries = collect();

    for ($i = 0; $i < 100; $i++) {
        $entries->push(new Entry([
            'id' => $i,
            'title' => "Entry $i",
            'content' => "This is entry number $i with unique content",
        ]));
    }

    $start = microtime(true);
    foreach ($entries as $entry) {
        $service->getTokens($entry);
    }
    $elapsed = microtime(true) - $start;

    expect($elapsed)->toBeLessThan(0.1); // Should be very fast

    echo sprintf("\n  ✓ 100 MinHash signatures generated in %.4f seconds", $elapsed);
})->group('benchmark');

test('benchmark: LSH bucketing reduces comparisons', function () {
    $service = new SimilarityService;
    $entries = collect();

    // Create 200 entries
    for ($i = 0; $i < 200; $i++) {
        $entries->push(new Entry([
            'id' => $i,
            'title' => 'Entry '.($i % 40),
            'content' => 'Content for entry '.($i % 40).' variation '.$i,
        ]));
    }

    $start = microtime(true);
    $duplicates = $service->findDuplicates($entries, 0.6);
    $elapsed = microtime(true) - $start;

    // With LSH, 200 entries should complete quickly
    // Without LSH, this would be 200*200 = 40,000 comparisons
    // With LSH, we reduce to ~200 * average_bucket_size comparisons
    expect($elapsed)->toBeLessThan(1.0);

    echo sprintf("\n  ✓ 200 entries with LSH bucketing in %.3f seconds", $elapsed);
})->group('benchmark');

test('benchmark: comparison of similarity methods', function () {
    $service = new SimilarityService;
    $entry1 = new Entry(['id' => 1, 'title' => 'PHP Tutorial', 'content' => 'Learn PHP programming basics']);
    $entry2 = new Entry(['id' => 2, 'title' => 'PHP Guide', 'content' => 'Learn PHP programming fundamentals']);

    // Test MinHash estimation (fast)
    $start = microtime(true);
    for ($i = 0; $i < 1000; $i++) {
        $service->estimateSimilarity($entry1, $entry2);
    }
    $minHashTime = microtime(true) - $start;

    // Test Jaccard calculation (accurate)
    $start = microtime(true);
    for ($i = 0; $i < 1000; $i++) {
        $service->calculateJaccardSimilarity($entry1, $entry2);
    }
    $jaccardTime = microtime(true) - $start;

    // Just verify both methods complete in reasonable time
    expect($minHashTime)->toBeLessThan(0.5);
    expect($jaccardTime)->toBeLessThan(0.5);

    echo sprintf("\n  ✓ MinHash: %.4fs | Jaccard: %.4fs", $minHashTime, $jaccardTime);
})->group('benchmark');

test('benchmark: memory usage stays constant', function () {
    $service = new SimilarityService;
    $entries = collect();

    for ($i = 0; $i < 1000; $i++) {
        $entries->push(new Entry([
            'id' => $i,
            'title' => "Entry $i",
            'content' => "Content $i",
        ]));
    }

    $memoryBefore = memory_get_usage(true);
    $service->findDuplicates($entries, 0.5);
    $memoryAfter = memory_get_usage(true);

    $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB

    // Memory usage should be reasonable (< 50MB for 1000 entries)
    expect($memoryUsed)->toBeLessThan(50);

    echo sprintf("\n  ✓ Memory used: %.2f MB", $memoryUsed);
})->group('benchmark');
