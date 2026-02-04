<?php

declare(strict_types=1);

use App\Services\GitContextService;
use App\Services\QdrantService;

beforeEach(function (): void {
    $this->gitService = mock(GitContextService::class);
    $this->qdrantService = mock(QdrantService::class);

    app()->instance(GitContextService::class, $this->gitService);
    app()->instance(QdrantService::class, $this->qdrantService);
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
