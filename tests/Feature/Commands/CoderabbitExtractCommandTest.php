<?php

declare(strict_types=1);

use App\Services\CodeRabbitService;
use App\Services\QdrantService;

beforeEach(function (): void {
    $this->mockQdrant = Mockery::mock(QdrantService::class);
    $this->mockCodeRabbit = Mockery::mock(CodeRabbitService::class);
    $this->app->instance(QdrantService::class, $this->mockQdrant);
    $this->app->instance(CodeRabbitService::class, $this->mockCodeRabbit);
});

afterEach(function (): void {
    Mockery::close();
});

it('requires --pr option', function (): void {
    $this->artisan('coderabbit:extract')
        ->assertFailed()
        ->expectsOutputToContain('--pr option is required');
});

it('validates --pr must be a positive integer', function (): void {
    $this->artisan('coderabbit:extract', ['--pr' => 'abc'])
        ->assertFailed()
        ->expectsOutputToContain('positive integer');
});

it('validates --pr rejects zero', function (): void {
    $this->artisan('coderabbit:extract', ['--pr' => '0'])
        ->assertFailed()
        ->expectsOutputToContain('positive integer');
});

it('validates --min-severity must be valid', function (): void {
    $this->artisan('coderabbit:extract', ['--pr' => '42', '--min-severity' => 'extreme'])
        ->assertFailed()
        ->expectsOutputToContain('Invalid --min-severity');
});

it('fails when PR fetch returns null', function (): void {
    $this->mockCodeRabbit->shouldReceive('fetchReviewComments')
        ->once()
        ->with(42)
        ->andReturn(null);

    $this->artisan('coderabbit:extract', ['--pr' => '42'])
        ->assertFailed()
        ->expectsOutputToContain('Failed to fetch PR #42');
});

it('succeeds with no coderabbit comments found', function (): void {
    $this->mockCodeRabbit->shouldReceive('fetchReviewComments')
        ->once()
        ->with(10)
        ->andReturn([
            'comments' => [],
            'pr_title' => 'Test PR',
            'pr_url' => 'https://github.com/test/repo/pull/10',
        ]);

    $this->artisan('coderabbit:extract', ['--pr' => '10'])
        ->assertSuccessful()
        ->expectsOutputToContain('No CodeRabbit review comments found');
});

it('succeeds with no actionable findings from comments', function (): void {
    $this->mockCodeRabbit->shouldReceive('fetchReviewComments')
        ->once()
        ->with(10)
        ->andReturn([
            'comments' => [
                ['body' => '', 'path' => null, 'author' => 'coderabbitai[bot]'],
            ],
            'pr_title' => 'Test PR',
            'pr_url' => 'https://github.com/test/repo/pull/10',
        ]);

    $this->mockCodeRabbit->shouldReceive('parseFindings')
        ->once()
        ->andReturn([]);

    $this->artisan('coderabbit:extract', ['--pr' => '10'])
        ->assertSuccessful()
        ->expectsOutputToContain('No actionable findings');
});

it('succeeds with no findings above severity threshold', function (): void {
    $this->mockCodeRabbit->shouldReceive('fetchReviewComments')
        ->once()
        ->with(10)
        ->andReturn([
            'comments' => [
                ['body' => 'Nit: fix typo', 'path' => 'src/app.php', 'author' => 'coderabbitai[bot]'],
            ],
            'pr_title' => 'Test PR',
            'pr_url' => 'https://github.com/test/repo/pull/10',
        ]);

    $this->mockCodeRabbit->shouldReceive('parseFindings')
        ->once()
        ->andReturn([
            ['title' => 'Fix typo', 'content' => 'Nit: fix typo', 'file' => 'src/app.php', 'severity' => 'low', 'confidence' => 45],
        ]);

    $this->mockCodeRabbit->shouldReceive('filterBySeverity')
        ->once()
        ->with(Mockery::any(), 'high')
        ->andReturn([]);

    $this->artisan('coderabbit:extract', ['--pr' => '10', '--min-severity' => 'high'])
        ->assertSuccessful()
        ->expectsOutputToContain('No findings meet the minimum severity threshold');
});

it('extracts findings and adds to knowledge base', function (): void {
    $this->mockCodeRabbit->shouldReceive('fetchReviewComments')
        ->once()
        ->with(42)
        ->andReturn([
            'comments' => [
                ['body' => 'Bug: missing null check', 'path' => 'src/Service.php', 'author' => 'coderabbitai[bot]'],
            ],
            'pr_title' => 'Add feature X',
            'pr_url' => 'https://github.com/test/repo/pull/42',
        ]);

    $findings = [
        ['title' => 'Missing null check', 'content' => 'Bug: missing null check', 'file' => 'src/Service.php', 'severity' => 'high', 'confidence' => 75],
    ];

    $this->mockCodeRabbit->shouldReceive('parseFindings')
        ->once()
        ->andReturn($findings);

    $this->mockCodeRabbit->shouldReceive('filterBySeverity')
        ->once()
        ->with(Mockery::any(), 'low')
        ->andReturn($findings);

    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['id'] === 'knowledge-pr-42-coderabbit-1'
            && str_contains($data['title'], 'Missing null check')
            && $data['priority'] === 'high'
            && $data['confidence'] === 75
            && in_array('coderabbit', $data['tags'], true)
            && in_array('pr-42', $data['tags'], true)
            && $data['source'] === 'https://github.com/test/repo/pull/42'
        ), 'default', false)
        ->andReturn(true);

    $this->artisan('coderabbit:extract', ['--pr' => '42'])
        ->assertSuccessful()
        ->expectsOutputToContain('1 finding(s) added to knowledge base');
});

it('handles upsert failures gracefully', function (): void {
    $this->mockCodeRabbit->shouldReceive('fetchReviewComments')
        ->once()
        ->with(42)
        ->andReturn([
            'comments' => [
                ['body' => 'Bug: issue found', 'path' => null, 'author' => 'coderabbitai[bot]'],
            ],
            'pr_title' => 'PR Title',
            'pr_url' => 'https://github.com/test/repo/pull/42',
        ]);

    $findings = [
        ['title' => 'Issue found', 'content' => 'Bug: issue found', 'file' => null, 'severity' => 'high', 'confidence' => 75],
    ];

    $this->mockCodeRabbit->shouldReceive('parseFindings')->once()->andReturn($findings);
    $this->mockCodeRabbit->shouldReceive('filterBySeverity')->once()->andReturn($findings);

    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->andThrow(new \RuntimeException('Connection failed'));

    $this->artisan('coderabbit:extract', ['--pr' => '42'])
        ->assertSuccessful()
        ->expectsOutputToContain('1 finding(s) failed to store');
});

it('supports --dry-run flag', function (): void {
    $this->mockCodeRabbit->shouldReceive('fetchReviewComments')
        ->once()
        ->with(5)
        ->andReturn([
            'comments' => [
                ['body' => 'Consider refactoring this', 'path' => 'src/App.php', 'author' => 'coderabbitai[bot]'],
            ],
            'pr_title' => 'Refactor',
            'pr_url' => 'https://github.com/test/repo/pull/5',
        ]);

    $findings = [
        ['title' => 'Consider refactoring', 'content' => 'Consider refactoring this', 'file' => 'src/App.php', 'severity' => 'medium', 'confidence' => 60],
    ];

    $this->mockCodeRabbit->shouldReceive('parseFindings')->once()->andReturn($findings);
    $this->mockCodeRabbit->shouldReceive('filterBySeverity')->once()->andReturn($findings);

    $this->mockQdrant->shouldNotReceive('upsert');

    $this->artisan('coderabbit:extract', ['--pr' => '5', '--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Dry run complete');
});

it('adds multiple findings with sequential semantic IDs', function (): void {
    $this->mockCodeRabbit->shouldReceive('fetchReviewComments')
        ->once()
        ->with(99)
        ->andReturn([
            'comments' => [
                ['body' => 'Security: SQL injection risk', 'path' => 'src/Query.php', 'author' => 'coderabbitai[bot]'],
                ['body' => 'Nit: fix typo in variable name', 'path' => 'src/Helper.php', 'author' => 'coderabbitai[bot]'],
            ],
            'pr_title' => 'Multiple findings',
            'pr_url' => 'https://github.com/test/repo/pull/99',
        ]);

    $findings = [
        ['title' => 'SQL injection risk', 'content' => 'Security: SQL injection risk', 'file' => 'src/Query.php', 'severity' => 'critical', 'confidence' => 85],
        ['title' => 'Fix typo', 'content' => 'Nit: fix typo in variable name', 'file' => 'src/Helper.php', 'severity' => 'low', 'confidence' => 45],
    ];

    $this->mockCodeRabbit->shouldReceive('parseFindings')->once()->andReturn($findings);
    $this->mockCodeRabbit->shouldReceive('filterBySeverity')->once()->andReturn($findings);

    $capturedIds = [];
    $this->mockQdrant->shouldReceive('upsert')
        ->twice()
        ->with(Mockery::on(function ($data) use (&$capturedIds): bool {
            $capturedIds[] = $data['id'];

            return true;
        }), 'default', false)
        ->andReturn(true);

    $this->artisan('coderabbit:extract', ['--pr' => '99'])
        ->assertSuccessful()
        ->expectsOutputToContain('2 finding(s) added to knowledge base');

    expect($capturedIds)->toBe([
        'knowledge-pr-99-coderabbit-1',
        'knowledge-pr-99-coderabbit-2',
    ]);
});

it('handles upsert returning false', function (): void {
    $this->mockCodeRabbit->shouldReceive('fetchReviewComments')
        ->once()
        ->with(42)
        ->andReturn([
            'comments' => [
                ['body' => 'Some finding', 'path' => null, 'author' => 'coderabbitai[bot]'],
            ],
            'pr_title' => 'PR Title',
            'pr_url' => 'https://github.com/test/repo/pull/42',
        ]);

    $findings = [
        ['title' => 'Some finding', 'content' => 'Some finding', 'file' => null, 'severity' => 'medium', 'confidence' => 60],
    ];

    $this->mockCodeRabbit->shouldReceive('parseFindings')->once()->andReturn($findings);
    $this->mockCodeRabbit->shouldReceive('filterBySeverity')->once()->andReturn($findings);

    $this->mockQdrant->shouldReceive('upsert')
        ->once()
        ->andReturn(false);

    $this->artisan('coderabbit:extract', ['--pr' => '42'])
        ->assertSuccessful()
        ->expectsOutputToContain('1 finding(s) failed to store');
});
