<?php

declare(strict_types=1);

use App\Exceptions\Qdrant\DuplicateEntryException;
use App\Services\EnhancementQueueService;
use App\Services\GitContextService;
use App\Services\QdrantService;
use App\Services\WriteGateService;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function (): void {
    $this->gitService = mock(GitContextService::class);
    $this->qdrantService = mock(QdrantService::class);
    $this->writeGateService = mock(WriteGateService::class);
    $this->writeGateService->shouldReceive('evaluate')
        ->andReturn(['passed' => true, 'matched' => ['durable_facts'], 'reason' => ''])
        ->byDefault();
    $this->enhancementQueue = mock(EnhancementQueueService::class);

    app()->instance(GitContextService::class, $this->gitService);
    app()->instance(QdrantService::class, $this->qdrantService);
    app()->instance(WriteGateService::class, $this->writeGateService);
    app()->instance(EnhancementQueueService::class, $this->enhancementQueue);
    mockProjectDetector();

    $this->enhancementQueue->shouldReceive('queue')->zeroOrMoreTimes();

    config(['search.ollama.enabled' => true]);
});

afterEach(function (): void {
});

it('creates a knowledge entry with required fields', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['title'] === 'Test Entry'
            && $data['content'] === 'Test content'
            && isset($data['id'])), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Test content',
    ])->assertSuccessful();
});

it('auto-populates git fields when in a git repository', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(true);
    $this->gitService->shouldReceive('getContext')->andReturn([
        'repo' => 'test/repo',
        'branch' => 'main',
        'commit' => 'abc123',
        'author' => 'Test Author',
    ]);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['repo'] === 'test/repo'
            && $data['branch'] === 'main'
            && $data['commit'] === 'abc123'
            && $data['author'] === 'Test Author'), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Git Auto Entry',
        '--content' => 'Content with git context',
    ])->assertSuccessful();
});

it('skips git detection with --no-git flag', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->never();

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['repo'] === null
            && $data['branch'] === null
            && $data['commit'] === null
            && $data['author'] === null), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'No Git Entry',
        '--content' => 'Content without git',
        '--no-git' => true,
    ])->assertSuccessful();
});

it('allows manual git field overrides', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(true);
    $this->gitService->shouldReceive('getContext')->andReturn([
        'repo' => 'auto/repo',
        'branch' => 'auto-branch',
        'commit' => 'auto123',
        'author' => 'Auto Author',
    ]);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['repo'] === 'custom/repo'
            && $data['branch'] === 'custom-branch'
            && $data['commit'] === 'abc123'), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Manual Git Entry',
        '--content' => 'Content with manual git',
        '--repo' => 'custom/repo',
        '--branch' => 'custom-branch',
        '--commit' => 'abc123',
    ])->assertSuccessful();
});

it('validates required content field', function (): void {
    $this->qdrantService->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'No Content Entry',
    ])->assertFailed();
});

it('validates confidence range', function (): void {
    $this->qdrantService->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Invalid Confidence',
        '--content' => 'Test',
        '--confidence' => 150,
    ])->assertFailed();
});

it('creates entry with tags', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['tags'] === ['php', 'laravel', 'testing']), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Tagged Entry',
        '--content' => 'Content',
        '--tags' => 'php,laravel,testing',
    ])->assertSuccessful();
});

it('accepts unknown category and stores null', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['title'] === 'Test Entry'
            && $data['content'] === 'Test content'
            && $data['category'] === null), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Test content',
        '--category' => 'invalid-category',
    ])->assertSuccessful();
});

it('validates priority is valid', function (): void {
    $this->qdrantService->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Invalid Priority',
        '--content' => 'Test',
        '--priority' => 'super-urgent',
    ])->assertFailed();
});

it('validates status is valid', function (): void {
    $this->qdrantService->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Invalid Status',
        '--content' => 'Test',
        '--status' => 'archived',
    ])->assertFailed();
});

it('handles Qdrant upsert failure gracefully', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->andReturn(false);

    $this->artisan('add', [
        'title' => 'Failed Entry',
        '--content' => 'This will fail',
    ])->assertFailed();
});

it('queues entry for enhancement by default', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->andReturn(true);

    // Reset the default expectation and set a specific one
    $this->enhancementQueue = mock(EnhancementQueueService::class);
    app()->instance(EnhancementQueueService::class, $this->enhancementQueue);

    $this->enhancementQueue->shouldReceive('queue')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['title'] === 'Enhanced Entry'), 'default');

    $this->artisan('add', [
        'title' => 'Enhanced Entry',
        '--content' => 'Content to enhance',
    ])->assertSuccessful();
});

it('skips enhancement queue with --skip-enhance flag', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->andReturn(true);

    // Reset the default expectation and set a specific one
    $this->enhancementQueue = mock(EnhancementQueueService::class);
    app()->instance(EnhancementQueueService::class, $this->enhancementQueue);

    $this->enhancementQueue->shouldNotReceive('queue');

    $this->artisan('add', [
        'title' => 'Fast Entry',
        '--content' => 'Fast content',
        '--skip-enhance' => true,
    ])->assertSuccessful();
});

it('skips enhancement queue when Ollama is disabled', function (): void {
    config(['search.ollama.enabled' => false]);

    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->andReturn(true);

    $this->enhancementQueue = mock(EnhancementQueueService::class);
    app()->instance(EnhancementQueueService::class, $this->enhancementQueue);

    $this->enhancementQueue->shouldNotReceive('queue');

    $this->artisan('add', [
        'title' => 'No Ollama Entry',
        '--content' => 'Content without Ollama',
    ])->assertSuccessful();
});

it('creates entry with all optional fields', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['title'] === 'Full Entry'
            && $data['content'] === 'Full content'
            && $data['category'] === 'testing'
            && $data['module'] === 'TestModule'
            && $data['priority'] === 'high'
            && $data['confidence'] === 85
            && $data['source'] === 'https://example.com'
            && $data['ticket'] === 'JIRA-123'
            && $data['status'] === 'validated'
            && $data['tags'] === ['php', 'testing']), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Full Entry',
        '--content' => 'Full content',
        '--category' => 'testing',
        '--tags' => 'php,testing',
        '--module' => 'TestModule',
        '--priority' => 'high',
        '--confidence' => 85,
        '--source' => 'https://example.com',
        '--ticket' => 'JIRA-123',
        '--status' => 'validated',
    ])->assertSuccessful();
});

describe('write gate integration', function (): void {
    it('rejects entries that fail the write gate', function (): void {
        $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

        $this->writeGateService->shouldReceive('evaluate')
            ->once()
            ->andReturn([
                'passed' => false,
                'matched' => [],
                'reason' => 'Entry does not meet any write gate criteria. Use --force to bypass.',
            ]);

        $this->qdrantService->shouldNotReceive('upsert');

        $this->artisan('add', [
            'title' => 'Low value note',
            '--content' => 'Talked to Bob about lunch',
        ])->assertFailed();
    });

    it('bypasses write gate with --force flag', function (): void {
        // Write gate should NOT be called when --force is used
        $this->writeGateService->shouldNotReceive('evaluate');

        $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

        $this->qdrantService->shouldReceive('upsert')
            ->once()
            ->andReturn(true);

        $this->artisan('add', [
            'title' => 'Forced entry',
            '--content' => 'This bypasses the gate',
            '--force' => true,
        ])->assertSuccessful();
    });

    it('passes entry data to write gate for evaluation', function (): void {
        $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

        $this->writeGateService->shouldReceive('evaluate')
            ->once()
            ->with(Mockery::on(fn ($data): bool => $data['title'] === 'Architecture Decision'
                && $data['content'] === 'We chose event sourcing because of auditability'
                && $data['category'] === 'architecture'
                && $data['priority'] === 'high'
                && $data['confidence'] === 90))
            ->andReturn(['passed' => true, 'matched' => ['decision_rationale', 'commitment_weight'], 'reason' => '']);

        $this->qdrantService->shouldReceive('upsert')
            ->once()
            ->andReturn(true);

        $this->artisan('add', [
            'title' => 'Architecture Decision',
            '--content' => 'We chose event sourcing because of auditability',
            '--category' => 'architecture',
            '--priority' => 'high',
            '--confidence' => 90,
        ])->assertSuccessful();
    });
});

it('returns existing id on exact hash duplicate (non-TTY)', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->andThrow(DuplicateEntryException::hashMatch('existing-id', 'hash123'));

    $this->artisan('add', [
        'title' => 'Duplicate Entry',
        '--content' => 'Duplicate content',
    ])->expectsOutputToContain('existing-id')->assertSuccessful();
});

it('returns existing id on similarity match (non-TTY)', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->andThrow(DuplicateEntryException::similarityMatch('existing-id', 0.92, 'similar content'));

    $this->artisan('add', [
        'title' => 'Similar Entry',
        '--content' => 'New content',
    ])->expectsOutputToContain('existing-id')->assertSuccessful();
});

it('returns existing id on duplicate with non-zero exit on stderr when not quiet', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->andThrow(DuplicateEntryException::hashMatch('existing-id-123', 'hash123'));

    $this->artisan('add', [
        'title' => 'Duplicate Entry',
        '--content' => 'Duplicate content',
    ])->expectsOutputToContain('existing-id-123')->assertSuccessful();
});

it('returns existing id with quiet flag (stderr silenced)', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->andThrow(DuplicateEntryException::similarityMatch('existing-id-456', 0.88, 'similar'));

    $this->artisan('add', [
        'title' => 'Duplicate Quiet',
        '--content' => 'Duplicate content',
        '--quiet' => true,
    ])->assertSuccessful();
});

it('skips duplicate detection with --force flag', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::any(), Mockery::any(), false)
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Forced Entry',
        '--content' => 'Content',
        '--force' => true,
    ])->assertSuccessful();
});

it('handles WriteGate rejection with non-zero exit', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->writeGateService->shouldReceive('evaluate')
        ->once()
        ->andReturn([
            'passed' => false,
            'matched' => [],
            'reason' => 'Quality check failed',
        ]);

    $this->qdrantService->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Rejected Entry',
        '--content' => 'Rejected content',
    ])->assertFailed();
});

it('handles WriteGate rejection with quiet flag (stderr silenced)', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->writeGateService->shouldReceive('evaluate')
        ->once()
        ->andReturn([
            'passed' => false,
            'matched' => [],
            'reason' => 'Quality check failed',
        ]);

    $this->qdrantService->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Quiet Rejected',
        '--content' => 'Rejected content',
        '--quiet' => true,
    ])->assertFailed();
});
