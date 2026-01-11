<?php

declare(strict_types=1);

use App\Services\GitContextService;
use App\Services\QdrantService;

beforeEach(function () {
    $this->mockQdrant = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->mockQdrant);
});

afterEach(function () {
    Mockery::close();
});

it('adds a knowledge entry with all options', function () {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['title'] === 'Test Entry Title'
                && $data['content'] === 'This is the detailed explanation'
                && $data['category'] === 'architecture'
                && $data['tags'] === ['module.submodule', 'patterns']
                && $data['confidence'] === 85
                && $data['priority'] === 'high'
                && $data['status'] === 'draft'
                && isset($data['id']);
        }))
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

it('adds a knowledge entry with minimal options', function () {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['title'] === 'Minimal Entry'
                && $data['content'] === 'Content here'
                && $data['priority'] === 'medium'
                && $data['confidence'] === 50
                && $data['status'] === 'draft';
        }))
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Minimal Entry',
        '--content' => 'Content here',
    ])->assertSuccessful();
});

it('validates confidence must be between 0 and 100', function () {
    $this->mockQdrant->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--confidence' => 150,
    ])->assertFailed();
});

it('validates confidence cannot be negative', function () {
    $this->mockQdrant->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--confidence' => -10,
    ])->assertFailed();
});

it('validates priority must be valid enum value', function () {
    $this->mockQdrant->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--priority' => 'invalid',
    ])->assertFailed();
});

it('validates category must be valid enum value', function () {
    $this->mockQdrant->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--category' => 'invalid-category',
    ])->assertFailed();
});

it('validates status must be valid enum value', function () {
    $this->mockQdrant->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--status' => 'invalid-status',
    ])->assertFailed();
});

it('requires title argument', function () {
    $this->mockQdrant->shouldNotReceive('upsert');

    expect(function () {
        $this->artisan('add');
    })->toThrow(\RuntimeException::class, 'Not enough arguments');
});

it('requires content option', function () {
    $this->mockQdrant->shouldNotReceive('upsert');

    $this->artisan('add', [
        'title' => 'Test Entry',
    ])->assertFailed();
});

it('accepts comma-separated tags', function () {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['tags'] === ['tag1', 'tag2', 'tag3'];
        }))
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--tags' => 'tag1,tag2,tag3',
    ])->assertSuccessful();
});

it('accepts single tag', function () {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['tags'] === ['single-tag'];
        }))
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--tags' => 'single-tag',
    ])->assertSuccessful();
});

it('accepts module option', function () {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['module'] === 'Blood';
        }))
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--module' => 'Blood',
    ])->assertSuccessful();
});

it('accepts source, ticket, and author options', function () {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['source'] === 'https://example.com'
                && $data['ticket'] === 'JIRA-123'
                && $data['author'] === 'John Doe';
        }))
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--source' => 'https://example.com',
        '--ticket' => 'JIRA-123',
        '--author' => 'John Doe',
    ])->assertSuccessful();
});

it('accepts status option', function () {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['status'] === 'validated';
        }))
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--status' => 'validated',
    ])->assertSuccessful();
});

it('auto-populates git context when in git repository', function () {
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
        ->with(Mockery::on(function ($data) {
            return $data['repo'] === 'https://github.com/test/repo'
                && $data['branch'] === 'feature/test'
                && $data['commit'] === 'abc123'
                && $data['author'] === 'Git User';
        }))
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
    ])->assertSuccessful();
});

it('skips git context when --no-git flag is used', function () {
    $mockGit = Mockery::mock(GitContextService::class);
    $mockGit->shouldNotReceive('isGitRepository');
    $mockGit->shouldNotReceive('getContext');

    $this->app->instance(GitContextService::class, $mockGit);

    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(function ($data) {
            return $data['repo'] === null
                && $data['branch'] === null
                && $data['commit'] === null
                && $data['author'] === null;
        }))
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
        '--no-git' => true,
    ])->assertSuccessful();
});

it('overrides auto-detected git values with manual options', function () {
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
        ->with(Mockery::on(function ($data) {
            return $data['repo'] === 'https://github.com/manual/repo'
                && $data['branch'] === 'manual-branch'
                && $data['commit'] === 'manual123'
                && $data['author'] === 'Manual User';
        }))
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

it('fails when QdrantService upsert returns false', function () {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->andReturn(false);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
    ])->assertFailed()
        ->expectsOutput('Failed to create knowledge entry');
});

it('displays success message with entry details', function () {
    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Success Entry',
        '--content' => 'Content here',
        '--category' => 'testing',
        '--priority' => 'high',
        '--confidence' => 95,
        '--tags' => 'tag1,tag2',
    ])->assertSuccessful()
        ->expectsOutputToContain('Knowledge entry created successfully with ID:')
        ->expectsOutputToContain('Title: Success Entry')
        ->expectsOutputToContain('Category: testing')
        ->expectsOutputToContain('Priority: high')
        ->expectsOutputToContain('Confidence: 95%')
        ->expectsOutputToContain('Tags: tag1, tag2');
});

it('generates unique UUID for entry ID', function () {
    $capturedId = null;

    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(function ($data) use (&$capturedId) {
            $capturedId = $data['id'];

            return isset($data['id']) && is_string($data['id']);
        }))
        ->andReturn(true);

    $this->artisan('add', [
        'title' => 'Test Entry',
        '--content' => 'Content',
    ])->assertSuccessful();

    expect($capturedId)->toBeString();
    expect(strlen($capturedId))->toBeGreaterThan(0);
});
