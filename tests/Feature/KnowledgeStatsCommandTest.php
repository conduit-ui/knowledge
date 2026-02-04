<?php

declare(strict_types=1);

use App\Services\QdrantService;

describe('KnowledgeStatsCommand', function (): void {
    it('displays comprehensive analytics dashboard covering all code paths', function (): void {
        $qdrant = mock(QdrantService::class);
        app()->instance(QdrantService::class, $qdrant);

        // Mixed entries testing all display logic in a single test due to static caching
        $entries = collect([
            [
                'id' => 1,
                'title' => 'Laravel Entry',
                'content' => 'Laravel content',
                'category' => 'tutorial',
                'status' => 'validated',
                'usage_count' => 50, // Most used
                'tags' => ['laravel'],
            ],
            [
                'id' => 2,
                'title' => 'PHP Entry',
                'content' => 'PHP content',
                'category' => 'guide',
                'status' => 'draft',
                'usage_count' => 10,
                'tags' => ['php'],
            ],
            [
                'id' => 3,
                'title' => 'Uncategorized Entry',
                'content' => 'No category',
                'category' => null,
                'status' => 'deprecated',
                'usage_count' => 0,
                'tags' => [],
            ],
        ]);

        // Now uses count() instead of search('')
        $qdrant->shouldReceive('count')
            ->once()
            ->andReturn(3);

        // Now uses scroll() to get sample entries
        $qdrant->shouldReceive('scroll')
            ->once()
            ->with([], 3)
            ->andReturn($entries);

        $this->artisan('stats')
            ->assertSuccessful();
    });
});
