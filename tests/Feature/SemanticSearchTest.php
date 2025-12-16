<?php

declare(strict_types=1);

use App\Contracts\EmbeddingServiceInterface;
use App\Models\Entry;
use App\Services\SemanticSearchService;
use App\Services\StubEmbeddingService;

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

test('stub embedding service returns empty array', function () {
    $service = new StubEmbeddingService;
    $embedding = $service->generate('test text');

    expect($embedding)->toBeArray()->toBeEmpty();
});

test('stub embedding service returns zero similarity', function () {
    $service = new StubEmbeddingService;
    $similarity = $service->similarity([1.0, 2.0], [3.0, 4.0]);

    expect($similarity)->toBe(0.0);
});

test('semantic search service detects no embedding support with stub', function () {
    $embeddingService = new StubEmbeddingService;
    $searchService = new SemanticSearchService($embeddingService, false);

    expect($searchService->hasEmbeddingSupport())->toBeFalse();
});

test('semantic search falls back to keyword search when disabled', function () {
    $embeddingService = new StubEmbeddingService;
    $searchService = new SemanticSearchService($embeddingService, false);

    $results = $searchService->search('Laravel');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Laravel Testing Guide');
});

test('semantic search falls back to keyword search when no embedding support', function () {
    $embeddingService = new StubEmbeddingService;
    $searchService = new SemanticSearchService($embeddingService, true);

    $results = $searchService->search('PHP');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('PHP Best Practices');
});

test('semantic search respects filters', function () {
    $embeddingService = new StubEmbeddingService;
    $searchService = new SemanticSearchService($embeddingService, false);

    $results = $searchService->search('guide', [
        'category' => 'documentation',
    ]);

    expect($results)->toHaveCount(1)
        ->and($results->first()->category)->toBe('documentation');
});

test('semantic search respects tag filters', function () {
    $embeddingService = new StubEmbeddingService;
    $searchService = new SemanticSearchService($embeddingService, false);

    $results = $searchService->search('optimization', [
        'tag' => 'database',
    ]);

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Database Optimization');
});

test('semantic search returns empty collection when no matches', function () {
    $embeddingService = new StubEmbeddingService;
    $searchService = new SemanticSearchService($embeddingService, false);

    $results = $searchService->search('nonexistent keyword');

    expect($results)->toBeEmpty();
});

test('semantic search orders results by confidence and usage', function () {
    $embeddingService = new StubEmbeddingService;
    $searchService = new SemanticSearchService($embeddingService, false);

    $results = $searchService->search('practices');

    expect($results)->toHaveCount(1)
        ->and($results->first()->confidence)->toBe(90);
});

test('semantic search service can be resolved from container', function () {
    $service = app(SemanticSearchService::class);

    expect($service)->toBeInstanceOf(SemanticSearchService::class);
});

test('embedding service interface can be resolved from container', function () {
    $service = app(EmbeddingServiceInterface::class);

    expect($service)->toBeInstanceOf(StubEmbeddingService::class);
});
