<?php

declare(strict_types=1);

use App\Contracts\FullTextSearchInterface;
use App\Services\QdrantService;
use Illuminate\Support\Collection;

describe('KnowledgeSearchCommand', function () {
    beforeEach(function () {
        $this->qdrantService = mock(QdrantService::class);
        $this->ftsService = mock(FullTextSearchInterface::class);

        app()->instance(QdrantService::class, $this->qdrantService);
        app()->instance(FullTextSearchInterface::class, $this->ftsService);
    });

    it('requires at least one parameter', function () {
        $this->artisan('search')
            ->expectsOutput('Please provide at least one search parameter.')
            ->assertFailed();
    });

    it('finds entries by keyword', function () {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('Laravel', Mockery::type('array'))
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

    it('filters by tag', function () {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('', ['tag' => 'php'])
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

    it('filters by category', function () {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('', ['category' => 'tutorial'])
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

    it('shows no results message', function () {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->andReturn(collect([]));

        $this->artisan('search', ['query' => 'nonexistent'])
            ->assertSuccessful()
            ->expectsOutput('No entries found.');
    });

    it('supports semantic flag', function () {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('Laravel', Mockery::type('array'))
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

    it('combines query and filters', function () {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('Laravel', ['category' => 'tutorial'])
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

    it('shows entry details', function () {
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

    it('truncates long content', function () {
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

    it('displays multiple search results', function () {
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

    it('supports multiple filters simultaneously', function () {
        $this->qdrantService->shouldReceive('search')
            ->once()
            ->with('', [
                'category' => 'testing',
                'module' => 'Core',
                'priority' => 'high',
                'status' => 'validated',
                'tag' => 'laravel',
            ])
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

    it('handles empty tags array gracefully', function () {
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

    it('displays score in results', function () {
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
                ],
            ]));

        $this->artisan('search', ['query' => 'test'])
            ->assertSuccessful()
            ->expectsOutputToContain('score: 0.92');
    });
});
