<?php

declare(strict_types=1);

use App\Services\SymbolIndexService;

beforeEach(function (): void {
    $this->indexerMock = Mockery::mock(SymbolIndexService::class);
    $this->app->instance(SymbolIndexService::class, $this->indexerMock);
});

afterEach(function (): void {
    Mockery::close();
});

function createSymbolResult(
    string $name = 'authenticate',
    string $kind = 'method',
    string $file = 'app/Services/AuthService.php',
    int $line = 15,
    int $score = 35,
): array {
    return [
        'id' => "{$file}::{$name}#{$kind}",
        'kind' => $kind,
        'name' => $name,
        'file' => $file,
        'line' => $line,
        'signature' => "public function {$name}(): bool",
        'summary' => "Method {$name}",
        'score' => $score,
    ];
}

describe('search-code command', function (): void {
    it('searches code symbols', function (): void {
        $this->indexerMock->shouldReceive('searchSymbols')
            ->once()
            ->with('authenticate', 'local/knowledge', null, null, 10)
            ->andReturn([createSymbolResult()]);

        $this->artisan('search-code', ['query' => 'authenticate'])
            ->assertSuccessful()
            ->expectsOutputToContain('results found')
            ->expectsOutputToContain('authenticate');
    });

    it('fails with empty query', function (): void {
        $this->indexerMock->shouldNotReceive('searchSymbols');

        $this->artisan('search-code', ['query' => ''])
            ->assertFailed();
    });

    it('fails with whitespace-only query', function (): void {
        $this->indexerMock->shouldNotReceive('searchSymbols');

        $this->artisan('search-code', ['query' => '   '])
            ->assertFailed();
    });

    it('shows no results message when no matches found', function (): void {
        $this->indexerMock->shouldReceive('searchSymbols')
            ->once()
            ->andReturn([]);

        $this->artisan('search-code', ['query' => 'nonexistent'])
            ->assertSuccessful()
            ->expectsOutputToContain('No results found');
    });

    it('respects limit option', function (): void {
        $this->indexerMock->shouldReceive('searchSymbols')
            ->once()
            ->with('test', 'local/knowledge', null, null, 5)
            ->andReturn([]);

        $this->artisan('search-code', ['query' => 'test', '--limit' => '5'])
            ->assertSuccessful();
    });

    it('filters by symbol kind', function (): void {
        $this->indexerMock->shouldReceive('searchSymbols')
            ->once()
            ->with('user', 'local/knowledge', 'class', null, 10)
            ->andReturn([createSymbolResult('User', 'class')]);

        $this->artisan('search-code', ['query' => 'user', '--kind' => 'class'])
            ->assertSuccessful();
    });

    it('filters by file pattern', function (): void {
        $this->indexerMock->shouldReceive('searchSymbols')
            ->once()
            ->with('test', 'local/knowledge', null, '*/Services/*', 10)
            ->andReturn([]);

        $this->artisan('search-code', ['query' => 'test', '--file' => '*/Services/*'])
            ->assertSuccessful();
    });

    it('uses custom repo', function (): void {
        $this->indexerMock->shouldReceive('searchSymbols')
            ->once()
            ->with('test', 'local/myproject', null, null, 10)
            ->andReturn([]);

        $this->artisan('search-code', ['query' => 'test', '--repo' => 'local/myproject'])
            ->assertSuccessful();
    });

    it('displays multiple results with numbering', function (): void {
        $this->indexerMock->shouldReceive('searchSymbols')
            ->once()
            ->andReturn([
                createSymbolResult('login', 'method', 'app/Auth.php', 10, 35),
                createSymbolResult('User', 'class', 'app/User.php', 5, 20),
                createSymbolResult('register', 'method', 'app/Auth.php', 50, 15),
            ]);

        $this->artisan('search-code', ['query' => 'auth'])
            ->assertSuccessful()
            ->expectsOutputToContain('3 results found')
            ->expectsOutputToContain('[1]')
            ->expectsOutputToContain('[2]')
            ->expectsOutputToContain('[3]');
    });

    it('displays signature', function (): void {
        $this->indexerMock->shouldReceive('searchSymbols')
            ->once()
            ->andReturn([createSymbolResult()]);

        $this->artisan('search-code', ['query' => 'authenticate'])
            ->assertSuccessful()
            ->expectsOutputToContain('public function authenticate');
    });

    it('displays file and line number', function (): void {
        $this->indexerMock->shouldReceive('searchSymbols')
            ->once()
            ->andReturn([createSymbolResult('test', 'function', 'src/utils.php', 42)]);

        $this->artisan('search-code', ['query' => 'test'])
            ->assertSuccessful()
            ->expectsOutputToContain('src/utils.php:42');
    });
});

describe('--show-source flag', function (): void {
    it('displays source code when flag is set', function (): void {
        $this->indexerMock->shouldReceive('searchSymbols')
            ->once()
            ->andReturn([createSymbolResult()]);

        $this->indexerMock->shouldReceive('getSymbolSource')
            ->once()
            ->andReturn("public function authenticate(): bool {\n    return true;\n}");

        $this->artisan('search-code', ['query' => 'authenticate', '--show-source' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('return true');
    });

    it('truncates source to 20 lines', function (): void {
        $lines = [];
        for ($i = 1; $i <= 25; $i++) {
            $lines[] = "    line {$i}";
        }

        $this->indexerMock->shouldReceive('searchSymbols')
            ->once()
            ->andReturn([createSymbolResult()]);

        $this->indexerMock->shouldReceive('getSymbolSource')
            ->once()
            ->andReturn(implode("\n", $lines));

        $this->artisan('search-code', ['query' => 'test', '--show-source' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('... (5 more lines)');
    });
});

describe('--outline flag', function (): void {
    it('shows file outline', function (): void {
        $this->indexerMock->shouldReceive('getFileOutline')
            ->once()
            ->with('app/Services/QdrantService.php', 'local/knowledge')
            ->andReturn([
                [
                    'id' => 'app/Services/QdrantService.php::QdrantService#class',
                    'kind' => 'class',
                    'name' => 'QdrantService',
                    'signature' => 'class QdrantService',
                    'summary' => 'Class QdrantService',
                    'line' => 28,
                    'children' => [
                        [
                            'id' => 'app/Services/QdrantService.php::QdrantService.search#method',
                            'kind' => 'method',
                            'name' => 'search',
                            'signature' => 'public function search()',
                            'summary' => 'Search entries.',
                            'line' => 100,
                        ],
                    ],
                ],
            ]);

        $this->artisan('search-code', [
            'query' => 'ignored',
            '--outline' => 'app/Services/QdrantService.php',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('QdrantService')
            ->expectsOutputToContain('search');
    });

    it('shows message when no symbols in file', function (): void {
        $this->indexerMock->shouldReceive('getFileOutline')
            ->once()
            ->andReturn([]);

        $this->artisan('search-code', [
            'query' => 'ignored',
            '--outline' => 'empty.php',
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('No symbols found');
    });
});
