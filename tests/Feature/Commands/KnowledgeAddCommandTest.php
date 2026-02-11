<?php

declare(strict_types=1);

use App\Exceptions\Qdrant\DuplicateEntryException;
use App\Services\GitContextService;
use App\Services\QdrantService;
use App\Services\WriteGateService;

beforeEach(function (): void {
    $this->mockQdrant = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->mockQdrant);

    $this->mockWriteGate = Mockery::mock(WriteGateService::class);
    $this->mockWriteGate->shouldReceive('evaluate')
        ->andReturn(['passed' => true, 'matched' => ['durable_facts'], 'reason' => ''])
        ->byDefault();
    $this->app->instance(WriteGateService::class, $this->mockWriteGate);
    mockProjectDetector();
});

afterEach(function (): void {
    Mockery::close();
});

it('adds a knowledge entry with all options', function (): void {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['title'] === 'Test Entry Title'
            && $data['content'] === 'This is the detailed explanation'
            && $data['category'] === 'architecture'
            && $data['tags'] === ['module.submodule', 'patterns']
            && $data['confidence'] === 85
            && $data['priority'] === 'high'
            && $data['status'] === 'draft'
            && isset($data['id'])), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry Title',
        '--content' => 'This is the detailed explanation',
        '--category' => 'architecture',
        '--tags' => 'module.submodule,patterns',
        '--confidence' => 85,
        '--priority' => 'high',
    ])->assertSuccessful();
});

it('adds a knowledge entry with minimal options', function (): void {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['title'] === 'Minimal Entry'
            && $data['content'] === 'Content here'
            && $data['priority'] === 'medium'
            && $data['confidence'] === 50
            && $data['status'] === 'draft'), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Minimal Entry',
        '--content' => 'Content here',
    ])->assertSuccessful();
});

it('validates confidence must be between 0 and 100', function (): void {
    $this->mockQdrant->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--confidence' => 150,
    ])->assertFailed();
});

it('validates confidence cannot be negative', function (): void {
    $this->mockQdrant->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--confidence' => -10,
    ])->assertFailed();
});

it('validates priority must be valid enum value', function (): void {
    $this->mockQdrant->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--priority' => 'invalid',
    ])->assertFailed();
});

it('validates category must be valid enum value', function (): void {
    $this->mockQdrant->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--category' => 'invalid-category',
    ])->assertFailed();
});

it('validates status must be valid enum value', function (): void {
    $this->mockQdrant->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--status' => 'invalid-status',
    ])->assertFailed();
});

it('requires title argument', function (): void {
    $this->mockQdrant->shouldNotReceive('upsert');

    expect(function (): void {
        $this->artisan('add');
    })->toThrow(\RuntimeException::class, 'Not enough arguments');
});

it('requires content option', function (): void {
    $this->mockQdrant->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Test Entry',
    ])->assertFailed();
});

it('accepts comma-separated tags', function (): void {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['tags'] === ['tag1', 'tag2', 'tag3']), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--tags' => 'tag1,tag2,tag3',
    ])->assertSuccessful();
});

it('accepts single tag', function (): void {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['tags'] === ['single-tag']), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--tags' => 'single-tag',
    ])->assertSuccessful();
});

it('accepts module option', function (): void {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['module'] === 'Blood'), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--module' => 'Blood',
    ])->assertSuccessful();
});

it('accepts source, ticket, and author options', function (): void {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['source'] === 'https://example.com'
            && $data['ticket'] === 'JIRA-123'
            && $data['author'] === 'John Doe'), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--source' => 'https://example.com',
        '--ticket' => 'JIRA-123',
        '--author' => 'John Doe',
    ])->assertSuccessful();
});

it('accepts status option', function (): void {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['status'] === 'validated'), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--status' => 'validated',
    ])->assertSuccessful();
});

it('auto-populates git context when in git repository', function (): void {
    $mockGit = Mockery::mock(GitContextService::class);
    $mockGit->shouldReceive('isGitRepository')->andReturn(true);
    $mockGit->shouldReceive('getContext')->andReturn([
        'repo' => 'https://github.com/test/repo',
        'branch' => 'feature/test',
        'commit' => 'abc123',
        'author' => 'Git User',
    ]);

    $this->app->instance(GitContextService::class, $mockGit);

    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['repo'] === 'https://github.com/test/repo'
            && $data['branch'] === 'feature/test'
            && $data['commit'] === 'abc123'
            && $data['author'] === 'Git User'), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
    ])->assertSuccessful();
});

it('skips git context when --no-git flag is used', function (): void {
    $mockGit = Mockery::mock(GitContextService::class);
    $mockGit->shouldNotReceive('isGitRepository');
    $mockGit->shouldNotReceive('getContext');

    $this->app->instance(GitContextService::class, $mockGit);

    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['repo'] === null
            && $data['branch'] === null
            && $data['commit'] === null
            && $data['author'] === null), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--no-git' => true,
    ])->assertSuccessful();
});

it('overrides auto-detected git values with manual options', function (): void {
    $mockGit = Mockery::mock(GitContextService::class);
    $mockGit->shouldReceive('isGitRepository')->andReturn(true);
    $mockGit->shouldReceive('getContext')->andReturn([
        'repo' => 'https://github.com/auto/repo',
        'branch' => 'auto-branch',
        'commit' => 'auto123',
        'author' => 'Auto User',
    ]);

    $this->app->instance(GitContextService::class, $mockGit);

    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['repo'] === 'https://github.com/manual/repo'
            && $data['branch'] === 'manual-branch'
            && $data['commit'] === 'manual123'
            && $data['author'] === 'Manual User'), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--repo' => 'https://github.com/manual/repo',
        '--branch' => 'manual-branch',
        '--commit' => 'manual123',
        '--author' => 'Manual User',
    ])->assertSuccessful();
});

it('fails when QdrantService upsert returns false', function (): void {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->andReturn(false);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
    ])->assertFailed()
        ->expectsOutputToContain('Failed to create knowledge entry');
});

it('displays success message with entry details', function (): void {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->andReturn(true);

    // Laravel Prompts table output may not be fully captured by test framework
    // We verify the command succeeds and produces some output
    $this->artisan('add', [
        'title' => 'Success Entry',
        '--content' => 'Content here',
        '--category' => 'testing',
        '--priority' => 'high',
        '--confidence' => 95,
        '--tags' => 'tag1,tag2',
    ])->assertSuccessful()
        ->expectsOutputToContain('Knowledge entry created');
});

it('generates unique UUID for entry ID', function (): void {
    $capturedId = null;

    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(function ($data) use (&$capturedId): bool {
            $capturedId = $data['id'];

            return isset($data['id']) && is_string($data['id']);
        }), Mockery::any(), Mockery::any())
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
    ])->assertSuccessful();

    expect($capturedId)->toBeString();
    expect(strlen((string) $capturedId))->toBeGreaterThan(0);
});

it('fails when duplicate hash is detected', function (): void {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->andThrow(DuplicateEntryException::hashMatch('existing-id-123', 'abc123'));

    $this->artisan('add', [
        'title' => 'Duplicate Entry',
        '--content' => 'Same content as existing',
    ])->assertFailed()
        ->expectsOutputToContain('Duplicate content detected');
});

it('fails when similar entry is detected and user declines', function (): void {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->andThrow(DuplicateEntryException::similarityMatch('similar-id-456', 0.97));

    $this->artisan('add', [
        'title' => 'Similar Entry',
        '--content' => 'Very similar content',
    ])
        ->expectsConfirmation("Supersede existing entry 'similar-id-456' with this new entry?", 'no')
        ->assertFailed()
        ->expectsOutputToContain('duplicate detected');
});

it('allows duplicate override with --force flag', function (): void {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::any(), 'default', false) // checkDuplicates = false
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Force Entry',
        '--content' => 'Content',
        '--force' => true,
    ])->assertSuccessful();
});

it('passes checkDuplicates=true by default', function (): void {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::any(), 'default', true) // checkDuplicates = true
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Normal Entry',
        '--content' => 'Content',
    ])->assertSuccessful();
});
