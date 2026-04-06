<?php

declare(strict_types=1);

use App\Mcp\Tools\FileOutlineTool;
use App\Services\SymbolIndexService;
use Laravel\Mcp\Request;

uses()->group('mcp-tools');

beforeEach(function (): void {
    $this->symbolIndex = Mockery::mock(SymbolIndexService::class);
    $this->tool = new FileOutlineTool($this->symbolIndex);
});

describe('file outline tool', function (): void {
    it('returns error when file is missing', function (): void {
        $request = new Request([]);
        $response = $this->tool->handle($request);
        expect($response->isError())->toBeTrue();
    });

    it('returns error when file is empty string', function (): void {
        $request = new Request(['file' => '']);
        $response = $this->tool->handle($request);
        expect($response->isError())->toBeTrue();
    });

    it('returns empty outline for unknown file', function (): void {
        $this->symbolIndex->shouldReceive('getFileOutline')
            ->with('app/Unknown.php', 'local/knowledge')
            ->once()
            ->andReturn([]);

        $request = new Request(['file' => 'app/Unknown.php']);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['symbols'])->toBeEmpty()
            ->and($data['total'])->toBe(0)
            ->and($data['file'])->toBe('app/Unknown.php');
    });

    it('returns symbol hierarchy', function (): void {
        $this->symbolIndex->shouldReceive('getFileOutline')
            ->with('app/Services/UserService.php', 'local/pstrax')
            ->once()
            ->andReturn([
                [
                    'id' => 'sym-1',
                    'kind' => 'class',
                    'name' => 'UserService',
                    'signature' => 'class UserService',
                    'summary' => '',
                    'line' => 10,
                    'children' => [
                        ['id' => 'sym-2', 'kind' => 'method', 'name' => 'find', 'signature' => 'public function find(int $id)', 'summary' => '', 'line' => 15],
                    ],
                ],
            ]);

        $request = new Request(['file' => 'app/Services/UserService.php', 'repo' => 'local/pstrax']);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['total'])->toBe(1)
            ->and($data['symbols'][0]['name'])->toBe('UserService')
            ->and($data['symbols'][0]['children'][0]['name'])->toBe('find')
            ->and($data['repo'])->toBe('local/pstrax');
    });

    it('defaults repo to local/knowledge', function (): void {
        $this->symbolIndex->shouldReceive('getFileOutline')
            ->withArgs(fn ($f, $r) => $r === 'local/knowledge')
            ->once()
            ->andReturn([]);

        $request = new Request(['file' => 'test.php']);
        $this->tool->handle($request);
    });
});

describe('schema', function (): void {
    it('returns valid schema definition', function (): void {
        $schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;
        $result = $this->tool->schema($schema);
        expect($result)->toBeArray()->not->toBeEmpty();
    });
});
