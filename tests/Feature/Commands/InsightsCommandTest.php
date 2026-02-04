<?php

declare(strict_types=1);

use App\Services\PatternDetectorService;
use App\Services\QdrantService;
use App\Services\ThemeClassifierService;

beforeEach(function (): void {
    $this->qdrantMock = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->qdrantMock);

    // Use real services for classification and pattern detection
    $this->app->instance(ThemeClassifierService::class, new ThemeClassifierService);
    $this->app->instance(PatternDetectorService::class, new PatternDetectorService);
});

describe('insights command', function (): void {
    it('shows theme and pattern analysis', function (): void {
        $entries = collect([
            createTestEntry('1', 'PHPStan Configuration', 'Testing and quality automation with PHPStan', ['testing']),
            createTestEntry('2', 'GitHub CLI Tool', 'Developer experience with API integration', ['github']),
            createTestEntry('3', 'Knowledge Synthesis', 'Context continuity and memory', ['knowledge']),
        ]);

        $this->qdrantMock->shouldReceive('scroll')
            ->andReturn($entries);

        $this->artisan('insights')
            ->assertSuccessful();
    });

    it('shows only themes when flag specified', function (): void {
        $entries = collect([
            createTestEntry('1', 'Test Entry', 'Content about testing', ['test']),
        ]);

        $this->qdrantMock->shouldReceive('scroll')
            ->once()
            ->andReturn($entries);

        $this->artisan('insights', ['--themes' => true])
            ->assertSuccessful();
    });

    it('shows only patterns when flag specified', function (): void {
        $entries = collect([
            createTestEntry('1', 'Test Entry', 'Content about testing', ['test']),
        ]);

        $this->qdrantMock->shouldReceive('scroll')
            ->once()
            ->andReturn($entries);

        $this->artisan('insights', ['--patterns' => true])
            ->assertSuccessful();
    });

    it('handles empty knowledge base', function (): void {
        $this->qdrantMock->shouldReceive('scroll')
            ->andReturn(collect());

        $this->artisan('insights')
            ->assertSuccessful();
    });

    it('respects limit option', function (): void {
        $this->qdrantMock->shouldReceive('scroll')
            ->with([], 50)
            ->andReturn(collect());

        $this->artisan('insights', ['--limit' => '50'])
            ->assertSuccessful();
    });
});

describe('classify-entry option', function (): void {
    it('classifies a single entry', function (): void {
        $entry = [
            'id' => 'test-id',
            'title' => 'PHPStan Quality Gate',
            'content' => 'Configure PHPStan for automated testing and code quality',
            'tags' => ['phpstan', 'testing'],
            'category' => 'testing',
            'confidence' => 80,
            'status' => 'validated',
            'usage_count' => 0,
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ];

        $this->qdrantMock->shouldReceive('getById')
            ->with('test-id')
            ->andReturn($entry);

        $this->artisan('insights', ['--classify-entry' => 'test-id'])
            ->assertSuccessful();
    });

    it('shows error for non-existent entry', function (): void {
        $this->qdrantMock->shouldReceive('getById')
            ->with('non-existent')
            ->andReturn(null);

        $this->artisan('insights', ['--classify-entry' => 'non-existent'])
            ->assertFailed();
    });

    it('shows warning when no strong theme match detected', function (): void {
        // Entry with generic content that won't match any theme
        $entry = [
            'id' => 'generic-id',
            'title' => 'Random Notes',
            'content' => 'Some random notes about nothing in particular.',
            'tags' => [],
            'category' => null,
            'confidence' => 50,
            'status' => 'validated',
            'usage_count' => 0,
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ];

        $this->qdrantMock->shouldReceive('getById')
            ->with('generic-id')
            ->andReturn($entry);

        $this->artisan('insights', ['--classify-entry' => 'generic-id'])
            ->assertSuccessful();
    });
});

describe('pattern analysis output', function (): void {
    it('displays frequent topics when detected', function (): void {
        // Create entries with repeated topics to trigger pattern detection (needs 3+ occurrences)
        $entries = collect([
            createTestEntry('1', 'PHPStan Configuration Guide', 'Configure PHPStan level phpstan analysis', ['phpstan']),
            createTestEntry('2', 'PHPStan Best Practices', 'Best practices for phpstan static analysis', ['phpstan']),
            createTestEntry('3', 'PHPStan CI Integration', 'Integrate phpstan into your CI pipeline', ['phpstan']),
            createTestEntry('4', 'PHPStan Baseline Setup', 'Setting up phpstan baseline for legacy code', ['phpstan']),
        ]);

        $this->qdrantMock->shouldReceive('scroll')
            ->andReturn($entries);

        $this->artisan('insights', ['--patterns' => true])
            ->assertSuccessful();
    });

    it('displays recurring tags when detected', function (): void {
        // Create entries with same tags repeated 3+ times
        $entries = collect([
            createTestEntry('1', 'Test Entry 1', 'Content one', ['testing', 'automation']),
            createTestEntry('2', 'Test Entry 2', 'Content two', ['testing', 'automation']),
            createTestEntry('3', 'Test Entry 3', 'Content three', ['testing', 'automation']),
            createTestEntry('4', 'Test Entry 4', 'Content four', ['testing', 'automation']),
        ]);

        $this->qdrantMock->shouldReceive('scroll')
            ->andReturn($entries);

        $this->artisan('insights', ['--patterns' => true])
            ->assertSuccessful();
    });

    it('displays project associations when detected', function (): void {
        // Create entries mentioning projects (needs 3+ occurrences of project patterns)
        $entries = collect([
            createTestEntry('1', 'Conduit GitHub Setup', 'Working on conduit-github integration', ['conduit']),
            createTestEntry('2', 'Conduit GitHub Features', 'New features for conduit-github', ['conduit']),
            createTestEntry('3', 'Conduit GitHub Bugs', 'Bug fixes in conduit-github cli', ['conduit']),
            createTestEntry('4', 'Conduit GitHub Release', 'Releasing conduit-github v2', ['conduit']),
        ]);

        $this->qdrantMock->shouldReceive('scroll')
            ->andReturn($entries);

        $this->artisan('insights', ['--patterns' => true])
            ->assertSuccessful();
    });

    it('displays insights when patterns are significant', function (): void {
        // Create entries that will generate insights (needs enough data for insights generation)
        $entries = collect([
            createTestEntry('1', 'PHPStan Guide', 'Configure phpstan for testing', ['phpstan']),
            createTestEntry('2', 'PHPStan Setup', 'Setup phpstan correctly', ['phpstan']),
            createTestEntry('3', 'PHPStan Tips', 'PHPStan tips and tricks', ['phpstan']),
            createTestEntry('4', 'PHPStan Rules', 'Custom phpstan rules', ['phpstan']),
            createTestEntry('5', 'Testing Entry', 'Testing content here', ['testing']),
            createTestEntry('6', 'More Testing', 'More testing content', ['testing']),
        ]);

        $this->qdrantMock->shouldReceive('scroll')
            ->andReturn($entries);

        $this->artisan('insights', ['--patterns' => true])
            ->assertSuccessful();
    });
});

function createTestEntry(string $id, string $title, string $content, array $tags = []): array
{
    return [
        'id' => $id,
        'title' => $title,
        'content' => $content,
        'tags' => $tags,
        'category' => 'architecture',
        'confidence' => 80,
        'status' => 'validated',
        'usage_count' => 0,
        'created_at' => '2026-01-01T00:00:00+00:00',
        'updated_at' => '2026-01-01T00:00:00+00:00',
    ];
}
