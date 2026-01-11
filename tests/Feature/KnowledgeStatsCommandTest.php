<?php

declare(strict_types=1);

use App\Services\QdrantService;

describe('KnowledgeStatsCommand', function () {
    it('displays comprehensive analytics dashboard covering all code paths', function () {
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

        $qdrant->shouldReceive('search')
            ->once()
            ->with('', [], 10000)
            ->andReturn($entries);

        $this->artisan('stats')
            ->expectsOutputToContain('Knowledge Base Analytics')
            ->expectsOutputToContain('Total Entries: 3')
            ->expectsOutputToContain('Entries by Status:')
            ->expectsOutputToContain('validated: 1')
            ->expectsOutputToContain('draft: 1')
            ->expectsOutputToContain('deprecated: 1')
            ->expectsOutputToContain('Entries by Category:')
            ->expectsOutputToContain('tutorial: 1')
            ->expectsOutputToContain('guide: 1')
            ->expectsOutputToContain('(uncategorized): 1')
            ->expectsOutputToContain('Usage Statistics:')
            ->expectsOutputToContain('Total Usage: 60')
            ->expectsOutputToContain('Average Usage: 20')
            ->expectsOutputToContain('Most Used: "Laravel Entry" (50 times)')
            ->assertSuccessful();
    });
});
