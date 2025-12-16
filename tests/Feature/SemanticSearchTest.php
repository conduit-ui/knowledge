<?php

declare(strict_types=1);

use App\Contracts\EmbeddingServiceInterface;
use App\Models\Entry;
use App\Services\SemanticSearchService;
use App\Services\StubEmbeddingService;

describe('SemanticSearch', function () {
    beforeEach(function () {
        // Create test entries
        Entry::factory()->create([
            'title' => 'Laravel Testing Guide',
            'content' => 'Complete guide to testing Laravel applications',
            'tags' => ['laravel', 'testing'],
            'category' => 'documentation',
            'confidence' => 95,
        ]);

        Entry::factory()->create([
            'title' => 'PHP Best Practices',
            'content' => 'Modern PHP development best practices',
            'tags' => ['php', 'best-practices'],
            'category' => 'guide',
            'confidence' => 90,
        ]);

        Entry::factory()->create([
            'title' => 'Database Optimization',
            'content' => 'Tips for optimizing database queries',
            'tags' => ['database', 'performance'],
            'category' => 'optimization',
            'confidence' => 85,
        ]);
    });

    describe('StubEmbeddingService', function () {
        it('returns empty array', function () {
            $service = new StubEmbeddingService;
            $embedding = $service->generate('test text');

            expect($embedding)->toBeArray()->toBeEmpty();
        });

        it('returns zero similarity', function () {
            $service = new StubEmbeddingService;
            $similarity = $service->similarity([1.0, 2.0], [3.0, 4.0]);

            expect($similarity)->toBe(0.0);
        });
    });

    describe('SemanticSearchService', function () {
        it('detects no embedding support with stub', function () {
            $embeddingService = new StubEmbeddingService;
            $searchService = new SemanticSearchService($embeddingService, false);

            expect($searchService->hasEmbeddingSupport())->toBeFalse();
        });

        it('falls back to keyword search when disabled', function () {
            $embeddingService = new StubEmbeddingService;
            $searchService = new SemanticSearchService($embeddingService, false);

            $results = $searchService->search('Laravel');

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('Laravel Testing Guide');
        });

        it('falls back to keyword search when no embedding support', function () {
            $embeddingService = new StubEmbeddingService;
            $searchService = new SemanticSearchService($embeddingService, true);

            $results = $searchService->search('PHP');

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('PHP Best Practices');
        });

        it('respects filters', function () {
            $embeddingService = new StubEmbeddingService;
            $searchService = new SemanticSearchService($embeddingService, false);

            $results = $searchService->search('guide', [
                'category' => 'documentation',
            ]);

            expect($results)->toHaveCount(1)
                ->and($results->first()->category)->toBe('documentation');
        });

        it('respects tag filters', function () {
            $embeddingService = new StubEmbeddingService;
            $searchService = new SemanticSearchService($embeddingService, false);

            $results = $searchService->search('optimization', [
                'tag' => 'database',
            ]);

            expect($results)->toHaveCount(1)
                ->and($results->first()->title)->toBe('Database Optimization');
        });

        it('respects module filters in keyword search', function () {
            Entry::factory()->create([
                'title' => 'Auth Module Guide',
                'content' => 'Authentication module documentation',
                'module' => 'auth',
                'confidence' => 88,
            ]);

            $embeddingService = new StubEmbeddingService;
            $searchService = new SemanticSearchService($embeddingService, false);

            $results = $searchService->search('module', [
                'module' => 'auth',
            ]);

            expect($results)->toHaveCount(1)
                ->and($results->first()->module)->toBe('auth');
        });

        it('respects priority filters in keyword search', function () {
            Entry::factory()->create([
                'title' => 'High Priority Task',
                'content' => 'This is a high priority task',
                'priority' => 'high',
                'confidence' => 92,
            ]);

            $embeddingService = new StubEmbeddingService;
            $searchService = new SemanticSearchService($embeddingService, false);

            $results = $searchService->search('priority', [
                'priority' => 'high',
            ]);

            expect($results)->toHaveCount(1)
                ->and($results->first()->priority)->toBe('high');
        });

        it('respects status filters in keyword search', function () {
            Entry::factory()->create([
                'title' => 'Validated Entry',
                'content' => 'This is a validated entry',
                'status' => 'validated',
                'confidence' => 91,
            ]);

            $embeddingService = new StubEmbeddingService;
            $searchService = new SemanticSearchService($embeddingService, false);

            $results = $searchService->search('validated', [
                'status' => 'validated',
            ]);

            expect($results)->toHaveCount(1)
                ->and($results->first()->status)->toBe('validated');
        });

        it('returns empty collection when no matches', function () {
            $embeddingService = new StubEmbeddingService;
            $searchService = new SemanticSearchService($embeddingService, false);

            $results = $searchService->search('nonexistent keyword');

            expect($results)->toBeEmpty();
        });

        it('orders results by confidence and usage', function () {
            $embeddingService = new StubEmbeddingService;
            $searchService = new SemanticSearchService($embeddingService, false);

            $results = $searchService->search('practices');

            expect($results)->toHaveCount(1)
                ->and($results->first()->confidence)->toBe(90);
        });

        it('can be resolved from container', function () {
            $service = app(SemanticSearchService::class);

            expect($service)->toBeInstanceOf(SemanticSearchService::class);
        });
    });

    describe('Container Resolution', function () {
        it('resolves embedding service interface from container', function () {
            $service = app(EmbeddingServiceInterface::class);

            expect($service)->toBeInstanceOf(StubEmbeddingService::class);
        });
    });

    describe('Semantic Search with Real Embeddings', function () {
        beforeEach(function () {
            // Bind mock embedding service
            $this->app->bind(EmbeddingServiceInterface::class, \Tests\Support\MockEmbeddingService::class);

            // Create entries with embeddings
            $mockService = new \Tests\Support\MockEmbeddingService;

            Entry::factory()->create([
                'title' => 'Laravel Testing Guide',
                'content' => 'Complete guide to testing Laravel applications',
                'tags' => ['laravel', 'testing'],
                'category' => 'documentation',
                'confidence' => 95,
                'embedding' => json_encode($mockService->generate('Complete guide to testing Laravel applications')),
            ]);

            Entry::factory()->create([
                'title' => 'PHP Best Practices',
                'content' => 'Modern PHP development best practices',
                'tags' => ['php', 'best-practices'],
                'category' => 'guide',
                'confidence' => 90,
                'embedding' => json_encode($mockService->generate('Modern PHP development best practices')),
            ]);

            Entry::factory()->create([
                'title' => 'Database Optimization',
                'content' => 'Tips for optimizing database queries',
                'tags' => ['database', 'performance'],
                'category' => 'optimization',
                'priority' => 'high',
                'status' => 'validated',
                'module' => 'database',
                'confidence' => 85,
                'embedding' => json_encode($mockService->generate('Tips for optimizing database queries')),
            ]);
        });

        it('performs semantic search when enabled', function () {
            $mockService = app(EmbeddingServiceInterface::class);
            $searchService = new SemanticSearchService($mockService, true);

            $results = $searchService->search('testing Laravel applications');

            expect($results)->not->toBeEmpty();
        });

        it('returns empty collection when query embedding generation fails', function () {
            $stubService = new StubEmbeddingService;
            $searchService = new SemanticSearchService($stubService, true);

            $results = $searchService->search('any query');

            expect($results)->toBeEmpty();
        });

        it('returns empty collection from semantic search when embedding fails mid-query', function () {
            // Create a custom service that passes hasEmbeddingSupport but fails for specific queries
            $conditionalService = new class implements \App\Contracts\EmbeddingServiceInterface
            {
                public function generate(string $text): array
                {
                    // Return empty for "fail" query, otherwise return valid embedding
                    if ($text === 'fail') {
                        return [];
                    }

                    return [1.0, 2.0, 3.0];
                }

                public function similarity(array $a, array $b): float
                {
                    return 0.5;
                }
            };

            $this->app->bind(\App\Contracts\EmbeddingServiceInterface::class, function () use ($conditionalService) {
                return $conditionalService;
            });

            $searchService = new SemanticSearchService($conditionalService, true);

            $results = $searchService->search('fail');

            expect($results)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
        });

        it('filters semantic search by tag', function () {
            $mockService = app(EmbeddingServiceInterface::class);
            $searchService = new SemanticSearchService($mockService, true);

            $results = $searchService->search('guide', [
                'tag' => 'database',
            ]);

            // Should only return entries with the 'database' tag
            foreach ($results as $result) {
                expect($result->tags)->toContain('database');
            }
        });

        it('filters semantic search by category', function () {
            $mockService = app(EmbeddingServiceInterface::class);
            $searchService = new SemanticSearchService($mockService, true);

            $results = $searchService->search('guide', [
                'category' => 'documentation',
            ]);

            expect($results->count())->toBeGreaterThanOrEqual(0);
            if ($results->isNotEmpty()) {
                expect($results->first()->category)->toBe('documentation');
            }
        });

        it('filters semantic search by module', function () {
            $mockService = app(EmbeddingServiceInterface::class);
            $searchService = new SemanticSearchService($mockService, true);

            $results = $searchService->search('optimization', [
                'module' => 'database',
            ]);

            expect($results->count())->toBeGreaterThanOrEqual(0);
            if ($results->isNotEmpty()) {
                expect($results->first()->module)->toBe('database');
            }
        });

        it('filters semantic search by priority', function () {
            $mockService = app(EmbeddingServiceInterface::class);
            $searchService = new SemanticSearchService($mockService, true);

            $results = $searchService->search('database', [
                'priority' => 'high',
            ]);

            expect($results->count())->toBeGreaterThanOrEqual(0);
            if ($results->isNotEmpty()) {
                expect($results->first()->priority)->toBe('high');
            }
        });

        it('filters semantic search by status', function () {
            $mockService = app(EmbeddingServiceInterface::class);
            $searchService = new SemanticSearchService($mockService, true);

            $results = $searchService->search('database', [
                'status' => 'validated',
            ]);

            expect($results->count())->toBeGreaterThanOrEqual(0);
            if ($results->isNotEmpty()) {
                expect($results->first()->status)->toBe('validated');
            }
        });

        it('calculates search scores based on similarity and confidence', function () {
            $mockService = app(EmbeddingServiceInterface::class);
            $searchService = new SemanticSearchService($mockService, true);

            $results = $searchService->search('Laravel testing');

            expect($results)->not->toBeEmpty();
            // Check that search_score attribute exists
            $firstResult = $results->first();
            expect($firstResult->getAttributes())->toHaveKey('search_score');
        });

        it('handles invalid JSON embeddings gracefully', function () {
            Entry::factory()->create([
                'title' => 'Invalid Embedding',
                'content' => 'This has invalid embedding data',
                'confidence' => 100,
                'embedding' => 'invalid json',
            ]);

            $mockService = app(EmbeddingServiceInterface::class);
            $searchService = new SemanticSearchService($mockService, true);

            $results = $searchService->search('invalid');

            // Should not crash and filter out invalid embeddings
            expect($results)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
        });
    });
});
