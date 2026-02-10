<?php

declare(strict_types=1);

use App\Services\QdrantService;

describe('KnowledgeSearchCommand', function (): void {
    beforeEach(function (): void {
        $this->qdrantService = mock(QdrantService::class);

        app()->instance(QdrantService::class, $this->qdrantService);
    });

    it('requires at least one parameter', function (): void {
        $this->artisan('search')
            ->expectsOutput('Please provide at least one search parameter.')
            ->assertFailed();
    });

    it('finds entries by keyword', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('Laravel', [], 20)
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Laravel Testing',
                    'content' => 'How to test Laravel applications',
                    'tags' => ['laravel', 'testing'],
                    'category' => 'tutorial',
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 95,
                    'score' => 0.95,
                ],
            ]));

        $this->artisan('search', ['query' => 'Laravel'])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 1 entry')
            ->expectsOutputToContain('Laravel Testing');
    });

    it('filters by tag', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('', ['tag' => 'php'], 20)
            ->andReturn(collect([
                [
                    'id' => 'uuid-2',
                    'title' => 'PHP Standards',
                    'content' => 'PHP coding standards and PSR guidelines',
                    'tags' => ['php', 'standards'],
                    'category' => 'guide',
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 90,
                    'score' => 0.90,
                ],
            ]));

        $this->artisan('search', ['--tag' => 'php'])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 1 entry')
            ->expectsOutputToContain('PHP Standards');
    });

    it('filters by category', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('', ['category' => 'tutorial'], 20)
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Laravel Testing',
                    'content' => 'How to test Laravel applications',
                    'tags' => ['laravel', 'testing'],
                    'category' => 'tutorial',
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 95,
                    'score' => 0.95,
                ],
            ]));

        $this->artisan('search', ['--category' => 'tutorial'])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 1 entry')
            ->expectsOutputToContain('Laravel Testing');
    });

    it('shows no results message', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->andReturn(collect([]));

        $this->artisan('search', ['query' => 'nonexistent'])
            ->assertSuccessful()
            ->expectsOutput('No entries found.');
    });

    it('supports semantic flag', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('Laravel', [], 20)
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Laravel Testing',
                    'content' => 'How to test Laravel applications',
                    'tags' => ['laravel', 'testing'],
                    'category' => 'tutorial',
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 95,
                    'score' => 0.95,
                ],
            ]));

        $this->artisan('search', [
            'query' => 'Laravel',
            '--semantic' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 1 entry')
            ->expectsOutputToContain('Laravel Testing');
    });

    it('combines query and filters', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('Laravel', ['category' => 'tutorial'], 20)
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Laravel Testing',
                    'content' => 'How to test Laravel applications',
                    'tags' => ['laravel', 'testing'],
                    'category' => 'tutorial',
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 95,
                    'score' => 0.95,
                ],
            ]));

        $this->artisan('search', [
            'query' => 'Laravel',
            '--category' => 'tutorial',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 1 entry')
            ->expectsOutputToContain('Laravel Testing');
    });

    it('shows entry details', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Laravel Testing',
                    'content' => 'How to test Laravel applications',
                    'tags' => ['laravel', 'testing'],
                    'category' => 'tutorial',
                    'module' => 'TestModule',
                    'priority' => 'high',
                    'status' => 'validated',
                    'confidence' => 95,
                    'score' => 0.95,
                ],
            ]));

        $this->artisan('search', ['query' => 'Laravel'])
            ->assertSuccessful()
            ->expectsOutputToContain('Laravel Testing')
            ->expectsOutputToContain('Category: tutorial | Priority: high | Confidence: 95%')
            ->expectsOutputToContain('Module: TestModule');
    });

    it('truncates long content', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-3',
                    'title' => 'Long Content',
                    'content' => str_repeat('a', 150),
                    'tags' => [],
                    'category' => null,
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 100,
                    'score' => 0.85,
                ],
            ]));

        $this->artisan('search', ['query' => 'Long'])
            ->assertSuccessful()
            ->expectsOutputToContain('...');
    });

    it('displays multiple search results', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Laravel Testing',
                    'content' => 'How to test Laravel applications',
                    'tags' => ['laravel', 'testing'],
                    'category' => 'tutorial',
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 95,
                    'score' => 0.95,
                ],
                [
                    'id' => 'uuid-2',
                    'title' => 'PHP Standards',
                    'content' => 'PHP coding standards',
                    'tags' => ['php'],
                    'category' => 'guide',
                    'module' => null,
                    'priority' => 'low',
                    'status' => 'draft',
                    'confidence' => 90,
                    'score' => 0.85,
                ],
            ]));

        $this->artisan('search', ['query' => 'test'])
            ->assertSuccessful()
            ->expectsOutputToContain('Found 2 entries')
            ->expectsOutputToContain('Laravel Testing')
            ->expectsOutputToContain('PHP Standards');
    });

    it('supports multiple filters simultaneously', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('', [
                'category' => 'testing',
                'module' => 'Core',
                'priority' => 'high',
                'status' => 'validated',
                'tag' => 'laravel',
            ], 20)
            ->andReturn(collect([]));

        $this->artisan('search', [
            '--category' => 'testing',
            '--module' => 'Core',
            '--priority' => 'high',
            '--status' => 'validated',
            '--tag' => 'laravel',
        ])
            ->assertSuccessful()
            ->expectsOutput('No entries found.');
    });

    it('handles empty tags array gracefully', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-4',
                    'title' => 'Untagged Entry',
                    'content' => 'This entry has no tags',
                    'tags' => [],
                    'category' => null,
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 50,
                    'score' => 0.75,
                ],
            ]));

        $this->artisan('search', ['query' => 'Untagged'])
            ->assertSuccessful()
            ->expectsOutputToContain('Untagged Entry');
    });

    it('displays score in results', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Test Entry',
                    'content' => 'Content',
                    'tags' => [],
                    'category' => null,
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 80,
                    'score' => 0.92,
                    'superseded_by' => null,
                ],
            ]));

        $this->artisan('search', ['query' => 'test'])
            ->assertSuccessful()
            ->expectsOutputToContain('score: 0.92');
    });

    it('passes include_superseded filter when flag is set', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('test', Mockery::on(fn ($filters): bool => isset($filters['include_superseded']) && $filters['include_superseded'] === true), 20)
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Old Entry',
                    'content' => 'Old content',
                    'tags' => [],
                    'category' => null,
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 50,
                    'score' => 0.85,
                    'superseded_by' => 'uuid-2',
                    'superseded_date' => '2026-01-15T00:00:00Z',
                    'superseded_reason' => 'Updated',
                ],
            ]));

        $this->artisan('search', [
            'query' => 'test',
            '--include-superseded' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Old Entry');
    });

    it('does not pass include_superseded by default', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('test', [], 20)
            ->andReturn(collect([]));

        $this->artisan('search', ['query' => 'test'])
            ->assertSuccessful();
    });

    it('shows superseded indicator on superseded entries', function (): void {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Superseded Entry',
                    'content' => 'Old content',
                    'tags' => [],
                    'category' => null,
                    'module' => null,
                    'priority' => 'medium',
                    'status' => 'draft',
                    'confidence' => 50,
                    'score' => 0.85,
                    'superseded_by' => 'uuid-2',
                    'superseded_date' => '2026-01-15T00:00:00Z',
                    'superseded_reason' => 'Updated',
                ],
            ]));

        $this->artisan('search', [
            'query' => 'test',
            '--include-superseded' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('SUPERSEDED')
            ->expectsOutputToContain('Superseded by: uuid-2');
    });
});
