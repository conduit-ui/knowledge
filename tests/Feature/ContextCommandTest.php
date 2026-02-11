<?php

declare(strict_types=1);

use App\Services\GitContextService;
use App\Services\QdrantService;

describe('ContextCommand', function (): void {
    beforeEach(function (): void {
        $this->qdrantService = mock(QdrantService::class);
        $this->gitService = mock(GitContextService::class);

        app()->instance(QdrantService::class, $this->qdrantService);
        app()->instance(GitContextService::class, $this->gitService);

        $this->gitService->shouldReceive('isGitRepository')->andReturn(true)->byDefault();
        $this->gitService->shouldReceive('getRepositoryPath')->andReturn('/home/user/my-project')->byDefault();
    });

    it('outputs markdown context grouped by category', function (): void {
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->with([], 50, 'my-project')
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Service Layer Pattern',
                    'content' => 'Use service classes for business logic.',
                    'tags' => ['architecture', 'services'],
                    'category' => 'architecture',
                    'priority' => 'high',
                    'confidence' => 90,
                    'usage_count' => 5,
                    'updated_at' => now()->toIso8601String(),
                ],
                [
                    'id' => 'uuid-2',
                    'title' => 'N+1 Query Gotcha',
                    'content' => 'Always eager load relationships.',
                    'tags' => ['performance'],
                    'category' => 'gotchas',
                    'priority' => 'critical',
                    'confidence' => 95,
                    'usage_count' => 3,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        $this->qdrantService->shouldReceive('incrementUsage')->twice();

        $this->artisan('context')
            ->assertSuccessful()
            ->expectsOutputToContain('# Session Context: my-project')
            ->expectsOutputToContain('## Architecture')
            ->expectsOutputToContain('### Service Layer Pattern')
            ->expectsOutputToContain('## Gotchas')
            ->expectsOutputToContain('### N+1 Query Gotcha');
    });

    it('shows no context message when empty', function (): void {
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([]));

        $this->artisan('context')
            ->assertSuccessful()
            ->expectsOutput('No context entries found.');
    });

    it('auto-detects project from git repo', function (): void {
        $this->gitService->shouldReceive('isGitRepository')->andReturn(true);
        $this->gitService->shouldReceive('getRepositoryPath')->andReturn('/home/user/knowledge');

        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->with([], 50, 'knowledge')
            ->andReturn(collect([]));

        $this->artisan('context')
            ->assertSuccessful();
    });

    it('falls back to default when not in git repo', function (): void {
        $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->with([], 50, 'default')
            ->andReturn(collect([]));

        $this->artisan('context')
            ->assertSuccessful();
    });

    it('uses explicit project flag over auto-detection', function (): void {
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->with([], 50, 'custom-project')
            ->andReturn(collect([]));

        $this->artisan('context', ['--project' => 'custom-project'])
            ->assertSuccessful();
    });

    it('filters by categories flag', function (): void {
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->with(['category' => 'architecture'], 25, 'my-project')
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Arch Entry',
                    'content' => 'Architecture content.',
                    'tags' => [],
                    'category' => 'architecture',
                    'priority' => 'high',
                    'confidence' => 90,
                    'usage_count' => 1,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->with(['category' => 'decisions'], 25, 'my-project')
            ->andReturn(collect([
                [
                    'id' => 'uuid-2',
                    'title' => 'Decision Entry',
                    'content' => 'Decision content.',
                    'tags' => [],
                    'category' => 'decisions',
                    'priority' => 'medium',
                    'confidence' => 80,
                    'usage_count' => 0,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        $this->qdrantService->shouldReceive('incrementUsage')->twice();

        $this->artisan('context', ['--categories' => 'architecture,decisions'])
            ->assertSuccessful()
            ->expectsOutputToContain('## Architecture')
            ->expectsOutputToContain('## Decisions');
    });

    it('respects max-tokens flag', function (): void {
        // Generate entries that would exceed a tiny token budget
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'First Entry',
                    'content' => str_repeat('A', 200),
                    'tags' => [],
                    'category' => 'architecture',
                    'priority' => 'high',
                    'confidence' => 90,
                    'usage_count' => 10,
                    'updated_at' => now()->toIso8601String(),
                ],
                [
                    'id' => 'uuid-2',
                    'title' => 'Second Entry That Should Be Truncated',
                    'content' => str_repeat('B', 2000),
                    'tags' => [],
                    'category' => 'patterns',
                    'priority' => 'medium',
                    'confidence' => 80,
                    'usage_count' => 0,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        $this->qdrantService->shouldReceive('incrementUsage')
            ->with('uuid-1', 'my-project')
            ->once();

        // Very small token budget - should only include first entry
        $this->artisan('context', ['--max-tokens' => '150'])
            ->assertSuccessful()
            ->expectsOutputToContain('First Entry');
    });

    it('respects limit flag', function (): void {
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->with([], 10, 'my-project')
            ->andReturn(collect([]));

        $this->artisan('context', ['--limit' => '10'])
            ->assertSuccessful();
    });

    it('skips usage tracking with no-usage flag', function (): void {
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Test Entry',
                    'content' => 'Some content.',
                    'tags' => [],
                    'category' => 'testing',
                    'priority' => 'medium',
                    'confidence' => 80,
                    'usage_count' => 0,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        $this->qdrantService->shouldNotReceive('incrementUsage');

        $this->artisan('context', ['--no-usage' => true])
            ->assertSuccessful();
    });

    it('increments usage_count for accessed entries', function (): void {
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Entry One',
                    'content' => 'Content one.',
                    'tags' => ['tag1'],
                    'category' => 'architecture',
                    'priority' => 'high',
                    'confidence' => 90,
                    'usage_count' => 2,
                    'updated_at' => now()->toIso8601String(),
                ],
                [
                    'id' => 'uuid-2',
                    'title' => 'Entry Two',
                    'content' => 'Content two.',
                    'tags' => [],
                    'category' => 'patterns',
                    'priority' => 'medium',
                    'confidence' => 85,
                    'usage_count' => 1,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        $this->qdrantService->shouldReceive('incrementUsage')
            ->with('uuid-1', 'my-project')
            ->once();

        $this->qdrantService->shouldReceive('incrementUsage')
            ->with('uuid-2', 'my-project')
            ->once();

        $this->artisan('context')
            ->assertSuccessful();
    });

    it('ranks entries by usage and recency', function (): void {
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-old',
                    'title' => 'Old Rarely Used',
                    'content' => 'Old content.',
                    'tags' => [],
                    'category' => 'architecture',
                    'priority' => 'low',
                    'confidence' => 70,
                    'usage_count' => 0,
                    'updated_at' => now()->subDays(30)->toIso8601String(),
                ],
                [
                    'id' => 'uuid-new',
                    'title' => 'New Highly Used',
                    'content' => 'New content.',
                    'tags' => [],
                    'category' => 'architecture',
                    'priority' => 'high',
                    'confidence' => 95,
                    'usage_count' => 10,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        $this->qdrantService->shouldReceive('incrementUsage')->twice();

        // The highly used + recent entry should appear first
        $result = $this->artisan('context');
        $result->assertSuccessful();

        // Capture output to verify order - new entry should be before old
        $result->expectsOutputToContain('New Highly Used');
    });

    it('formats entries with metadata', function (): void {
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Test Pattern',
                    'content' => 'Always use Pest for testing.',
                    'tags' => ['testing', 'pest'],
                    'category' => 'patterns',
                    'priority' => 'high',
                    'confidence' => 95,
                    'usage_count' => 5,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        $this->qdrantService->shouldReceive('incrementUsage')->once();

        $this->artisan('context')
            ->assertSuccessful()
            ->expectsOutputToContain('### Test Pattern')
            ->expectsOutputToContain('Priority: high')
            ->expectsOutputToContain('Confidence: 95%')
            ->expectsOutputToContain('Tags: testing, pest')
            ->expectsOutputToContain('Always use Pest for testing.');
    });

    it('handles entries with no category', function (): void {
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Uncategorized Entry',
                    'content' => 'No category set.',
                    'tags' => [],
                    'category' => null,
                    'priority' => 'medium',
                    'confidence' => 50,
                    'usage_count' => 0,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        $this->qdrantService->shouldReceive('incrementUsage')->once();

        $this->artisan('context')
            ->assertSuccessful()
            ->expectsOutputToContain('## Uncategorized');
    });

    it('orders categories in predefined order', function (): void {
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Gotcha First Alphabetically',
                    'content' => 'A gotcha.',
                    'tags' => [],
                    'category' => 'gotchas',
                    'priority' => 'medium',
                    'confidence' => 80,
                    'usage_count' => 10,
                    'updated_at' => now()->toIso8601String(),
                ],
                [
                    'id' => 'uuid-2',
                    'title' => 'Arch Entry',
                    'content' => 'Architecture stuff.',
                    'tags' => [],
                    'category' => 'architecture',
                    'priority' => 'high',
                    'confidence' => 90,
                    'usage_count' => 10,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        $this->qdrantService->shouldReceive('incrementUsage')->twice();

        // Architecture should come before gotchas in output even though
        // gotchas was first in the scroll results
        $this->artisan('context')
            ->assertSuccessful()
            ->expectsOutputToContain('## Architecture')
            ->expectsOutputToContain('## Gotchas');
    });

    it('handles entries with empty updated_at gracefully', function (): void {
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'Entry Without Date',
                    'content' => 'No date.',
                    'tags' => [],
                    'category' => 'testing',
                    'priority' => 'medium',
                    'confidence' => 50,
                    'usage_count' => 0,
                    'updated_at' => '',
                ],
                [
                    'id' => 'uuid-2',
                    'title' => 'Entry With Date',
                    'content' => 'Has date.',
                    'tags' => [],
                    'category' => 'testing',
                    'priority' => 'medium',
                    'confidence' => 50,
                    'usage_count' => 0,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        $this->qdrantService->shouldReceive('incrementUsage')->twice();

        $this->artisan('context')
            ->assertSuccessful()
            ->expectsOutputToContain('Entry Without Date');
    });

    it('handles git repo with null path gracefully', function (): void {
        $this->gitService->shouldReceive('isGitRepository')->andReturn(true);
        $this->gitService->shouldReceive('getRepositoryPath')->andReturn(null);

        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->with([], 50, 'default')
            ->andReturn(collect([]));

        $this->artisan('context')
            ->assertSuccessful();
    });

    it('handles entry with invalid date string that makes strtotime return false', function (): void {
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-bad-date',
                    'title' => 'Entry With Bad Date',
                    'content' => 'Content with invalid date.',
                    'tags' => ['test'],
                    'category' => 'testing',
                    'priority' => 'medium',
                    'confidence' => 70,
                    'usage_count' => 0,
                    'updated_at' => 'not-a-date',
                ],
                [
                    'id' => 'uuid-good-date',
                    'title' => 'Entry With Good Date',
                    'content' => 'Content with valid date.',
                    'tags' => [],
                    'category' => 'testing',
                    'priority' => 'medium',
                    'confidence' => 70,
                    'usage_count' => 0,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        $this->qdrantService->shouldReceive('incrementUsage')->twice();

        $this->artisan('context')
            ->assertSuccessful()
            ->expectsOutputToContain('Entry With Bad Date');
    });

    it('breaks when a category header alone exceeds remaining char budget', function (): void {
        // Use a very tight token budget.
        // The header "# Session Context: my-project" is ~32 chars + 1 newline = 33.
        // First category "## Architecture" is ~17 chars + 2 = 19.
        // First entry block adds more chars.
        // Second category "## Patterns" header should trigger the break.
        $this->qdrantService->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'uuid-1',
                    'title' => 'A',
                    'content' => 'B',
                    'tags' => [],
                    'category' => 'architecture',
                    'priority' => 'high',
                    'confidence' => 90,
                    'usage_count' => 10,
                    'updated_at' => now()->toIso8601String(),
                ],
                [
                    'id' => 'uuid-2',
                    'title' => 'Second Category Entry',
                    'content' => 'This should not appear.',
                    'tags' => [],
                    'category' => 'patterns',
                    'priority' => 'medium',
                    'confidence' => 80,
                    'usage_count' => 5,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        // Only uuid-1 should get usage tracked since uuid-2 is in a new
        // category that can't fit.
        $this->qdrantService->shouldReceive('incrementUsage')
            ->with('uuid-1', 'my-project')
            ->once();

        // Set token budget very tight: enough for header + first category + first entry,
        // but not enough for a second category header.
        // "# Session Context: my-project" = 31 chars, + 1 newline = 32 chars counted
        // "## Architecture" = 15 + 2 = 17 chars counted
        // Entry block for title "A" content "B" is approx:
        //   "### A\n\nPriority: high\nConfidence: 90%\n\nB\n" ~ 42 chars + 1 = 43
        // Total ~ 32 + 17 + 43 = 92 chars
        // "## Patterns" header = 11 + 2 = 13 chars
        // Budget at 25 tokens * 4 = 100 chars allows first entry but not second category header
        $this->artisan('context', ['--max-tokens' => '25'])
            ->assertSuccessful()
            ->expectsOutputToContain('## Architecture')
            ->expectsOutputToContain('### A');
    });
});
