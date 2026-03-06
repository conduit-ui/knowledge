<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\CodeIndexerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Semantic code search across indexed repositories. Search by natural language to find classes, methods, functions, and their source code.')]
#[IsReadOnly]
#[IsIdempotent]
class SearchCodeTool extends Tool
{
    public function __construct(
        private readonly CodeIndexerService $codeIndexer,
    ) {}

    public function handle(Request $request): Response
    {
        /** @var string $query */
        $query = $request->get('query');

        if (! is_string($query) || strlen($query) < 2) {
            return Response::error('A search query of at least 2 characters is required.');
        }

        $limit = is_int($request->get('limit')) ? min($request->get('limit'), 20) : 10;

        $filters = array_filter([
            'repo' => is_string($request->get('repo')) ? $request->get('repo') : null,
            'language' => is_string($request->get('language')) ? $request->get('language') : null,
        ]);

        $results = $this->codeIndexer->search($query, $limit, $filters);

        if ($results === []) {
            return Response::text(json_encode([
                'results' => [],
                'meta' => ['query' => $query, 'total' => 0],
            ], JSON_THROW_ON_ERROR));
        }

        $formatted = array_map(fn (array $r): array => [
            'filepath' => $r['filepath'],
            'repo' => $r['repo'],
            'language' => $r['language'],
            'symbol_name' => $r['symbol_name'] ?? null,
            'symbol_kind' => $r['symbol_kind'] ?? null,
            'line' => $r['start_line'],
            'score' => round($r['score'], 3),
            'content' => $r['content'],
        ], $results);

        return Response::text(json_encode([
            'results' => $formatted,
            'meta' => [
                'query' => $query,
                'total' => count($formatted),
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Natural language query (e.g., "rate limiting middleware", "database migration logic")')
                ->required(),
            'repo' => $schema->string()
                ->description('Filter to a specific repo (e.g., "local/pstrax-laravel").'),
            'language' => $schema->string()
                ->description('Filter by language (php, typescript, javascript, python).'),
            'limit' => $schema->integer()
                ->description('Max results (default 10, max 20).')
                ->default(10),
        ];
    }
}
