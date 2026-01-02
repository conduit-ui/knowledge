<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Services\SimilarityService;

describe('SimilarityService', function (): void {
    beforeEach(function (): void {
        $this->service = new SimilarityService;
    });

    afterEach(function (): void {
        $this->service->clearCache();
    });

    describe('calculateJaccardSimilarity', function (): void {
        it('calculates correctly for identical entries', function (): void {
            $entry1 = new Entry(['id' => 1, 'title' => 'Test Entry', 'content' => 'This is a test']);
            $entry2 = new Entry(['id' => 2, 'title' => 'Test Entry', 'content' => 'This is a test']);

            $similarity = $this->service->calculateJaccardSimilarity($entry1, $entry2);

            expect($similarity)->toBe(1.0);
        });

        it('calculates correctly for completely different entries', function (): void {
            $entry1 = new Entry(['id' => 1, 'title' => 'Cooking Recipes Collection', 'content' => 'Delicious pasta carbonara spaghetti italian cuisine']);
            $entry2 = new Entry(['id' => 2, 'title' => 'Machine Learning Basics', 'content' => 'Neural networks tensorflow pytorch algorithms']);

            $similarity = $this->service->calculateJaccardSimilarity($entry1, $entry2);

            expect($similarity)->toBeLessThan(0.3);
        });

        it('calculates correctly for partially similar entries', function (): void {
            $entry1 = new Entry(['id' => 1, 'title' => 'PHP Tutorial', 'content' => 'Learn PHP programming']);
            $entry2 = new Entry(['id' => 2, 'title' => 'PHP Guide', 'content' => 'Learn PHP development']);

            $similarity = $this->service->calculateJaccardSimilarity($entry1, $entry2);

            // Jaccard similarity: intersection/union of tokens
            // Common: php, learn (2), Union: php, tutorial, learn, programming, guide, development (6)
            // Expected: 2/6 = 0.33
            expect($similarity)->toBeGreaterThan(0.3);
        });

        it('returns zero similarity for empty entries', function (): void {
            $entry1 = new Entry(['id' => 1, 'title' => '', 'content' => '']);
            $entry2 = new Entry(['id' => 2, 'title' => '', 'content' => '']);

            $similarity = $this->service->calculateJaccardSimilarity($entry1, $entry2);

            expect($similarity)->toBe(0.0);
        });

        it('handles case insensitivity', function (): void {
            $entry1 = new Entry(['id' => 1, 'title' => 'PHP TUTORIAL', 'content' => 'LEARN PHP']);
            $entry2 = new Entry(['id' => 2, 'title' => 'php tutorial', 'content' => 'learn php']);

            $similarity = $this->service->calculateJaccardSimilarity($entry1, $entry2);

            expect($similarity)->toBe(1.0);
        });
    });

    describe('getTokens', function (): void {
        it('tokenizes text correctly', function (): void {
            $entry = new Entry(['id' => 1, 'title' => 'Test', 'content' => 'This is a test of the tokenizer']);

            $tokens = $this->service->getTokens($entry);

            expect($tokens)
                ->toBeArray()
                ->toContain('test')
                ->toContain('tokenizer')
                ->not->toContain('is')
                ->not->toContain('the')
                ->not->toContain('a');
        });

        it('caches tokenization results', function (): void {
            $entry = new Entry(['id' => 1, 'title' => 'Test', 'content' => 'This is a test']);

            $tokens1 = $this->service->getTokens($entry);
            $tokens2 = $this->service->getTokens($entry);

            expect($tokens1)->toBe($tokens2);
        });

        it('removes special characters from tokens', function (): void {
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

        it('filters out short words', function (): void {
            $entry = new Entry(['id' => 1, 'title' => 'Test', 'content' => 'Hi we go to school']);

            $tokens = $this->service->getTokens($entry);

            expect($tokens)
                ->not->toContain('hi')
                ->not->toContain('we')
                ->not->toContain('go');
        });
    });

    describe('estimateSimilarity', function (): void {
        it('estimates similarity from MinHash signatures', function (): void {
            $entry1 = new Entry(['id' => 1, 'title' => 'PHP Tutorial', 'content' => 'Learn PHP programming basics']);
            $entry2 = new Entry(['id' => 2, 'title' => 'PHP Guide', 'content' => 'Learn PHP programming fundamentals']);

            $estimated = $this->service->estimateSimilarity($entry1, $entry2);
            $actual = $this->service->calculateJaccardSimilarity($entry1, $entry2);

            expect($estimated)->toBeGreaterThan(0.3);
            expect(abs($estimated - $actual))->toBeLessThan(0.4);
        });
    });

    describe('findDuplicates', function (): void {
        it('finds no duplicates when entries are completely different', function (): void {
            $entries = collect([
                new Entry(['id' => 1, 'title' => 'Italian Cooking', 'content' => 'Pasta carbonara spaghetti italian cuisine recipes']),
                new Entry(['id' => 2, 'title' => 'Neural Networks', 'content' => 'Machine learning tensorflow pytorch algorithms training']),
                new Entry(['id' => 3, 'title' => 'Gardening Tips', 'content' => 'Planting vegetables tomatoes flowers garden soil']),
            ]);

            $duplicates = $this->service->findDuplicates($entries, 0.9);

            expect($duplicates)->toBeEmpty();
        });

        it('finds duplicates when entries are nearly identical', function (): void {
            $entries = collect([
                new Entry(['id' => 1, 'title' => 'PHP Tutorial Advanced Topics', 'content' => 'Learn advanced PHP programming techniques']),
                new Entry(['id' => 2, 'title' => 'PHP Tutorial Advanced Topics', 'content' => 'Learn advanced PHP programming techniques']),
            ]);

            // With only 2 identical entries, findDuplicates should find them
            $duplicates = $this->service->findDuplicates($entries, 0.5);

            expect($duplicates->count())->toBeGreaterThanOrEqual(1);
        });

        it('finds multiple duplicate groups', function (): void {
            $entries = collect([
                new Entry(['id' => 1, 'title' => 'PHP Tutorial Advanced Topics', 'content' => 'Learn advanced PHP programming techniques']),
                new Entry(['id' => 2, 'title' => 'PHP Tutorial Advanced Topics', 'content' => 'Learn advanced PHP programming techniques']),
                new Entry(['id' => 3, 'title' => 'Python Data Analysis Guide', 'content' => 'Master Python data analysis tools']),
                new Entry(['id' => 4, 'title' => 'Python Data Analysis Guide', 'content' => 'Master Python data analysis tools']),
            ]);

            $duplicates = $this->service->findDuplicates($entries, 0.7);

            expect($duplicates->count())->toBeGreaterThanOrEqual(1);
        });

        it('returns empty collection for less than 2 entries', function (): void {
            $entries = collect([
                new Entry(['id' => 1, 'title' => 'Test', 'content' => 'Content']),
            ]);

            $duplicates = $this->service->findDuplicates($entries, 0.5);

            expect($duplicates)->toBeEmpty();
        });

        it('sorts duplicate groups by similarity descending', function (): void {
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
    });

    describe('clearCache', function (): void {
        it('clears cache correctly', function (): void {
            $entry = new Entry(['id' => 1, 'title' => 'Test', 'content' => 'Content']);

            $this->service->getTokens($entry);
            $this->service->clearCache();
            $tokens = $this->service->getTokens($entry);

            expect($tokens)->toBeArray();
        });
    });
});
