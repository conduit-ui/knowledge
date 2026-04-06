<?php

declare(strict_types=1);

use App\Mcp\Tools\SearchCodeTool;
use App\Services\CodeIndexerService;
use App\Services\SymbolIndexService;
use Laravel\Mcp\Request;

uses()->group('mcp-tools');

beforeEach(function (): void {
    $this->codeIndexer = Mockery::mock(CodeIndexerService::class);
    $this->symbolIndex = Mockery::mock(SymbolIndexService::class);
    $this->tool = new SearchCodeTool($this->codeIndexer, $this->symbolIndex);
});

describe('search code tool', function (): void {
    it('returns error when query is missing', function (): void {
        $request = new Request([]);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });

    it('returns error when query is too short', function (): void {
        $request = new Request(['query' => 'a']);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });

    it('returns empty results when nothing found', function (): void {
        $this->codeIndexer->shouldReceive('search')
            ->once()
            ->andReturn([]);

        $request = new Request(['query' => 'authentication middleware']);
        $response = $this->tool->handle($request);

        expect($response->isError())->toBeFalse();

        $data = json_decode((string) $response->content(), true);
        expect($data['results'])->toBeEmpty()
            ->and($data['meta']['total'])->toBe(0)
            ->and($data['meta']['query'])->toBe('authentication middleware');
    });

    it('returns formatted results with source code', function (): void {
        $this->codeIndexer->shouldReceive('search')
            ->once()
            ->andReturn([
                [
                    'filepath' => '/app/Http/Middleware/Auth.php',
                    'repo' => 'local/pstrax-laravel',
                    'language' => 'php',
                    'content' => 'class Auth extends Middleware {}',
                    'score' => 0.92,
                    'functions' => ['handle'],
                    'symbol_name' => 'Auth',
                    'symbol_kind' => 'class',
                    'signature' => 'class Auth extends Middleware',
                    'start_line' => 5,
                    'end_line' => 30,
                ],
            ]);

        $this->symbolIndex->shouldReceive('getSymbolSourceByNameAndFile')
            ->with('Auth', '/app/Http/Middleware/Auth.php', 'local/pstrax-laravel')
            ->once()
            ->andReturn('class Auth extends Middleware { public function handle() {} }');

        $request = new Request(['query' => 'authentication middleware']);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['results'])->toHaveCount(1)
            ->and($data['results'][0]['filepath'])->toBe('/app/Http/Middleware/Auth.php')
            ->and($data['results'][0]['symbol_name'])->toBe('Auth')
            ->and($data['results'][0]['symbol_kind'])->toBe('class')
            ->and($data['results'][0]['score'])->toBe(0.92)
            ->and($data['results'][0]['line'])->toBe(5)
            ->and($data['results'][0]['source'])->toBe('class Auth extends Middleware { public function handle() {} }')
            ->and($data['meta']['total'])->toBe(1);
    });

    it('returns null source when symbol not found in index', function (): void {
        $this->codeIndexer->shouldReceive('search')
            ->once()
            ->andReturn([
                [
                    'filepath' => '/app/Foo.php',
                    'repo' => 'local/test',
                    'language' => 'php',
                    'content' => 'class Foo {}',
                    'score' => 0.8,
                    'functions' => [],
                    'symbol_name' => 'Foo',
                    'symbol_kind' => 'class',
                    'signature' => 'class Foo',
                    'start_line' => 1,
                    'end_line' => 5,
                ],
            ]);

        $this->symbolIndex->shouldReceive('getSymbolSourceByNameAndFile')
            ->once()
            ->andReturnNull();

        $request = new Request(['query' => 'test query']);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['results'][0]['source'])->toBeNull();
    });

    it('passes repo filter to search', function (): void {
        $this->codeIndexer->shouldReceive('search')
            ->withArgs(function (string $query, int $limit, array $filters): bool {
                return $query === 'test' && $filters === ['repo' => 'local/pstrax-laravel'];
            })
            ->once()
            ->andReturn([]);

        $request = new Request(['query' => 'test', 'repo' => 'local/pstrax-laravel']);
        $this->tool->handle($request);
    });

    it('passes language filter to search', function (): void {
        $this->codeIndexer->shouldReceive('search')
            ->withArgs(function (string $query, int $limit, array $filters): bool {
                return $filters === ['language' => 'php'];
            })
            ->once()
            ->andReturn([]);

        $request = new Request(['query' => 'test', 'language' => 'php']);
        $this->tool->handle($request);
    });

    it('respects limit parameter', function (): void {
        $this->codeIndexer->shouldReceive('search')
            ->withArgs(function (string $query, int $limit): bool {
                return $limit === 5;
            })
            ->once()
            ->andReturn([]);

        $request = new Request(['query' => 'test', 'limit' => 5]);
        $this->tool->handle($request);
    });

    it('caps limit at 20', function (): void {
        $this->codeIndexer->shouldReceive('search')
            ->withArgs(function (string $query, int $limit): bool {
                return $limit === 20;
            })
            ->once()
            ->andReturn([]);

        $request = new Request(['query' => 'test', 'limit' => 50]);
        $this->tool->handle($request);
    });

    it('defaults limit to 10', function (): void {
        $this->codeIndexer->shouldReceive('search')
            ->withArgs(function (string $query, int $limit): bool {
                return $limit === 10;
            })
            ->once()
            ->andReturn([]);

        $request = new Request(['query' => 'test']);
        $this->tool->handle($request);
    });

    it('returns non-integer query as error', function (): void {
        $request = new Request(['query' => 123]);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });
});

describe('schema', function (): void {
    it('returns valid schema definition', function (): void {
        $schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;
        $result = $this->tool->schema($schema);
        expect($result)->toBeArray()->not->toBeEmpty();
    });
});
