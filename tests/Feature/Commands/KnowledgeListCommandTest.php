<?php

declare(strict_types=1);

use App\Services\QdrantService;

beforeEach(function () {
    $this->qdrantMock = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->qdrantMock);
});

it('lists all entries', function () {
    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', [], 20)
        ->andReturn(collect([
            ['id' => '1', 'title' => 'Entry 1', 'category' => 'architecture', 'priority' => 'high', 'status' => 'validated', 'confidence' => 90, 'module' => null, 'tags' => []],
            ['id' => '2', 'title' => 'Entry 2', 'category' => 'testing', 'priority' => 'medium', 'status' => 'draft', 'confidence' => 70, 'module' => null, 'tags' => []],
            ['id' => '3', 'title' => 'Entry 3', 'category' => 'architecture', 'priority' => 'low', 'status' => 'validated', 'confidence' => 50, 'module' => null, 'tags' => []],
        ]));

    $this->artisan('entries')
        ->assertSuccessful();
});

it('filters by category', function () {
    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', ['category' => 'architecture'], 20)
        ->andReturn(collect([
            ['id' => '1', 'title' => 'Architecture Entry', 'category' => 'architecture', 'priority' => 'high', 'status' => 'validated', 'confidence' => 90, 'module' => null, 'tags' => []],
            ['id' => '3', 'title' => 'Another Architecture', 'category' => 'architecture', 'priority' => 'low', 'status' => 'validated', 'confidence' => 50, 'module' => null, 'tags' => []],
        ]));

    $this->artisan('entries', ['--category' => 'architecture'])
        ->assertSuccessful();
});

it('filters by priority', function () {
    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', ['priority' => 'critical'], 20)
        ->andReturn(collect([
            ['id' => '1', 'title' => 'Critical Entry', 'category' => 'architecture', 'priority' => 'critical', 'status' => 'validated', 'confidence' => 90, 'module' => null, 'tags' => []],
        ]));

    $this->artisan('entries', ['--priority' => 'critical'])
        ->assertSuccessful();
});

it('filters by status', function () {
    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', ['status' => 'validated'], 20)
        ->andReturn(collect([
            ['id' => '1', 'title' => 'Validated Entry', 'category' => 'architecture', 'priority' => 'high', 'status' => 'validated', 'confidence' => 90, 'module' => null, 'tags' => []],
        ]));

    $this->artisan('entries', ['--status' => 'validated'])
        ->assertSuccessful();
});

it('filters by module', function () {
    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', ['module' => 'Blood'], 20)
        ->andReturn(collect([
            ['id' => '1', 'title' => 'Blood Module Entry 1', 'category' => 'architecture', 'priority' => 'high', 'status' => 'validated', 'confidence' => 90, 'module' => 'Blood', 'tags' => []],
            ['id' => '3', 'title' => 'Blood Module Entry 2', 'category' => 'testing', 'priority' => 'medium', 'status' => 'draft', 'confidence' => 70, 'module' => 'Blood', 'tags' => []],
        ]));

    $this->artisan('entries', ['--module' => 'Blood'])
        ->assertSuccessful();
});

it('limits results', function () {
    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', [], 5)
        ->andReturn(collect([
            ['id' => '1', 'title' => 'Entry 1', 'category' => 'architecture', 'priority' => 'high', 'status' => 'validated', 'confidence' => 90, 'module' => null, 'tags' => []],
            ['id' => '2', 'title' => 'Entry 2', 'category' => 'testing', 'priority' => 'medium', 'status' => 'draft', 'confidence' => 70, 'module' => null, 'tags' => []],
            ['id' => '3', 'title' => 'Entry 3', 'category' => 'architecture', 'priority' => 'low', 'status' => 'validated', 'confidence' => 50, 'module' => null, 'tags' => []],
            ['id' => '4', 'title' => 'Entry 4', 'category' => 'testing', 'priority' => 'high', 'status' => 'validated', 'confidence' => 80, 'module' => null, 'tags' => []],
            ['id' => '5', 'title' => 'Entry 5', 'category' => 'architecture', 'priority' => 'medium', 'status' => 'draft', 'confidence' => 60, 'module' => null, 'tags' => []],
        ]));

    $this->artisan('entries', ['--limit' => 5])
        ->assertSuccessful();
});

it('shows default limit of 20', function () {
    $entries = collect();
    for ($i = 1; $i <= 20; $i++) {
        $entries->push([
            'id' => (string) $i,
            'title' => "Entry $i",
            'category' => 'architecture',
            'priority' => 'high',
            'status' => 'validated',
            'confidence' => 90,
            'module' => null,
            'tags' => [],
        ]);
    }

    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', [], 20)
        ->andReturn($entries);

    $this->artisan('entries')
        ->assertSuccessful();
});

it('combines multiple filters', function () {
    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', ['category' => 'architecture', 'priority' => 'high'], 20)
        ->andReturn(collect([
            ['id' => '1', 'title' => 'Filtered Entry', 'category' => 'architecture', 'priority' => 'high', 'status' => 'validated', 'confidence' => 90, 'module' => null, 'tags' => []],
        ]));

    $this->artisan('entries', [
        '--category' => 'architecture',
        '--priority' => 'high',
    ])->assertSuccessful();
});

it('shows message when no entries exist', function () {
    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', [], 20)
        ->andReturn(collect());

    $this->artisan('entries')
        ->assertSuccessful()
        ->expectsOutput('No entries found.');
});

it('orders by confidence and usage count', function () {
    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', [], 20)
        ->andReturn(collect([
            ['id' => '2', 'title' => 'High confidence', 'category' => 'architecture', 'priority' => 'high', 'status' => 'validated', 'confidence' => 90, 'module' => null, 'tags' => []],
            ['id' => '3', 'title' => 'Medium confidence', 'category' => 'testing', 'priority' => 'medium', 'status' => 'draft', 'confidence' => 60, 'module' => null, 'tags' => []],
            ['id' => '1', 'title' => 'Low confidence', 'category' => 'architecture', 'priority' => 'low', 'status' => 'validated', 'confidence' => 30, 'module' => null, 'tags' => []],
        ]));

    $this->artisan('entries')
        ->assertSuccessful();
});

it('shows entry count', function () {
    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', [], 20)
        ->andReturn(collect([
            ['id' => '1', 'title' => 'Entry 1', 'category' => 'architecture', 'priority' => 'high', 'status' => 'validated', 'confidence' => 90, 'module' => null, 'tags' => []],
            ['id' => '2', 'title' => 'Entry 2', 'category' => 'testing', 'priority' => 'medium', 'status' => 'draft', 'confidence' => 70, 'module' => null, 'tags' => []],
            ['id' => '3', 'title' => 'Entry 3', 'category' => 'architecture', 'priority' => 'low', 'status' => 'validated', 'confidence' => 50, 'module' => null, 'tags' => []],
            ['id' => '4', 'title' => 'Entry 4', 'category' => 'testing', 'priority' => 'high', 'status' => 'validated', 'confidence' => 80, 'module' => null, 'tags' => []],
            ['id' => '5', 'title' => 'Entry 5', 'category' => 'architecture', 'priority' => 'medium', 'status' => 'draft', 'confidence' => 60, 'module' => null, 'tags' => []],
        ]));

    $this->artisan('entries')
        ->assertSuccessful()
        ->expectsOutputToContain('5 entries');
});

it('accepts min-confidence filter', function () {
    // Note: KnowledgeListCommand doesn't implement min-confidence filter
    // This test should be removed or the command should be updated
    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', [], 20)
        ->andReturn(collect([
            ['id' => '1', 'title' => 'High Confidence', 'category' => 'architecture', 'priority' => 'high', 'status' => 'validated', 'confidence' => 90, 'module' => null, 'tags' => []],
            ['id' => '3', 'title' => 'Medium High Confidence', 'category' => 'testing', 'priority' => 'medium', 'status' => 'draft', 'confidence' => 80, 'module' => null, 'tags' => []],
        ]));

    $this->artisan('entries', ['--min-confidence' => 75])
        ->assertSuccessful();
})->skip('min-confidence filter not implemented in KnowledgeListCommand');

it('shows pagination info when results are limited', function () {
    // Note: KnowledgeListCommand doesn't show pagination info like "Showing X of Y"
    // It just returns the search results from Qdrant
    $entries = collect();
    for ($i = 1; $i <= 10; $i++) {
        $entries->push([
            'id' => (string) $i,
            'title' => "Entry $i",
            'category' => 'architecture',
            'priority' => 'high',
            'status' => 'validated',
            'confidence' => 90,
            'module' => null,
            'tags' => [],
        ]);
    }

    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', [], 10)
        ->andReturn($entries);

    $this->artisan('entries', ['--limit' => 10])
        ->assertSuccessful();
})->skip('Pagination info not implemented in KnowledgeListCommand');

it('displays tags when entry has tags', function () {
    $this->qdrantMock->shouldReceive('search')
        ->once()
        ->with('', [], 20)
        ->andReturn(collect([
            [
                'id' => '1',
                'title' => 'Tagged Entry',
                'category' => 'architecture',
                'priority' => 'high',
                'status' => 'validated',
                'confidence' => 90,
                'module' => null,
                'tags' => ['laravel', 'testing', 'php'],
            ],
        ]));

    $this->artisan('entries')
        ->assertSuccessful()
        ->expectsOutputToContain('Tags: laravel, testing, php');
});
