<?php

declare(strict_types=1);

use App\Exceptions\Qdrant\DuplicateEntryException;
use App\Services\GitContextService;
use App\Services\QdrantService;
use App\Services\WriteGateService;

beforeEach(function (): void {
    $this->gitService = mock(GitContextService::class);
    $this->qdrantService = mock(QdrantService::class);
    $this->writeGateService = mock(WriteGateService::class);
    $this->writeGateService->shouldReceive('evaluate')
        ->andReturn(['passed' => true, 'matched' => ['durable_facts'], 'reason' => ''])
        ->byDefault();

    app()->instance(GitContextService::class, $this->gitService);
    app()->instance(QdrantService::class, $this->qdrantService);
    app()->instance(WriteGateService::class, $this->writeGateService);
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

it('validates category is valid', function (): void {
    $this->qdrantService->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Invalid Category',
        '--content' => 'Test',
        '--category' => 'invalid-category',
    ])->assertFailed();
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

it('fails on exact hash duplicate', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->andThrow(DuplicateEntryException::hashMatch('existing-id', 'hash123'));

    $this->artisan('add', [
        'title' => 'Duplicate Entry',
        '--content' => 'Duplicate content',
    ])->assertFailed();
});

it('prompts to supersede when similarity duplicate detected and user confirms', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::any(), Mockery::any(), true)
        ->andThrow(DuplicateEntryException::similarityMatch('existing-id', 0.97));

    // User confirms supersession
    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::any(), 'default', false)
        ->andReturn(true);

    $this->qdrantService->shouldReceive('markSuperseded')
        ->once()
        ->with('existing-id', Mockery::type('string'), Mockery::type('string'))
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Updated Entry',
        '--content' => 'Updated content',
        '--confidence' => 80,
    ])
        ->expectsConfirmation("Supersede existing entry 'existing-id' with this new entry?", 'yes')
        ->assertSuccessful();
});

it('aborts when user declines supersession', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::any(), Mockery::any(), true)
        ->andThrow(DuplicateEntryException::similarityMatch('existing-id', 0.96));

    $this->artisan('add', [
        'title' => 'Updated Entry',
        '--content' => 'Updated content',
        '--confidence' => 80,
    ])
        ->expectsConfirmation("Supersede existing entry 'existing-id' with this new entry?", 'no')
        ->assertFailed();
});

it('warns about low confidence when superseding', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::any(), Mockery::any(), true)
        ->andThrow(DuplicateEntryException::similarityMatch('existing-id', 0.96));

    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::any(), 'default', false)
        ->andReturn(true);

    $this->qdrantService->shouldReceive('markSuperseded')
        ->once()
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Low Confidence Entry',
        '--content' => 'Content',
        '--confidence' => 30,
    ])
        ->expectsConfirmation("Supersede existing entry 'existing-id' with this new entry?", 'yes')
        ->expectsOutputToContain('Knowledge entry created')
        ->assertSuccessful();
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

it('fails when re-upsert during supersession returns false', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    // First upsert throws similarity match
    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::any(), Mockery::any(), true)
        ->andThrow(DuplicateEntryException::similarityMatch('existing-id', 0.95));

    // User confirms, but re-upsert (without duplicate check) returns false
    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::any(), 'default', false)
        ->andReturn(false);

    $this->qdrantService->shouldNotReceive('markSuperseded');

    $this->artisan('add', [
        'title' => 'Supersede Fail Entry',
        '--content' => 'Content that fails on re-upsert',
        '--confidence' => 80,
    ])
        ->expectsConfirmation("Supersede existing entry 'existing-id' with this new entry?", 'yes')
        ->assertFailed()
        ->expectsOutputToContain('Failed to create knowledge entry');
});

it('succeeds with warning when markSuperseded returns false', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    // First upsert throws similarity match
    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::any(), Mockery::any(), true)
        ->andThrow(DuplicateEntryException::similarityMatch('existing-id', 0.92));

    // Re-upsert succeeds
    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::any(), 'default', false)
        ->andReturn(true);

    // markSuperseded fails
    $this->qdrantService->shouldReceive('markSuperseded')
        ->once()
        ->with('existing-id', Mockery::type('string'), Mockery::type('string'))
        ->andReturn(false);

    $this->artisan('add', [
        'title' => 'Supersede Mark Fail',
        '--content' => 'Content where mark fails',
        '--confidence' => 80,
    ])
        ->expectsConfirmation("Supersede existing entry 'existing-id' with this new entry?", 'yes')
        ->expectsOutputToContain('failed to mark old entry as superseded')
        ->assertSuccessful();
});
