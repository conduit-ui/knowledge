<?php

declare(strict_types=1);

use App\Services\DailyLogService;
use App\Services\GitContextService;

beforeEach(function (): void {
    $this->dailyLogService = mock(DailyLogService::class);
    $this->gitService = mock(GitContextService::class);

    app()->instance(DailyLogService::class, $this->dailyLogService);
    app()->instance(GitContextService::class, $this->gitService);
});

it('stages a knowledge entry with required fields', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->dailyLogService->shouldReceive('stage')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['title'] === 'Test Entry'
            && $data['content'] === 'Test content'
            && $data['section'] === 'Notes'))
        ->andReturn('test-uuid');

    $this->artisan('stage', [
        'title' => 'Test Entry',
        '--content' => 'Test content',
    ])->assertSuccessful();
});

it('stages entry with all options', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->dailyLogService->shouldReceive('stage')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['title'] === 'Full Entry'
            && $data['content'] === 'Full content'
            && $data['section'] === 'Decisions'
            && $data['category'] === 'architecture'
            && $data['tags'] === ['php', 'laravel']
            && $data['priority'] === 'high'
            && $data['confidence'] === 90
            && $data['source'] === 'https://example.com'
            && $data['ticket'] === 'JIRA-123'
            && $data['author'] === 'Test Author'))
        ->andReturn('test-uuid');

    $this->artisan('stage', [
        'title' => 'Full Entry',
        '--content' => 'Full content',
        '--section' => 'Decisions',
        '--category' => 'architecture',
        '--tags' => 'php,laravel',
        '--priority' => 'high',
        '--confidence' => 90,
        '--source' => 'https://example.com',
        '--ticket' => 'JIRA-123',
        '--author' => 'Test Author',
    ])->assertSuccessful();
});

it('validates required content field', function (): void {
    $this->dailyLogService->shouldNotReceive('stage');

    $this->artisan('stage', [
        'title' => 'No Content',
    ])->assertFailed();
});

it('validates confidence range', function (): void {
    $this->dailyLogService->shouldNotReceive('stage');

    $this->artisan('stage', [
        'title' => 'Invalid Confidence',
        '--content' => 'Test',
        '--confidence' => 150,
    ])->assertFailed();
});

it('validates section is valid', function (): void {
    $this->dailyLogService->shouldNotReceive('stage');

    $this->artisan('stage', [
        'title' => 'Invalid Section',
        '--content' => 'Test',
        '--section' => 'InvalidSection',
    ])->assertFailed();
});

it('validates category is valid', function (): void {
    $this->dailyLogService->shouldNotReceive('stage');

    $this->artisan('stage', [
        'title' => 'Invalid Category',
        '--content' => 'Test',
        '--category' => 'invalid-category',
    ])->assertFailed();
});

it('validates priority is valid', function (): void {
    $this->dailyLogService->shouldNotReceive('stage');

    $this->artisan('stage', [
        'title' => 'Invalid Priority',
        '--content' => 'Test',
        '--priority' => 'super-urgent',
    ])->assertFailed();
});

it('auto-detects author from git context', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(true);
    $this->gitService->shouldReceive('getContext')->andReturn([
        'repo' => 'test/repo',
        'branch' => 'main',
        'commit' => 'abc123',
        'author' => 'Git Author',
    ]);

    $this->dailyLogService->shouldReceive('stage')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['author'] === 'Git Author'))
        ->andReturn('test-uuid');

    $this->artisan('stage', [
        'title' => 'Git Entry',
        '--content' => 'Content',
    ])->assertSuccessful();
});

it('skips git detection with --no-git flag', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->never();

    $this->dailyLogService->shouldReceive('stage')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['author'] === null))
        ->andReturn('test-uuid');

    $this->artisan('stage', [
        'title' => 'No Git',
        '--content' => 'Content',
        '--no-git' => true,
    ])->assertSuccessful();
});

it('stages entry in Corrections section', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->dailyLogService->shouldReceive('stage')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['section'] === 'Corrections'))
        ->andReturn('test-uuid');

    $this->artisan('stage', [
        'title' => 'A Correction',
        '--content' => 'Content',
        '--section' => 'Corrections',
    ])->assertSuccessful();
});

it('stages entry in Commitments section', function (): void {
    $this->gitService->shouldReceive('isGitRepository')->andReturn(false);

    $this->dailyLogService->shouldReceive('stage')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['section'] === 'Commitments'))
        ->andReturn('test-uuid');

    $this->artisan('stage', [
        'title' => 'A Commitment',
        '--content' => 'Content',
        '--section' => 'Commitments',
    ])->assertSuccessful();
});
