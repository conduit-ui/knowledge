<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\SymbolIndexService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Look up a symbol by ID — get metadata and optionally its source code.')]
#[IsReadOnly]
#[IsIdempotent]
class SymbolLookupTool extends Tool
{
    public function __construct(
        private readonly SymbolIndexService $symbolIndex,
    ) {}

    public function handle(Request $request): Response
    {
        $symbolId = $request->get('symbol_id');

        if (! is_string($symbolId) || $symbolId === '') {
            return Response::error('A symbol_id is required.');
        }

        $repo = is_string($request->get('repo')) ? $request->get('repo') : 'local/knowledge';
        $includeSource = $request->get('include_source') !== false;

        $symbol = $this->symbolIndex->getSymbol($symbolId, $repo);

        if ($symbol === null) {
            return Response::error("Symbol '{$symbolId}' not found in {$repo}.");
        }

        $result = [
            'id' => $symbol['id'] ?? $symbolId,
            'kind' => $symbol['kind'] ?? '',
            'name' => $symbol['name'] ?? '',
            'file' => $symbol['file'] ?? '',
            'line' => $symbol['line'] ?? 0,
            'signature' => $symbol['signature'] ?? '',
            'summary' => $symbol['summary'] ?? '',
            'docstring' => $symbol['docstring'] ?? '',
        ];

        if ($includeSource) {
            $result['source'] = $this->symbolIndex->getSymbolSource($symbolId, $repo);
        }

        return Response::text(json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'symbol_id' => $schema->string()
                ->description('The symbol ID from a search or outline result')
                ->required(),
            'repo' => $schema->string()
                ->description('Repository identifier (e.g., "local/pstrax-laravel"). Defaults to "local/knowledge".'),
            'include_source' => $schema->boolean()
                ->description('Whether to include the full source code (default: true)'),
        ];
    }
}
