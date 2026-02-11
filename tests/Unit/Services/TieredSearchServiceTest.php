<?php

declare(strict_types=1);

use App\Enums\SearchTier;
use App\Services\EntryMetadataService;
use App\Services\QdrantService;
use App\Services\TieredSearchService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->qdrantService = Mockery::mock(QdrantService::class);
    $this->metadataService = new EntryMetadataService;
    $this->service = new TieredSearchService($this->qdrantService, $this->metadataService);
});

afterEach(function (): void {
    Carbon::setTestNow();
    Mockery::close();
});

describe('search', function (): void {
    it('returns early when confident matches found at working tier', function (): void {
        Carbon::setTestNow('2026-02-10');

        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('test query', ['status' => 'draft'], 20, 'default')
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'score' => 0.95,
                    'title' => 'Working Entry',
                    'content' => 'Recent draft content',
                    'tags' => ['test'],
                    'category' => 'testing',
                    'module' => null,
                    'priority' => 'high',
                    'status' => 'draft',
                    'confidence' => 90,
                    'usage_count' => 5,
                    'created_at' => '2026-02-09T00:00:00+00:00',
                    'updated_at' => '2026-02-09T00:00:00+00:00',
                    'last_verified' => '2026-02-09T00:00:00+00:00',
                    'evidence' => null,
                ],
            ]));

        $results = $this->service->search('test query');

        expect($results)->toHaveCount(1);
        expect($results->first()['tier'])->toBe('working');
        expect($results->first()['tier_label'])->toBe('Working Context');
    });

    it('searches multiple tiers when no confident matches found', function (): void {
        Carbon::setTestNow('2026-02-10');

        // Working tier - no results
        $this->qdrantService->shouldReceive('search')
            ->with('test query', ['status' => 'draft'], 20, 'default')
            ->andReturn(collect([]));

        // Recent tier - no results (nothing within 14 days)
        $this->qdrantService->shouldReceive('search')
            ->with('test query', [], 20, 'default')
            ->andReturn(collect([]));

        // Structured tier - no results
        $this->qdrantService->shouldReceive('search')
            ->with('test query', ['status' => 'validated'], 20, 'default')
            ->andReturn(collect([]));

        // Archive tier - no results
        $this->qdrantService->shouldReceive('search')
            ->with('test query', ['status' => 'deprecated'], 20, 'default')
            ->andReturn(collect([]));

        // All tiers search for fallback (4 more calls)
        $this->qdrantService->shouldReceive('search')
            ->with('test query', ['status' => 'draft'], 20, 'default')
            ->andReturn(collect([]));

        $this->qdrantService->shouldReceive('search')
            ->with('test query', [], 20, 'default')
            ->andReturn(collect([]));

        $this->qdrantService->shouldReceive('search')
            ->with('test query', ['status' => 'validated'], 20, 'default')
            ->andReturn(collect([]));

        $this->qdrantService->shouldReceive('search')
            ->with('test query', ['status' => 'deprecated'], 20, 'default')
            ->andReturn(collect([]));

        $results = $this->service->search('test query');

        expect($results)->toBeEmpty();
    });

    it('forces search on a specific tier when forceTier is set', function (): void {
        Carbon::setTestNow('2026-02-10');

        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('test query', ['status' => 'validated'], 20, 'default')
            ->andReturn(collect([
                [
                    'id' => 'uuid-2',
                    'score' => 0.85,
                    'title' => 'Structured Entry',
                    'content' => 'Validated content',
                    'tags' => [],
                    'category' => 'architecture',
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'validated',
                    'confidence' => 80,
                    'usage_count' => 10,
                    'created_at' => '2026-01-01T00:00:00+00:00',
                    'updated_at' => '2026-02-01T00:00:00+00:00',
                    'last_verified' => '2026-02-01T00:00:00+00:00',
                    'evidence' => null,
                ],
            ]));

        $results = $this->service->search('test query', [], 20, SearchTier::Structured);

        expect($results)->toHaveCount(1);
        expect($results->first()['tier'])->toBe('structured');
        expect($results->first()['tier_label'])->toBe('Structured Storage');
    });

    it('forces search on archive tier', function (): void {
        Carbon::setTestNow('2026-02-10');

        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('old query', ['status' => 'deprecated'], 10, 'default')
            ->andReturn(collect([
                [
                    'id' => 'uuid-3',
                    'score' => 0.70,
                    'title' => 'Archived Entry',
                    'content' => 'Old deprecated content',
                    'tags' => ['legacy'],
                    'category' => 'deployment',
                    'module' => null,
                    'priority' => 'low',
                    'status' => 'deprecated',
                    'confidence' => 30,
                    'usage_count' => 1,
                    'created_at' => '2025-06-01T00:00:00+00:00',
                    'updated_at' => '2025-06-01T00:00:00+00:00',
                    'last_verified' => null,
                    'evidence' => null,
                ],
            ]));

        $results = $this->service->search('old query', [], 10, SearchTier::Archive);

        expect($results)->toHaveCount(1);
        expect($results->first()['tier'])->toBe('archive');
        expect($results->first()['tier_label'])->toBe('Archive');
    });

    it('passes filters through to tier search', function (): void {
        Carbon::setTestNow('2026-02-10');

        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('query', ['tag' => 'php', 'status' => 'draft'], 20, 'default')
            ->andReturn(collect([
                [
                    'id' => 'uuid-4',
                    'score' => 0.90,
                    'title' => 'PHP Working Entry',
                    'content' => 'PHP draft content',
                    'tags' => ['php'],
                    'category' => 'testing',
                    'module' => null,
                    'priority' => 'high',
                    'status' => 'draft',
                    'confidence' => 95,
                    'usage_count' => 2,
                    'created_at' => '2026-02-09T00:00:00+00:00',
                    'updated_at' => '2026-02-09T00:00:00+00:00',
                    'last_verified' => '2026-02-09T00:00:00+00:00',
                    'evidence' => null,
                ],
            ]));

        $results = $this->service->search('query', ['tag' => 'php'], 20, SearchTier::Working);

        expect($results)->toHaveCount(1);
        expect($results->first()['tier'])->toBe('working');
    });

    it('uses custom project namespace', function (): void {
        Carbon::setTestNow('2026-02-10');

        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('query', ['status' => 'validated'], 20, 'my-project')
            ->andReturn(collect([]));

        $results = $this->service->search('query', [], 20, SearchTier::Structured, 'my-project');

        expect($results)->toBeEmpty();
    });
});

describe('searchTier', function (): void {
    it('adds tier and tier_label to results', function (): void {
        Carbon::setTestNow('2026-02-10');

        $this->qdrantService->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'score' => 0.90,
                    'title' => 'Test Entry',
                    'content' => 'Content',
                    'tags' => [],
                    'category' => null,
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 80,
                    'usage_count' => 0,
                    'created_at' => '2026-02-09T00:00:00+00:00',
                    'updated_at' => '2026-02-09T00:00:00+00:00',
                    'last_verified' => '2026-02-09T00:00:00+00:00',
                    'evidence' => null,
                ],
            ]));

        $results = $this->service->searchTier('test', [], 20, SearchTier::Working);

        expect($results->first())->toHaveKeys(['tier', 'tier_label', 'tiered_score']);
        expect($results->first()['tier'])->toBe('working');
        expect($results->first()['tier_label'])->toBe('Working Context');
    });

    it('filters recent tier results by 14-day window', function (): void {
        Carbon::setTestNow('2026-02-10');

        $this->qdrantService->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-recent',
                    'score' => 0.90,
                    'title' => 'Recent Entry',
                    'content' => 'Recent content',
                    'tags' => [],
                    'category' => null,
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'validated',
                    'confidence' => 80,
                    'usage_count' => 0,
                    'created_at' => '2026-02-05T00:00:00+00:00',
                    'updated_at' => '2026-02-05T00:00:00+00:00',
                    'last_verified' => '2026-02-05T00:00:00+00:00',
                    'evidence' => null,
                ],
                [
                    'id' => 'uuid-old',
                    'score' => 0.85,
                    'title' => 'Old Entry',
                    'content' => 'Old content',
                    'tags' => [],
                    'category' => null,
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'validated',
                    'confidence' => 80,
                    'usage_count' => 0,
                    'created_at' => '2025-12-01T00:00:00+00:00',
                    'updated_at' => '2025-12-01T00:00:00+00:00',
                    'last_verified' => '2025-12-01T00:00:00+00:00',
                    'evidence' => null,
                ],
            ]));

        $results = $this->service->searchTier('test', [], 20, SearchTier::Recent);

        expect($results)->toHaveCount(1);
        expect($results->first()['id'])->toBe('uuid-recent');
    });

    it('does not filter non-recent tier by time', function (): void {
        Carbon::setTestNow('2026-02-10');

        $this->qdrantService->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-old-structured',
                    'score' => 0.80,
                    'title' => 'Old Structured Entry',
                    'content' => 'Content from last year',
                    'tags' => [],
                    'category' => null,
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'validated',
                    'confidence' => 70,
                    'usage_count' => 0,
                    'created_at' => '2025-06-01T00:00:00+00:00',
                    'updated_at' => '2025-06-01T00:00:00+00:00',
                    'last_verified' => '2026-01-01T00:00:00+00:00',
                    'evidence' => null,
                ],
            ]));

        $results = $this->service->searchTier('test', [], 20, SearchTier::Structured);

        expect($results)->toHaveCount(1);
    });

    it('sorts results by tiered score descending', function (): void {
        Carbon::setTestNow('2026-02-10');

        $this->qdrantService->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-low',
                    'score' => 0.60,
                    'title' => 'Low Score Entry',
                    'content' => 'Content',
                    'tags' => [],
                    'category' => null,
                    'module' => null,
                    'priority' => 'low',
                    'status' => 'draft',
                    'confidence' => 30,
                    'usage_count' => 0,
                    'created_at' => '2026-01-01T00:00:00+00:00',
                    'updated_at' => '2026-01-01T00:00:00+00:00',
                    'last_verified' => '2026-01-01T00:00:00+00:00',
                    'evidence' => null,
                ],
                [
                    'id' => 'uuid-high',
                    'score' => 0.95,
                    'title' => 'High Score Entry',
                    'content' => 'Content',
                    'tags' => [],
                    'category' => null,
                    'module' => null,
                    'priority' => 'high',
                    'status' => 'draft',
                    'confidence' => 95,
                    'usage_count' => 0,
                    'created_at' => '2026-02-09T00:00:00+00:00',
                    'updated_at' => '2026-02-09T00:00:00+00:00',
                    'last_verified' => '2026-02-09T00:00:00+00:00',
                    'evidence' => null,
                ],
            ]));

        $results = $this->service->searchTier('test', [], 20, SearchTier::Working);

        expect($results->first()['id'])->toBe('uuid-high');
        expect($results->last()['id'])->toBe('uuid-low');
    });

    it('excludes recent tier entries with no date', function (): void {
        Carbon::setTestNow('2026-02-10');

        $this->qdrantService->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-nodate',
                    'score' => 0.90,
                    'title' => 'No Date Entry',
                    'content' => 'Content',
                    'tags' => [],
                    'category' => null,
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 80,
                    'usage_count' => 0,
                    'created_at' => '',
                    'updated_at' => '',
                    'last_verified' => null,
                    'evidence' => null,
                ],
            ]));

        $results = $this->service->searchTier('test', [], 20, SearchTier::Recent);

        expect($results)->toBeEmpty();
    });
});

describe('calculateScore', function (): void {
    it('computes score as relevance * confidence_weight * freshness_decay', function (): void {
        Carbon::setTestNow('2026-02-10');

        $entry = [
            'score' => 0.90,
            'confidence' => 80,
            'created_at' => '2026-02-10T00:00:00+00:00',
            'updated_at' => '2026-02-10T00:00:00+00:00',
            'last_verified' => '2026-02-10T00:00:00+00:00',
        ];

        $score = $this->service->calculateScore($entry);

        // relevance=0.90, confidence_weight=0.80, freshness_decay=1.0 (0 days)
        // 0.90 * 0.80 * 1.0 = 0.72
        expect($score)->toBeGreaterThan(0.71);
        expect($score)->toBeLessThan(0.73);
    });

    it('degrades score for older entries', function (): void {
        Carbon::setTestNow('2026-02-10');

        $fresh = [
            'score' => 0.90,
            'confidence' => 80,
            'created_at' => '2026-02-10T00:00:00+00:00',
            'updated_at' => '2026-02-10T00:00:00+00:00',
            'last_verified' => '2026-02-10T00:00:00+00:00',
        ];

        $stale = [
            'score' => 0.90,
            'confidence' => 80,
            'created_at' => '2025-12-01T00:00:00+00:00',
            'updated_at' => '2025-12-01T00:00:00+00:00',
            'last_verified' => '2025-12-01T00:00:00+00:00',
        ];

        $freshScore = $this->service->calculateScore($fresh);
        $staleScore = $this->service->calculateScore($stale);

        expect($freshScore)->toBeGreaterThan($staleScore);
    });

    it('handles entry with zero score', function (): void {
        Carbon::setTestNow('2026-02-10');

        $entry = [
            'score' => 0.0,
            'confidence' => 90,
            'created_at' => '2026-02-10T00:00:00+00:00',
            'updated_at' => '2026-02-10T00:00:00+00:00',
            'last_verified' => '2026-02-10T00:00:00+00:00',
        ];

        expect($this->service->calculateScore($entry))->toBe(0.0);
    });

    it('handles entry with missing fields gracefully', function (): void {
        Carbon::setTestNow('2026-02-10');

        $entry = [];

        $score = $this->service->calculateScore($entry);

        expect($score)->toBe(0.0);
    });
});

describe('calculateConfidenceWeight', function (): void {
    it('returns normalized confidence as 0-1 value', function (): void {
        $entry = [
            'confidence' => 80,
            'last_verified' => now()->toIso8601String(),
        ];

        expect($this->service->calculateConfidenceWeight($entry))->toBe(0.8);
    });

    it('applies confidence degradation for stale entries', function (): void {
        Carbon::setTestNow('2026-02-10');

        $entry = [
            'confidence' => 80,
            'last_verified' => '2025-10-01T00:00:00+00:00',
        ];

        $weight = $this->service->calculateConfidenceWeight($entry);

        // Stale: 132 days since verification, 42 days over 90 threshold
        // degradation = round(42 * 0.15) = round(6.3) = 6
        // effective = max(10, 80 - 6) = 74
        // weight = 74 / 100 = 0.74
        expect($weight)->toBe(0.74);
    });

    it('returns minimum weight for zero confidence', function (): void {
        $entry = [
            'confidence' => 0,
            'last_verified' => now()->subDays(200)->toIso8601String(),
        ];

        $weight = $this->service->calculateConfidenceWeight($entry);

        // min confidence is 10, so 10/100 = 0.1
        expect($weight)->toBe(0.1);
    });
});

describe('calculateFreshnessDecay', function (): void {
    it('returns 1.0 for entry updated today', function (): void {
        Carbon::setTestNow('2026-02-10');

        $entry = [
            'updated_at' => '2026-02-10T00:00:00+00:00',
        ];

        expect($this->service->calculateFreshnessDecay($entry))->toBe(1.0);
    });

    it('returns 0.5 for entry updated 30 days ago (half-life)', function (): void {
        Carbon::setTestNow('2026-02-10');

        $entry = [
            'updated_at' => '2026-01-11T00:00:00+00:00',
        ];

        $decay = $this->service->calculateFreshnessDecay($entry);

        expect($decay)->toBeGreaterThan(0.49);
        expect($decay)->toBeLessThan(0.51);
    });

    it('returns 0.25 for entry updated 60 days ago', function (): void {
        Carbon::setTestNow('2026-02-10');

        $entry = [
            'updated_at' => '2025-12-12T00:00:00+00:00',
        ];

        $decay = $this->service->calculateFreshnessDecay($entry);

        expect($decay)->toBeGreaterThan(0.24);
        expect($decay)->toBeLessThan(0.26);
    });

    it('falls back to created_at when updated_at is missing', function (): void {
        Carbon::setTestNow('2026-02-10');

        $entry = [
            'created_at' => '2026-02-10T00:00:00+00:00',
        ];

        expect($this->service->calculateFreshnessDecay($entry))->toBe(1.0);
    });

    it('returns 0.5 when no dates available', function (): void {
        $entry = [];

        expect($this->service->calculateFreshnessDecay($entry))->toBe(0.5);
    });

    it('returns 0.5 when dates are empty strings', function (): void {
        $entry = [
            'updated_at' => '',
            'created_at' => '',
        ];

        expect($this->service->calculateFreshnessDecay($entry))->toBe(0.5);
    });
});
