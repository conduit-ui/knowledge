<?php

declare(strict_types=1);

use App\Mcp\Tools\SymbolLookupTool;
use App\Services\SymbolIndexService;
use Laravel\Mcp\Request;

uses()->group('mcp-tools');

beforeEach(function (): void {
    $this->symbolIndex = Mockery::mock(SymbolIndexService::class);
    $this->tool = new SymbolLookupTool($this->symbolIndex);
});

describe('symbol lookup tool', function (): void {
    it('returns error when symbol_id is missing', function (): void {
        $request = new Request([]);
        $response = $this->tool->handle($request);
        expect($response->isError())->toBeTrue();
    });

    it('returns error when symbol_id is empty', function (): void {
        $request = new Request(['symbol_id' => '']);
        $response = $this->tool->handle($request);
        expect($response->isError())->toBeTrue();
    });

    it('returns error when symbol not found', function (): void {
        $this->symbolIndex->shouldReceive('getSymbol')
            ->with('nonexistent', 'local/knowledge')
            ->once()
            ->andReturnNull();

        $request = new Request(['symbol_id' => 'nonexistent']);
        $response = $this->tool->handle($request);
        expect($response->isError())->toBeTrue();
    });

    it('returns symbol with source', function (): void {
        $this->symbolIndex->shouldReceive('getSymbol')
            ->with('sym-1', 'local/pstrax')
            ->once()
            ->andReturn([
                'id' => 'sym-1',
                'kind' => 'class',
                'name' => 'UserService',
                'file' => 'app/Services/UserService.php',
                'line' => 10,
                'signature' => 'class UserService',
                'summary' => 'Handles users',
                'docstring' => '/** User service */',
            ]);

        $this->symbolIndex->shouldReceive('getSymbolSource')
            ->with('sym-1', 'local/pstrax')
            ->once()
            ->andReturn('class UserService { }');

        $request = new Request(['symbol_id' => 'sym-1', 'repo' => 'local/pstrax']);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['name'])->toBe('UserService')
            ->and($data['source'])->toBe('class UserService { }')
            ->and($data['kind'])->toBe('class');
    });

    it('excludes source when include_source is false', function (): void {
        $this->symbolIndex->shouldReceive('getSymbol')
            ->with('sym-1', 'local/knowledge')
            ->once()
            ->andReturn([
                'id' => 'sym-1',
                'kind' => 'method',
                'name' => 'find',
                'file' => 'app/Foo.php',
                'line' => 5,
                'signature' => 'public function find()',
                'summary' => '',
                'docstring' => '',
            ]);

        $this->symbolIndex->shouldNotReceive('getSymbolSource');

        $request = new Request(['symbol_id' => 'sym-1', 'include_source' => false]);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['name'])->toBe('find')
            ->and(array_key_exists('source', $data))->toBeFalse();
    });
});
