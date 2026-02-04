<?php

declare(strict_types=1);

use App\Services\Bm25SparseEmbeddingService;

uses()->group('sparse-embedding');

beforeEach(function (): void {
    $this->service = new Bm25SparseEmbeddingService;
});

describe('generate', function (): void {
    it('returns empty arrays for empty text', function (): void {
        $result = $this->service->generate('');

        expect($result)->toBe(['indices' => [], 'values' => []]);
    });

    it('returns empty arrays for whitespace-only text', function (): void {
        $result = $this->service->generate('   ');

        expect($result)->toBe(['indices' => [], 'values' => []]);
    });

    it('generates sparse vector with indices and values', function (): void {
        $result = $this->service->generate('Laravel framework testing');

        expect($result)->toHaveKeys(['indices', 'values']);
        expect($result['indices'])->toBeArray();
        expect($result['values'])->toBeArray();
        expect(count($result['indices']))->toBe(count($result['values']));
        expect(count($result['indices']))->toBeGreaterThan(0);
    });

    it('produces consistent hashes for same terms', function (): void {
        $result1 = $this->service->generate('laravel testing');
        $result2 = $this->service->generate('laravel testing');

        expect($result1)->toBe($result2);
    });

    it('filters out stop words', function (): void {
        // "the" and "a" should be filtered out, leaving only meaningful terms
        $result = $this->service->generate('the quick brown fox');

        // Should have indices for 'quick', 'brown', 'fox' (not 'the')
        expect(count($result['indices']))->toBe(3);
    });

    it('filters out short tokens', function (): void {
        // Tokens with 2 or fewer characters should be filtered
        $result = $this->service->generate('is a ab abc testing');

        // Only 'abc' and 'testing' should remain (length > 2 and not stop words)
        expect(count($result['indices']))->toBe(2);
    });

    it('normalizes text to lowercase', function (): void {
        $result1 = $this->service->generate('LARAVEL Testing');
        $result2 = $this->service->generate('laravel testing');

        expect($result1)->toBe($result2);
    });

    it('removes special characters', function (): void {
        $result1 = $this->service->generate('laravel-testing');
        $result2 = $this->service->generate('laravel testing');

        expect($result1)->toBe($result2);
    });

    it('handles term frequencies', function (): void {
        // When a term appears multiple times, it should have higher weight
        $singleResult = $this->service->generate('laravel');
        $doubleResult = $this->service->generate('laravel laravel');

        // Both should have same indices (same term), but double should have higher value
        expect($singleResult['indices'])->toBe($doubleResult['indices']);
        expect($doubleResult['values'][0])->toBeGreaterThan($singleResult['values'][0]);
    });

    it('returns sorted indices', function (): void {
        $result = $this->service->generate('zebra apple banana');

        $indices = $result['indices'];
        $sortedIndices = $indices;
        sort($sortedIndices);

        expect($indices)->toBe($sortedIndices);
    });

    it('produces indices within vocab size bounds', function (): void {
        $customService = new Bm25SparseEmbeddingService(vocabSize: 1000);
        $result = $customService->generate('laravel framework testing patterns architecture');

        foreach ($result['indices'] as $index) {
            expect($index)->toBeGreaterThanOrEqual(0);
            expect($index)->toBeLessThan(1000);
        }
    });

    it('produces positive values', function (): void {
        $result = $this->service->generate('laravel testing framework');

        foreach ($result['values'] as $value) {
            expect($value)->toBeGreaterThan(0.0);
        }
    });

    it('handles unicode characters', function (): void {
        $result = $this->service->generate('cafe testing development');

        expect($result['indices'])->not->toBeEmpty();
        expect($result['values'])->not->toBeEmpty();
    });
});

describe('BM25 parameters', function (): void {
    it('allows custom k1 parameter', function (): void {
        $defaultService = new Bm25SparseEmbeddingService(k1: 1.2);
        $customService = new Bm25SparseEmbeddingService(k1: 2.0);

        $defaultResult = $defaultService->generate('testing testing testing');
        $customResult = $customService->generate('testing testing testing');

        // Different k1 values should produce different scores
        expect($defaultResult['values'])->not->toBe($customResult['values']);
    });

    it('allows custom b parameter', function (): void {
        $defaultService = new Bm25SparseEmbeddingService(b: 0.75);
        $customService = new Bm25SparseEmbeddingService(b: 0.5);

        $defaultResult = $defaultService->generate('testing framework');
        $customResult = $customService->generate('testing framework');

        // Different b values should produce different scores
        expect($defaultResult['values'])->not->toBe($customResult['values']);
    });

    it('allows custom average document length', function (): void {
        $shortDocService = new Bm25SparseEmbeddingService(avgDocLength: 50.0);
        $longDocService = new Bm25SparseEmbeddingService(avgDocLength: 200.0);

        $shortResult = $shortDocService->generate('testing framework');
        $longResult = $longDocService->generate('testing framework');

        // Different avgDocLength should affect scores
        expect($shortResult['values'])->not->toBe($longResult['values']);
    });
});
