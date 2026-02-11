<?php

declare(strict_types=1);

use App\Services\EntryMetadataService;
use App\Services\TieredSearchService;

beforeEach(function (): void {
    $this->mockTieredSearch = Mockery::mock(TieredSearchService::class);
    $this->app->instance(TieredSearchService::class, $this->mockTieredSearch);

    $this->mockMetadata = Mockery::mock(EntryMetadataService::class);
    $this->mockMetadata->shouldReceive('isStale')->andReturn(false);
    $this->mockMetadata->shouldReceive('calculateEffectiveConfidence')->andReturn(80);
    $this->mockMetadata->shouldReceive('confidenceLevel')->andReturn('high');
    $this->app->instance(EntryMetadataService::class, $this->mockMetadata);
});

afterEach(function (): void {
    Mockery::close();
});

it('searches entries by keyword in title and content', function (): void {
    $this->mockTieredSearch->shouldReceive('search')
        ->once()
        ->with('timezone', [], 20, null)
        ->andReturn(collect([
            [
                'id' => 'entry-1',
                'title' => 'Laravel Timezone Conversion',
                'content' => 'How to handle timezone conversion',
                'category' => 'architecture',
                'priority' => 'high',
                'confidence' => 90,
                'module' => null,
                'tags' => ['laravel', 'timezone'],
                'score' => 0.95,
                'status' => 'validated',
                'usage_count' => 0,
                'created_at' => '2025-01-01T00:00:00Z',
                'updated_at' => '2025-01-01T00:00:00Z',
                'tier' => 'working',
                'tier_label' => 'Working Context',
                'tiered_score' => 0.85,
            ],
        ]));

    $this->artisan('search', ['query' => 'timezone'])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry')
        ->expectsOutputToContain('Laravel Timezone Conversion');
});

it('searches entries by tag', function (): void {
    $this->mockTieredSearch->shouldReceive('search')
        ->once()
        ->with('', ['tag' => 'blood.notifications'], 20, null)
        ->andReturn(collect([
            [
                'id' => 'entry-1',
                'title' => 'Blood Notifications',
                'content' => 'Notification system',
                'category' => 'architecture',
                'priority' => 'medium',
                'confidence' => 80,
                'module' => 'Blood',
                'tags' => ['blood.notifications', 'laravel'],
                'score' => 0.85,
                'status' => 'draft',
                'usage_count' => 0,
                'created_at' => '2025-01-01T00:00:00Z',
                'updated_at' => '2025-01-01T00:00:00Z',
                'tier' => 'working',
                'tier_label' => 'Working Context',
                'tiered_score' => 0.68,
            ],
        ]));

    $this->artisan('search', ['--tag' => 'blood.notifications'])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry');
});

it('searches entries by category', function (): void {
    $this->mockTieredSearch->shouldReceive('search')
        ->once()
        ->with('', ['category' => 'architecture'], 20, null)
        ->andReturn(collect([
            [
                'id' => 'entry-1',
                'title' => 'Architecture Entry',
                'content' => 'Architecture details',
                'category' => 'architecture',
                'priority' => 'high',
                'confidence' => 85,
                'module' => null,
                'tags' => [],
                'score' => 0.9,
                'status' => 'validated',
                'usage_count' => 0,
                'created_at' => '2025-01-01T00:00:00Z',
                'updated_at' => '2025-01-01T00:00:00Z',
                'tier' => 'structured',
                'tier_label' => 'Structured Storage',
                'tiered_score' => 0.76,
            ],
        ]));

    $this->artisan('search', ['--category' => 'architecture'])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry');
});

it('searches entries by category and module', function (): void {
    $this->mockTieredSearch->shouldReceive('search')
        ->once()
        ->with('', ['category' => 'architecture', 'module' => 'Blood'], 20, null)
        ->andReturn(collect([
            [
                'id' => 'entry-1',
                'title' => 'Blood Architecture',
                'content' => 'Blood module architecture',
                'category' => 'architecture',
                'priority' => 'high',
                'confidence' => 90,
                'module' => 'Blood',
                'tags' => [],
                'score' => 0.92,
                'status' => 'validated',
                'usage_count' => 0,
                'created_at' => '2025-01-01T00:00:00Z',
                'updated_at' => '2025-01-01T00:00:00Z',
                'tier' => 'structured',
                'tier_label' => 'Structured Storage',
                'tiered_score' => 0.82,
            ],
        ]));

    $this->artisan('search', [
        '--category' => 'architecture',
        '--module' => 'Blood',
    ])->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry');
});

it('searches entries by priority', function (): void {
    $this->mockTieredSearch->shouldReceive('search')
        ->once()
        ->with('', ['priority' => 'critical'], 20, null)
        ->andReturn(collect([
            [
                'id' => 'entry-1',
                'title' => 'Critical Entry',
                'content' => 'Critical issue',
                'category' => 'security',
                'priority' => 'critical',
                'confidence' => 95,
                'module' => null,
                'tags' => [],
                'score' => 0.98,
                'status' => 'validated',
                'usage_count' => 0,
                'created_at' => '2025-01-01T00:00:00Z',
                'updated_at' => '2025-01-01T00:00:00Z',
                'tier' => 'structured',
                'tier_label' => 'Structured Storage',
                'tiered_score' => 0.93,
            ],
        ]));

    $this->artisan('search', ['--priority' => 'critical'])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry');
});

it('searches entries by status', function (): void {
    $this->mockTieredSearch->shouldReceive('search')
        ->once()
        ->with('', ['status' => 'validated'], 20, null)
        ->andReturn(collect([
            [
                'id' => 'entry-1',
                'title' => 'Validated Entry',
                'content' => 'Validated content',
                'category' => 'testing',
                'priority' => 'medium',
                'confidence' => 85,
                'module' => null,
                'tags' => [],
                'score' => 0.88,
                'status' => 'validated',
                'usage_count' => 0,
                'created_at' => '2025-01-01T00:00:00Z',
                'updated_at' => '2025-01-01T00:00:00Z',
                'tier' => 'structured',
                'tier_label' => 'Structured Storage',
                'tiered_score' => 0.74,
            ],
        ]));

    $this->artisan('search', ['--status' => 'validated'])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry');
});

it('shows message when no results found', function (): void {
    $this->mockTieredSearch->shouldReceive('search')
        ->once()
        ->with('nonexistent', [], 20, null)
        ->andReturn(collect([]));

    $this->artisan('search', ['query' => 'nonexistent'])
        ->assertSuccessful()
        ->expectsOutput('No entries found.');
});

it('searches with multiple filters', function (): void {
    $this->mockTieredSearch->shouldReceive('search')
        ->once()
        ->with('', [
            'category' => 'testing',
            'module' => 'Blood',
            'priority' => 'high',
        ], 20, null)
        ->andReturn(collect([
            [
                'id' => 'entry-1',
                'title' => 'Laravel Testing Best Practices',
                'content' => 'Testing content',
                'category' => 'testing',
                'priority' => 'high',
                'confidence' => 90,
                'module' => 'Blood',
                'tags' => ['laravel', 'pest'],
                'score' => 0.93,
                'status' => 'validated',
                'usage_count' => 0,
                'created_at' => '2025-01-01T00:00:00Z',
                'updated_at' => '2025-01-01T00:00:00Z',
                'tier' => 'structured',
                'tier_label' => 'Structured Storage',
                'tiered_score' => 0.83,
            ],
        ]));

    $this->artisan('search', [
        '--category' => 'testing',
        '--module' => 'Blood',
        '--priority' => 'high',
    ])->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry');
});

it('requires at least one search parameter', function (): void {
    $this->mockTieredSearch->shouldNotReceive('search');

    $this->artisan('search')
        ->assertFailed()
        ->expectsOutput('Please provide at least one search parameter.');
});

it('handles query with all filter types combined', function (): void {
    $this->mockTieredSearch->shouldReceive('search')
        ->once()
        ->with('laravel', [
            'tag' => 'testing',
            'category' => 'architecture',
            'module' => 'Core',
            'priority' => 'high',
            'status' => 'validated',
        ], 20, null)
        ->andReturn(collect([]));

    $this->artisan('search', [
        'query' => 'laravel',
        '--tag' => 'testing',
        '--category' => 'architecture',
        '--module' => 'Core',
        '--priority' => 'high',
        '--status' => 'validated',
    ])->assertSuccessful()
        ->expectsOutput('No entries found.');
});

it('uses semantic search by default', function (): void {
    $this->mockTieredSearch->shouldReceive('search')
        ->once()
        ->with('semantic', [], 20, null)
        ->andReturn(collect([]));

    $this->artisan('search', [
        'query' => 'semantic',
        '--semantic' => true,
    ])->assertSuccessful();
});

it('handles entries with missing optional fields', function (): void {
    $this->mockTieredSearch->shouldReceive('search')
        ->once()
        ->with('minimal', [], 20, null)
        ->andReturn(collect([
            [
                'id' => 'minimal-entry',
                'title' => 'Minimal',
                'content' => 'Short',
                'category' => null,
                'priority' => 'medium',
                'confidence' => 0,
                'module' => null,
                'tags' => [],
                'score' => 0.5,
                'status' => 'draft',
                'usage_count' => 0,
                'created_at' => '2025-01-01T00:00:00Z',
                'updated_at' => '2025-01-01T00:00:00Z',
                'tier' => 'working',
                'tier_label' => 'Working Context',
                'tiered_score' => 0.05,
            ],
        ]));

    $this->artisan('search', ['query' => 'minimal'])
        ->assertSuccessful()
        ->expectsOutputToContain('Category: N/A');
});

describe('--tier flag', function (): void {
    it('passes working tier to tiered search', function (): void {
        $this->mockTieredSearch->shouldReceive('search')
            ->once()
            ->with('query', [], 20, \App\Enums\SearchTier::Working)
            ->andReturn(collect([]));

        $this->artisan('search', ['query' => 'query', '--tier' => 'working'])
            ->assertSuccessful()
            ->expectsOutput('No entries found.');
    });

    it('passes recent tier to tiered search', function (): void {
        $this->mockTieredSearch->shouldReceive('search')
            ->once()
            ->with('query', [], 20, \App\Enums\SearchTier::Recent)
            ->andReturn(collect([]));

        $this->artisan('search', ['query' => 'query', '--tier' => 'recent'])
            ->assertSuccessful()
            ->expectsOutput('No entries found.');
    });

    it('passes structured tier to tiered search', function (): void {
        $this->mockTieredSearch->shouldReceive('search')
            ->once()
            ->with('query', [], 20, \App\Enums\SearchTier::Structured)
            ->andReturn(collect([]));

        $this->artisan('search', ['query' => 'query', '--tier' => 'structured'])
            ->assertSuccessful()
            ->expectsOutput('No entries found.');
    });

    it('passes archive tier to tiered search', function (): void {
        $this->mockTieredSearch->shouldReceive('search')
            ->once()
            ->with('query', [], 20, \App\Enums\SearchTier::Archive)
            ->andReturn(collect([]));

        $this->artisan('search', ['query' => 'query', '--tier' => 'archive'])
            ->assertSuccessful()
            ->expectsOutput('No entries found.');
    });

    it('rejects invalid tier value', function (): void {
        $this->mockTieredSearch->shouldNotReceive('search');

        $this->artisan('search', ['query' => 'query', '--tier' => 'invalid'])
            ->assertFailed();
    });

    it('displays tier label in results', function (): void {
        $this->mockTieredSearch->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Test Entry',
                    'content' => 'Content',
                    'category' => 'testing',
                    'priority' => 'medium',
                    'confidence' => 80,
                    'module' => null,
                    'tags' => [],
                    'score' => 0.90,
                    'status' => 'draft',
                    'usage_count' => 0,
                    'created_at' => '2026-02-09T00:00:00Z',
                    'updated_at' => '2026-02-09T00:00:00Z',
                    'tier' => 'working',
                    'tier_label' => 'Working Context',
                    'tiered_score' => 0.72,
                ],
            ]));

        $this->artisan('search', ['query' => 'test', '--tier' => 'working'])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 1 entry')
            ->expectsOutputToContain('Test Entry');
    });

    it('displays ranked score in results', function (): void {
        $this->mockTieredSearch->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Test Entry',
                    'content' => 'Content',
                    'category' => 'testing',
                    'priority' => 'medium',
                    'confidence' => 80,
                    'module' => null,
                    'tags' => [],
                    'score' => 0.90,
                    'status' => 'validated',
                    'usage_count' => 0,
                    'created_at' => '2025-01-01T00:00:00Z',
                    'updated_at' => '2025-01-01T00:00:00Z',
                    'tier' => 'structured',
                    'tier_label' => 'Structured Storage',
                    'tiered_score' => 0.65,
                ],
            ]));

        $this->artisan('search', ['query' => 'test'])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 1 entry')
            ->expectsOutputToContain('Test Entry');
    });
});
