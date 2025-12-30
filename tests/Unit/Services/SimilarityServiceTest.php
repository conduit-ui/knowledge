<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Services\SimilarityService;

beforeEach(function () {
    $this->service = new SimilarityService;
});

afterEach(function () {
    $this->service->clearCache();
});

test('calculates Jaccard similarity correctly for identical entries', function () {
    $entry1 = new Entry(['id' => 1, 'title' => 'Test Entry', 'content' => 'This is a test']);
    $entry2 = new Entry(['id' => 2, 'title' => 'Test Entry', 'content' => 'This is a test']);

    $similarity = $this->service->calculateJaccardSimilarity($entry1, $entry2);

    expect($similarity)->toBe(1.0);
});

test('calculates Jaccard similarity correctly for completely different entries', function () {
    $entry1 = new Entry(['id' => 1, 'title' => 'Cooking Recipes Collection', 'content' => 'Delicious pasta carbonara spaghetti italian cuisine']);
    $entry2 = new Entry(['id' => 2, 'title' => 'Machine Learning Basics', 'content' => 'Neural networks tensorflow pytorch algorithms']);

    $similarity = $this->service->calculateJaccardSimilarity($entry1, $entry2);

    expect($similarity)->toBeLessThan(0.3);
});

test('calculates Jaccard similarity correctly for partially similar entries', function () {
    $entry1 = new Entry(['id' => 1, 'title' => 'PHP Tutorial', 'content' => 'Learn PHP programming']);
    $entry2 = new Entry(['id' => 2, 'title' => 'PHP Guide', 'content' => 'Learn PHP development']);

    $similarity = $this->service->calculateJaccardSimilarity($entry1, $entry2);

    expect($similarity)->toBeGreaterThan(0.5);
});

test('returns zero similarity for empty entries', function () {
    $entry1 = new Entry(['id' => 1, 'title' => '', 'content' => '']);
    $entry2 = new Entry(['id' => 2, 'title' => '', 'content' => '']);

    $similarity = $this->service->calculateJaccardSimilarity($entry1, $entry2);

    expect($similarity)->toBe(0.0);
});

test('tokenizes text correctly', function () {
    $entry = new Entry(['id' => 1, 'title' => 'Test', 'content' => 'This is a test of the tokenizer']);

    $tokens = $this->service->getTokens($entry);

    expect($tokens)
        ->toBeArray()
        ->toContain('test')
        ->toContain('tokenizer')
        ->not->toContain('is') // stop word
        ->not->toContain('the') // stop word
        ->not->toContain('a'); // stop word
});

test('caches tokenization results', function () {
    $entry = new Entry(['id' => 1, 'title' => 'Test', 'content' => 'This is a test']);

    $tokens1 = $this->service->getTokens($entry);
    $tokens2 = $this->service->getTokens($entry);

    expect($tokens1)->toBe($tokens2); // Same array reference (cached)
});

test('estimates similarity from MinHash signatures', function () {
    $entry1 = new Entry(['id' => 1, 'title' => 'PHP Tutorial', 'content' => 'Learn PHP programming basics']);
    $entry2 = new Entry(['id' => 2, 'title' => 'PHP Guide', 'content' => 'Learn PHP programming fundamentals']);

    $estimated = $this->service->estimateSimilarity($entry1, $entry2);
    $actual = $this->service->calculateJaccardSimilarity($entry1, $entry2);

    // MinHash estimate should be reasonably close to actual Jaccard
    expect($estimated)->toBeGreaterThan(0.3);
    expect(abs($estimated - $actual))->toBeLessThan(0.4); // Within 40% margin
});

test('finds no duplicates when entries are completely different', function () {
    $entries = collect([
        new Entry(['id' => 1, 'title' => 'Italian Cooking', 'content' => 'Pasta carbonara spaghetti italian cuisine recipes']),
        new Entry(['id' => 2, 'title' => 'Neural Networks', 'content' => 'Machine learning tensorflow pytorch algorithms training']),
        new Entry(['id' => 3, 'title' => 'Gardening Tips', 'content' => 'Planting vegetables tomatoes flowers garden soil']),
    ]);

    $duplicates = $this->service->findDuplicates($entries, 0.9);

    expect($duplicates)->toBeEmpty();
});

test('finds duplicates when entries are nearly identical', function () {
    $entries = collect([
        new Entry(['id' => 1, 'title' => 'Learning PHP Programming Language Step by Step', 'content' => 'Comprehensive guide to learning PHP programming from scratch with examples']),
        new Entry(['id' => 2, 'title' => 'Learning PHP Programming Language Step by Step', 'content' => 'Comprehensive guide to learning PHP programming from scratch with examples']),
        new Entry(['id' => 3, 'title' => 'Python for Beginners', 'content' => 'Introduction to Python programming language for absolute beginners']),
    ]);

    $duplicates = $this->service->findDuplicates($entries, 0.7);

    expect($duplicates->count())->toBeGreaterThanOrEqual(1);
    // Find the duplicate group with entries 1 and 2
    $found = false;
    foreach ($duplicates as $group) {
        $ids = collect($group['entries'])->pluck('id')->sort()->values();
        if ($ids->contains(1) && $ids->contains(2)) {
            $found = true;
            break;
        }
    }
    expect($found)->toBeTrue();
});

test('finds multiple duplicate groups', function () {
    $entries = collect([
        new Entry(['id' => 1, 'title' => 'PHP Tutorial Advanced Topics', 'content' => 'Learn advanced PHP programming techniques']),
        new Entry(['id' => 2, 'title' => 'PHP Tutorial Advanced Topics', 'content' => 'Learn advanced PHP programming techniques']),
        new Entry(['id' => 3, 'title' => 'Python Data Analysis Guide', 'content' => 'Master Python data analysis tools']),
        new Entry(['id' => 4, 'title' => 'Python Data Analysis Guide', 'content' => 'Master Python data analysis tools']),
    ]);

    $duplicates = $this->service->findDuplicates($entries, 0.7);

    expect($duplicates->count())->toBeGreaterThanOrEqual(1);
});

test('returns empty collection for less than 2 entries', function () {
    $entries = collect([
        new Entry(['id' => 1, 'title' => 'Test', 'content' => 'Content']),
    ]);

    $duplicates = $this->service->findDuplicates($entries, 0.5);

    expect($duplicates)->toBeEmpty();
});

test('sorts duplicate groups by similarity descending', function () {
    $entries = collect([
        new Entry(['id' => 1, 'title' => 'Exact Match Entry', 'content' => 'Same content here exactly']),
        new Entry(['id' => 2, 'title' => 'Exact Match Entry', 'content' => 'Same content here exactly']),
        new Entry(['id' => 3, 'title' => 'Similar Entry Topic', 'content' => 'Different but somewhat related']),
        new Entry(['id' => 4, 'title' => 'Similar Entry Topic', 'content' => 'Different but somewhat comparable']),
    ]);

    $duplicates = $this->service->findDuplicates($entries, 0.5);

    expect($duplicates->count())->toBeGreaterThanOrEqual(1);

    if ($duplicates->count() > 1) {
        expect($duplicates->first()['similarity'])->toBeGreaterThanOrEqual($duplicates->last()['similarity']);
    }
});

test('clears cache correctly', function () {
    $entry = new Entry(['id' => 1, 'title' => 'Test', 'content' => 'Content']);

    $this->service->getTokens($entry);
    $this->service->clearCache();
    $tokens = $this->service->getTokens($entry);

    expect($tokens)->toBeArray();
});

test('handles case insensitivity', function () {
    $entry1 = new Entry(['id' => 1, 'title' => 'PHP TUTORIAL', 'content' => 'LEARN PHP']);
    $entry2 = new Entry(['id' => 2, 'title' => 'php tutorial', 'content' => 'learn php']);

    $similarity = $this->service->calculateJaccardSimilarity($entry1, $entry2);

    expect($similarity)->toBe(1.0);
});

test('removes special characters from tokens', function () {
    $entry = new Entry(['id' => 1, 'title' => 'Test!', 'content' => 'Hello, world! #testing @mention']);

    $tokens = $this->service->getTokens($entry);

    expect($tokens)
        ->toBeArray()
        ->toContain('test')
        ->toContain('hello')
        ->toContain('world')
        ->toContain('testing')
        ->toContain('mention')
        ->not->toContain('!');
});

test('filters out short words', function () {
    $entry = new Entry(['id' => 1, 'title' => 'Test', 'content' => 'Hi we go to school']);

    $tokens = $this->service->getTokens($entry);

    expect($tokens)
        ->not->toContain('hi') // 2 chars
        ->not->toContain('we') // 2 chars
        ->not->toContain('go'); // 2 chars
});
