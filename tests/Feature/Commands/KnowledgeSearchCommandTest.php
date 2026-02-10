<?php

declare(strict_types=1);

use App\Services\EntryMetadataService;
use App\Services\QdrantService;

beforeEach(function (): void {
    $this->mockQdrant = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->mockQdrant);

    $this->mockMetadata = Mockery::mock(EntryMetadataService::class);
    $this->mockMetadata->shouldReceive('isStale')->andReturn(false);
    $this->mockMetadata->shouldReceive('calculateEffectiveConfidence')->andReturn(80);
    $this->mockMetadata->shouldReceive('confidenceLevel')->andReturn('high');
    $this->app->instance(EntryMetadataService::class, $this->mockMetadata);

    mockProjectDetector();
});

afterEach(function (): void {
    Mockery::close();
});

it('searches entries by keyword in title and content', function (): void {
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->with('timezone', [], 20, 'default')
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
            ],
        ]));

    $this->artisan('search', ['query' => 'timezone'])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry')
        ->expectsOutputToContain('Laravel Timezone Conversion');
});

it('searches entries by tag', function (): void {
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->with('', ['tag' => 'blood.notifications'], 20, 'default')
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
            ],
        ]));

    $this->artisan('search', ['--tag' => 'blood.notifications'])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry');
});

it('searches entries by category', function (): void {
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->with('', ['category' => 'architecture'], 20, 'default')
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
            ],
        ]));

    $this->artisan('search', ['--category' => 'architecture'])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry');
});

it('searches entries by category and module', function (): void {
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->with('', ['category' => 'architecture', 'module' => 'Blood'], 20, 'default')
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
            ],
        ]));

    $this->artisan('search', [
        '--category' => 'architecture',
        '--module' => 'Blood',
    ])->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry');
});

it('searches entries by priority', function (): void {
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->with('', ['priority' => 'critical'], 20, 'default')
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
            ],
        ]));

    $this->artisan('search', ['--priority' => 'critical'])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry');
});

it('searches entries by status', function (): void {
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->with('', ['status' => 'validated'], 20, 'default')
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
            ],
        ]));

    $this->artisan('search', ['--status' => 'validated'])
        ->assertSuccessful()
        ->expectsOutputToContain('Found 1 entry');
});

it('shows message when no results found', function (): void {
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->with('nonexistent', [], 20, 'default')
        ->andReturn(collect([]));

    $this->artisan('search', ['query' => 'nonexistent'])
        ->assertSuccessful()
        ->expectsOutput('No entries found.');
});

it('searches with multiple filters', function (): void {
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->with('', [
            'category' => 'testing',
            'module' => 'Blood',
            'priority' => 'high',
        ], 20, 'default')
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
    $this->mockQdrant->shouldNotReceive('search');

    $this->artisan('search')
        ->assertFailed()
        ->expectsOutput('Please provide at least one search parameter.');
});

it('handles query with all filter types combined', function (): void {
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->with('laravel', [
            'tag' => 'testing',
            'category' => 'architecture',
            'module' => 'Core',
            'priority' => 'high',
            'status' => 'validated',
        ], 20, 'default')
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
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->with('semantic', [], 20, 'default')
        ->andReturn(collect([]));

    $this->artisan('search', [
        'query' => 'semantic',
        '--semantic' => true,
    ])->assertSuccessful();
});

it('handles entries with missing optional fields', function (): void {
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->with('minimal', [], 20, 'default')
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
            ],
        ]));

    $this->artisan('search', ['query' => 'minimal'])
        ->assertSuccessful()
        ->expectsOutputToContain('Category: N/A');
});
