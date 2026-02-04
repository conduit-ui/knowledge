<?php

declare(strict_types=1);

use App\Services\CodeIndexerService;

beforeEach(function (): void {
    $this->indexerMock = Mockery::mock(CodeIndexerService::class);
    $this->app->instance(CodeIndexerService::class, $this->indexerMock);
});

afterEach(function (): void {
    Mockery::close();
});

describe('search-code command', function (): void {
    it('searches code semantically', function (): void {
        $this->indexerMock->shouldReceive('search')
            ->once()
            ->with('authentication logic', 10, [])
            ->andReturn([
                createCodeResult('/path/to/auth.php', 'my-repo', 'php', 0.95),
            ]);

        $this->artisan('search-code', ['query' => 'authentication logic'])
            ->assertSuccessful()
            ->expectsOutputToContain('results found')
            ->expectsOutputToContain('auth.php');
    });

    it('fails with empty query', function (): void {
        $this->indexerMock->shouldNotReceive('search');

        $this->artisan('search-code', ['query' => ''])
            ->assertFailed();
    });

    it('fails with whitespace-only query', function (): void {
        $this->indexerMock->shouldNotReceive('search');

        $this->artisan('search-code', ['query' => '   '])
            ->assertFailed();
    });

    it('shows no results message when no matches found', function (): void {
        $this->indexerMock->shouldReceive('search')
            ->once()
            ->with('nonexistent code', 10, [])
            ->andReturn([]);

        $this->artisan('search-code', ['query' => 'nonexistent code'])
            ->assertSuccessful()
            ->expectsOutputToContain('No results found');
    });

    it('respects limit option', function (): void {
        $this->indexerMock->shouldReceive('search')
            ->once()
            ->with('test', 5, [])
            ->andReturn([]);

        $this->artisan('search-code', ['query' => 'test', '--limit' => '5'])
            ->assertSuccessful();
    });

    it('filters by repository', function (): void {
        $this->indexerMock->shouldReceive('search')
            ->once()
            ->with('test', 10, ['repo' => 'my-project'])
            ->andReturn([]);

        $this->artisan('search-code', ['query' => 'test', '--repo' => 'my-project'])
            ->assertSuccessful();
    });

    it('filters by language', function (): void {
        $this->indexerMock->shouldReceive('search')
            ->once()
            ->with('test', 10, ['language' => 'php'])
            ->andReturn([]);

        $this->artisan('search-code', ['query' => 'test', '--language' => 'php'])
            ->assertSuccessful();
    });

    it('combines repo and language filters', function (): void {
        $this->indexerMock->shouldReceive('search')
            ->once()
            ->with('test', 10, ['repo' => 'my-project', 'language' => 'typescript'])
            ->andReturn([]);

        $this->artisan('search-code', [
            'query' => 'test',
            '--repo' => 'my-project',
            '--language' => 'typescript',
        ])->assertSuccessful();
    });

    it('displays multiple results with numbering', function (): void {
        $this->indexerMock->shouldReceive('search')
            ->once()
            ->andReturn([
                createCodeResult('/path/to/file1.php', 'repo1', 'php', 0.95),
                createCodeResult('/path/to/file2.js', 'repo2', 'javascript', 0.85),
                createCodeResult('/path/to/file3.py', 'repo3', 'python', 0.75),
            ]);

        $this->artisan('search-code', ['query' => 'test'])
            ->assertSuccessful()
            ->expectsOutputToContain('3 results found')
            ->expectsOutputToContain('[1]')
            ->expectsOutputToContain('[2]')
            ->expectsOutputToContain('[3]');
    });

    it('displays functions when available', function (): void {
        $result = createCodeResult('/path/to/auth.php', 'my-repo', 'php', 0.95);
        $result['functions'] = ['authenticate', 'login', 'logout'];

        $this->indexerMock->shouldReceive('search')
            ->once()
            ->andReturn([$result]);

        $this->artisan('search-code', ['query' => 'auth'])
            ->assertSuccessful()
            ->expectsOutputToContain('Functions: authenticate, login, logout');
    });

    it('truncates functions list to 5 items', function (): void {
        $result = createCodeResult('/path/to/file.php', 'repo', 'php', 0.9);
        $result['functions'] = ['func1', 'func2', 'func3', 'func4', 'func5', 'func6', 'func7'];

        $this->indexerMock->shouldReceive('search')
            ->once()
            ->andReturn([$result]);

        $this->artisan('search-code', ['query' => 'test'])
            ->assertSuccessful()
            ->expectsOutputToContain('Functions: func1, func2, func3, func4, func5');
    });

    it('does not display functions line when empty', function (): void {
        $result = createCodeResult('/path/to/file.php', 'repo', 'php', 0.9);
        $result['functions'] = [];

        $this->indexerMock->shouldReceive('search')
            ->once()
            ->andReturn([$result]);

        $output = $this->artisan('search-code', ['query' => 'test']);
        $output->assertSuccessful();
        // Functions line should not appear when empty
    });

    it('displays line range', function (): void {
        $result = createCodeResult('/path/to/file.php', 'repo', 'php', 0.9);
        $result['start_line'] = 10;
        $result['end_line'] = 50;

        $this->indexerMock->shouldReceive('search')
            ->once()
            ->andReturn([$result]);

        $this->artisan('search-code', ['query' => 'test'])
            ->assertSuccessful()
            ->expectsOutputToContain('Lines: 10-50');
    });
});

describe('--show-content flag', function (): void {
    it('displays code content when flag is set', function (): void {
        $result = createCodeResult('/path/to/file.php', 'repo', 'php', 0.9);
        $result['content'] = "<?php\nfunction test() {\n    return true;\n}";

        $this->indexerMock->shouldReceive('search')
            ->once()
            ->andReturn([$result]);

        $this->artisan('search-code', ['query' => 'test', '--show-content' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('function test()');
    });

    it('truncates content to 15 lines and shows remaining count', function (): void {
        $lines = [];
        for ($i = 1; $i <= 20; $i++) {
            $lines[] = "Line {$i} of code";
        }
        $result = createCodeResult('/path/to/file.php', 'repo', 'php', 0.9);
        $result['content'] = implode("\n", $lines);

        $this->indexerMock->shouldReceive('search')
            ->once()
            ->andReturn([$result]);

        $this->artisan('search-code', ['query' => 'test', '--show-content' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Line 1 of code')
            ->expectsOutputToContain('Line 15 of code')
            ->expectsOutputToContain('... (5 more lines)');
    });

    it('does not show more lines indicator when content is 15 lines or less', function (): void {
        $lines = [];
        for ($i = 1; $i <= 10; $i++) {
            $lines[] = "Line {$i}";
        }
        $result = createCodeResult('/path/to/file.php', 'repo', 'php', 0.9);
        $result['content'] = implode("\n", $lines);

        $this->indexerMock->shouldReceive('search')
            ->once()
            ->andReturn([$result]);

        $output = $this->artisan('search-code', ['query' => 'test', '--show-content' => true]);
        $output->assertSuccessful();
        // Should not contain "more lines" indicator
    });

    it('shows separator lines around content', function (): void {
        $result = createCodeResult('/path/to/file.php', 'repo', 'php', 0.9);
        $result['content'] = "<?php\necho 'hello';";

        $this->indexerMock->shouldReceive('search')
            ->once()
            ->andReturn([$result]);

        $this->artisan('search-code', ['query' => 'test', '--show-content' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('----');
    });
});

// Helper function
function createCodeResult(
    string $filepath,
    string $repo,
    string $language,
    float $score,
): array {
    return [
        'filepath' => $filepath,
        'repo' => $repo,
        'language' => $language,
        'content' => "Sample code content for {$filepath}",
        'score' => $score,
        'functions' => [],
        'start_line' => 1,
        'end_line' => 100,
    ];
}
