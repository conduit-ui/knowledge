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
