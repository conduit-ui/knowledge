<?php

declare(strict_types=1);

use App\Contracts\FullTextSearchInterface;
use App\Enums\ObservationType;
use App\Models\Observation;
use App\Models\Session;
use App\Services\QdrantService;

beforeEach(function () {
    $this->mockQdrant = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->mockQdrant);

    $this->mockFts = Mockery::mock(FullTextSearchInterface::class);
    $this->app->instance(FullTextSearchInterface::class, $this->mockFts);
});

afterEach(function () {
    Mockery::close();
});

it('searches entries by keyword in title and content', function () {
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

it('searches entries by tag', function () {
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

it('searches entries by category', function () {
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

it('searches entries by category and module', function () {
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

it('searches entries by priority', function () {
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

it('searches entries by status', function () {
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

it('shows message when no results found', function () {
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->with('nonexistent', [], 20, 'default')
        ->andReturn(collect([]));

    $this->artisan('search', ['query' => 'nonexistent'])
        ->assertSuccessful()
        ->expectsOutput('No entries found.');
});

it('searches with multiple filters', function () {
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

it('requires at least one search parameter', function () {
    $this->mockQdrant->shouldNotReceive('search');

    $this->artisan('search')
        ->assertFailed()
        ->expectsOutput('Please provide at least one search parameter.');
});

it('displays entry details with score', function () {
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->andReturn(collect([
            [
                'id' => 'test-123',
                'title' => 'Test Entry',
                'content' => 'This is a very long content that exceeds 100 characters to test the truncation feature in the search output display',
                'category' => 'testing',
                'priority' => 'high',
                'confidence' => 85,
                'module' => 'TestModule',
                'tags' => ['tag1', 'tag2'],
                'score' => 0.92,
                'status' => 'validated',
                'usage_count' => 5,
                'created_at' => '2025-01-01T00:00:00Z',
                'updated_at' => '2025-01-01T00:00:00Z',
            ],
        ]));

    $this->artisan('search', ['query' => 'test'])
        ->assertSuccessful()
        ->expectsOutputToContain('[test-123]')
        ->expectsOutputToContain('Test Entry')
        ->expectsOutputToContain('score: 0.92')
        ->expectsOutputToContain('Category: testing | Priority: high | Confidence: 85%')
        ->expectsOutputToContain('Module: TestModule')
        ->expectsOutputToContain('Tags: tag1, tag2')
        ->expectsOutputToContain('...');
});

it('handles entries with missing optional fields', function () {
    $this->mockQdrant->shouldReceive('search')
        ->once()
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

    $this->artisan('search', ['query' => 'test'])
        ->assertSuccessful()
        ->expectsOutputToContain('Category: N/A');
});

describe('--observations flag', function (): void {
    it('searches observations instead of entries', function (): void {
        $session = Session::factory()->create();

        $observation = Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Authentication Bug Fix',
            'type' => ObservationType::Bugfix,
            'narrative' => 'Fixed OAuth bug',
        ]);

        $this->mockFts->shouldReceive('searchObservations')
            ->once()
            ->with('authentication')
            ->andReturn(collect([$observation]));

        $this->mockQdrant->shouldNotReceive('search');

        $this->artisan('search', [
            'query' => 'authentication',
            '--observations' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Found 1 observation');
    });

    it('shows no observations message when none found', function (): void {
        $this->mockFts->shouldReceive('searchObservations')
            ->once()
            ->with('nonexistent')
            ->andReturn(collect([]));

        $this->artisan('search', [
            'query' => 'nonexistent',
            '--observations' => true,
        ])->assertSuccessful()
            ->expectsOutput('No observations found.');
    });

    it('displays observation type, title, concept, and created date', function (): void {
        $session = Session::factory()->create();

        $observation = Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Bug Fix',
            'type' => ObservationType::Bugfix,
            'concept' => 'Authentication',
            'narrative' => 'Fixed auth bug',
        ]);

        $this->mockFts->shouldReceive('searchObservations')
            ->once()
            ->with('bug')
            ->andReturn(collect([$observation]));

        $output = $this->artisan('search', [
            'query' => 'bug',
            '--observations' => true,
        ]);

        $output->assertSuccessful()
            ->expectsOutputToContain('Bug Fix')
            ->expectsOutputToContain('Type: bugfix')
            ->expectsOutputToContain('Concept: Authentication');
    });

    it('requires query when using observations flag', function (): void {
        $this->mockFts->shouldNotReceive('searchObservations');

        $this->artisan('search', [
            '--observations' => true,
        ])->assertFailed()
            ->expectsOutput('Please provide a search query when using --observations.');
    });

    it('counts observations correctly', function (): void {
        $session = Session::factory()->create();

        $observations = Observation::factory(3)->create([
            'session_id' => $session->id,
            'title' => 'Test Observation',
            'narrative' => 'Test narrative',
        ]);

        $this->mockFts->shouldReceive('searchObservations')
            ->once()
            ->with('test')
            ->andReturn($observations);

        $this->artisan('search', [
            'query' => 'test',
            '--observations' => true,
        ])->assertSuccessful()
            ->expectsOutput('Found 3 observations');
    });

    it('truncates long observation narratives', function (): void {
        $session = Session::factory()->create();

        $longNarrative = str_repeat('This is a very long narrative. ', 10);

        $observation = Observation::factory()->create([
            'session_id' => $session->id,
            'title' => 'Long Narrative Observation',
            'narrative' => $longNarrative,
        ]);

        $this->mockFts->shouldReceive('searchObservations')
            ->once()
            ->with('narrative')
            ->andReturn(collect([$observation]));

        $this->artisan('search', [
            'query' => 'narrative',
            '--observations' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('...');
    });
});

it('handles query with all filter types combined', function () {
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

it('uses semantic search by default', function () {
    $this->mockQdrant->shouldReceive('search')
        ->once()
        ->andReturn(collect([]));

    $this->artisan('search', [
        'query' => 'test',
        '--semantic' => true,
    ])->assertSuccessful();
});
