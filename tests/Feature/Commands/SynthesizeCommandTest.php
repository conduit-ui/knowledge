<?php

declare(strict_types=1);

use App\Services\QdrantService;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->qdrantMock = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->qdrantMock);
    Carbon::setTestNow(Carbon::parse('2026-02-03 10:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('dedupe', function (): void {
    it('finds and merges duplicate entries', function (): void {
        $candidate = createEntry('1', 'Test Entry', 50, 'draft');
        $duplicate = createEntry('2', 'Test Entry Similar', 80, 'validated', 0.95);

        $this->qdrantMock->shouldReceive('scroll')
            ->once()
            ->with(['status' => 'draft'], 100)
            ->andReturn(collect([$candidate]));

        $this->qdrantMock->shouldReceive('search')
            ->once()
            ->andReturn(collect([$duplicate]));

        $this->qdrantMock->shouldReceive('updateFields')
            ->once()
            ->with('1', Mockery::on(fn ($fields): bool => $fields['status'] === 'deprecated'))
            ->andReturn(true);

        // Mock digest and archive operations to return empty
        mockEmptyDigestAndArchive($this->qdrantMock);

        $this->artisan('synthesize')
            ->assertSuccessful();
    });

    it('skips entries below similarity threshold', function (): void {
        $candidate = createEntry('1', 'Unique Entry', 50, 'draft');
        $notSimilar = createEntry('2', 'Different Entry', 80, 'validated', 0.5);

        $this->qdrantMock->shouldReceive('scroll')
            ->once()
            ->with(['status' => 'draft'], 100)
            ->andReturn(collect([$candidate]));

        $this->qdrantMock->shouldReceive('search')
            ->once()
            ->andReturn(collect([$notSimilar]));

        // Should NOT call updateFields for merge
        $this->qdrantMock->shouldNotReceive('updateFields')
            ->with('1', Mockery::any());

        mockEmptyDigestAndArchive($this->qdrantMock);

        $this->artisan('synthesize', ['--dedupe' => true])
            ->assertSuccessful();
    });

    it('respects dry-run flag', function (): void {
        $candidate = createEntry('1', 'Test Entry', 50, 'draft');
        $duplicate = createEntry('2', 'Test Entry Similar', 80, 'validated', 0.95);

        $this->qdrantMock->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([$candidate]));

        $this->qdrantMock->shouldReceive('search')
            ->once()
            ->andReturn(collect([$duplicate]));

        // Should NOT update in dry-run mode
        $this->qdrantMock->shouldNotReceive('updateFields');

        $this->artisan('synthesize', ['--dedupe' => true, '--dry-run' => true])
            ->assertSuccessful();
    });

    it('skips already processed entries to avoid duplicate processing', function (): void {
        // Simulate a scroll result that contains the same entry ID twice
        // (can happen with pagination edge cases or data inconsistencies)
        $candidate = createEntry('1', 'Test Entry', 50, 'draft');
        $duplicateCandidate = createEntry('1', 'Test Entry', 50, 'draft'); // Same ID appears again
        $higherConfidenceMatch = createEntry('2', 'Better Entry', 80, 'validated', 0.95);

        $this->qdrantMock->shouldReceive('scroll')
            ->with(['status' => 'draft'], 100)
            ->andReturn(collect([$candidate, $duplicateCandidate]));

        // First iteration: finds a match and processes it
        // Second iteration: same ID should be skipped via continue
        $this->qdrantMock->shouldReceive('search')
            ->once()  // Should only be called once since second iteration is skipped
            ->andReturn(collect([$higherConfidenceMatch]));

        // Only one merge should happen
        $this->qdrantMock->shouldReceive('updateFields')
            ->once()
            ->with('1', Mockery::on(fn ($fields): bool => $fields['status'] === 'deprecated'))
            ->andReturn(true);

        mockEmptyDigestAndArchive($this->qdrantMock);

        $this->artisan('synthesize')
            ->assertSuccessful();
    });
});

describe('digest', function (): void {
    it('creates a daily digest', function (): void {
        // No existing digest
        $this->qdrantMock->shouldReceive('search')
            ->with('Daily Synthesis - 2026-02-03', ['tag' => 'daily-synthesis'], 1)
            ->andReturn(collect());

        // Recent validated entries
        $entries = collect([
            createEntry('1', 'Feature Complete', 85, 'validated'),
            createEntry('2', 'Bug Fixed', 90, 'validated'),
        ]);

        $this->qdrantMock->shouldReceive('scroll')
            ->with(['status' => 'validated'], 50)
            ->andReturn($entries);

        $this->qdrantMock->shouldReceive('upsert')
            ->once()
            ->with(Mockery::on(fn ($data): bool => str_contains((string) $data['title'], 'Daily Synthesis - 2026-02-03')
                && $data['status'] === 'validated'
                && in_array('daily-synthesis', $data['tags'])))
            ->andReturn(true);

        $this->artisan('synthesize', ['--digest' => true])
            ->assertSuccessful();
    });

    it('skips digest if already exists', function (): void {
        $existingDigest = createEntry('existing', 'Daily Synthesis - 2026-02-03', 85, 'validated');

        $this->qdrantMock->shouldReceive('search')
            ->with('Daily Synthesis - 2026-02-03', ['tag' => 'daily-synthesis'], 1)
            ->andReturn(collect([$existingDigest]));

        // Should NOT create new digest
        $this->qdrantMock->shouldNotReceive('upsert');

        $this->artisan('synthesize', ['--digest' => true])
            ->assertSuccessful();
    });

    it('skips digest when no high-value entries exist', function (): void {
        $this->qdrantMock->shouldReceive('search')
            ->andReturn(collect());

        $this->qdrantMock->shouldReceive('scroll')
            ->andReturn(collect());

        $this->qdrantMock->shouldNotReceive('upsert');

        $this->artisan('synthesize', ['--digest' => true])
            ->assertSuccessful();
    });

    it('shows digest preview in dry-run mode', function (): void {
        // No existing digest
        $this->qdrantMock->shouldReceive('search')
            ->with('Daily Synthesis - 2026-02-03', ['tag' => 'daily-synthesis'], 1)
            ->andReturn(collect());

        // Recent validated entries with high confidence
        $entries = collect([
            createEntry('1', 'Feature Complete', 85, 'validated'),
        ]);

        $this->qdrantMock->shouldReceive('scroll')
            ->with(['status' => 'validated'], 50)
            ->andReturn($entries);

        // Should NOT upsert in dry-run mode
        $this->qdrantMock->shouldNotReceive('upsert');

        $this->artisan('synthesize', ['--digest' => true, '--dry-run' => true])
            ->expectsOutputToContain('Would create digest:')
            ->assertSuccessful();
    });

    it('truncates long content in digest preview', function (): void {
        // No existing digest
        $this->qdrantMock->shouldReceive('search')
            ->with('Daily Synthesis - 2026-02-03', ['tag' => 'daily-synthesis'], 1)
            ->andReturn(collect());

        // Entry with long content (>100 chars)
        $longContent = str_repeat('This is a very long piece of content that exceeds the hundred character limit. ', 3);
        $entryWithLongContent = createEntry('1', 'Long Content Entry', 85, 'validated');
        $entryWithLongContent['content'] = $longContent;

        $this->qdrantMock->shouldReceive('scroll')
            ->with(['status' => 'validated'], 50)
            ->andReturn(collect([$entryWithLongContent]));

        // Should NOT upsert in dry-run mode
        $this->qdrantMock->shouldNotReceive('upsert');

        $this->artisan('synthesize', ['--digest' => true, '--dry-run' => true])
            ->expectsOutputToContain('...')
            ->assertSuccessful();
    });
});

describe('archive-stale', function (): void {
    it('archives old low-confidence entries', function (): void {
        $staleEntry = createEntry('1', 'Old Entry', 40, 'draft');
        $staleEntry['created_at'] = Carbon::now()->subDays(60)->toIso8601String();
        $staleEntry['usage_count'] = 0;

        $this->qdrantMock->shouldReceive('scroll')
            ->with(['status' => 'draft'], 200)
            ->andReturn(collect([$staleEntry]));

        $this->qdrantMock->shouldReceive('updateFields')
            ->once()
            ->with('1', ['status' => 'deprecated'])
            ->andReturn(true);

        $this->artisan('synthesize', ['--archive-stale' => true])
            ->assertSuccessful();
    });

    it('preserves entries with usage', function (): void {
        $usedEntry = createEntry('1', 'Used Entry', 40, 'draft');
        $usedEntry['created_at'] = Carbon::now()->subDays(60)->toIso8601String();
        $usedEntry['usage_count'] = 5; // Has usage

        $this->qdrantMock->shouldReceive('scroll')
            ->andReturn(collect([$usedEntry]));

        // Should NOT archive because it has usage
        $this->qdrantMock->shouldNotReceive('updateFields');

        $this->artisan('synthesize', ['--archive-stale' => true])
            ->assertSuccessful();
    });

    it('respects custom stale-days option', function (): void {
        $entry = createEntry('1', 'Entry', 40, 'draft');
        $entry['created_at'] = Carbon::now()->subDays(10)->toIso8601String();
        $entry['usage_count'] = 0;

        $this->qdrantMock->shouldReceive('scroll')
            ->andReturn(collect([$entry]));

        // With default 30 days, this entry is NOT stale
        $this->qdrantMock->shouldNotReceive('updateFields');

        $this->artisan('synthesize', ['--archive-stale' => true])
            ->assertSuccessful();
    });

    it('archives with custom stale-days threshold', function (): void {
        $entry = createEntry('1', 'Entry', 40, 'draft');
        $entry['created_at'] = Carbon::now()->subDays(10)->toIso8601String();
        $entry['usage_count'] = 0;

        $this->qdrantMock->shouldReceive('scroll')
            ->andReturn(collect([$entry]));

        // With 7 day threshold, this entry IS stale
        $this->qdrantMock->shouldReceive('updateFields')
            ->once()
            ->with('1', ['status' => 'deprecated'])
            ->andReturn(true);

        $this->artisan('synthesize', ['--archive-stale' => true, '--stale-days' => '7'])
            ->assertSuccessful();
    });
});

describe('full run', function (): void {
    it('runs all operations when no flags specified', function (): void {
        // Dedupe
        $this->qdrantMock->shouldReceive('scroll')
            ->with(['status' => 'draft'], 100)
            ->andReturn(collect());

        // Digest
        $this->qdrantMock->shouldReceive('search')
            ->with('Daily Synthesis - 2026-02-03', ['tag' => 'daily-synthesis'], 1)
            ->andReturn(collect());

        $this->qdrantMock->shouldReceive('scroll')
            ->with(['status' => 'validated'], 50)
            ->andReturn(collect());

        // Archive
        $this->qdrantMock->shouldReceive('scroll')
            ->with(['status' => 'draft'], 200)
            ->andReturn(collect());

        $this->artisan('synthesize')
            ->assertSuccessful();
    });
});

// Helper functions
function createEntry(
    string $id,
    string $title,
    int $confidence,
    string $status,
    float $score = 0.0
): array {
    return [
        'id' => $id,
        'title' => $title,
        'content' => "Content for {$title}",
        'confidence' => $confidence,
        'status' => $status,
        'score' => $score,
        'category' => 'architecture',
        'tags' => [],
        'module' => null,
        'priority' => 'medium',
        'usage_count' => 0,
        'created_at' => Carbon::now()->toIso8601String(),
        'updated_at' => Carbon::now()->toIso8601String(),
    ];
}

function mockEmptyDigestAndArchive(Mockery\MockInterface $mock): void
{
    // Digest check
    $mock->shouldReceive('search')
        ->with(Mockery::pattern('/Daily Synthesis/'), Mockery::any(), 1)
        ->andReturn(collect());

    $mock->shouldReceive('scroll')
        ->with(['status' => 'validated'], 50)
        ->andReturn(collect());

    // Archive stale
    $mock->shouldReceive('scroll')
        ->with(['status' => 'draft'], 200)
        ->andReturn(collect());
}
