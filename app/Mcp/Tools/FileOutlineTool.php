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

#[Description('Get the symbol outline of a file — classes, methods, functions with hierarchy.')]
#[IsReadOnly]
#[IsIdempotent]
class FileOutlineTool extends Tool
{
    public function __construct(
        private readonly SymbolIndexService $symbolIndex,
    ) {}

    public function handle(Request $request): Response
    {
        $file = $request->get('file');

        if (! is_string($file) || $file === '') {
            return Response::error('A file path is required.');
        }

        $repo = is_string($request->get('repo')) ? $request->get('repo') : 'local/knowledge';

        $outline = $this->symbolIndex->getFileOutline($file, $repo);

        return Response::text(json_encode([
            'file' => $file,
            'repo' => $repo,
            'symbols' => $outline,
            'total' => count($outline),
        ], JSON_THROW_ON_ERROR));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()
                ->description('Relative file path within the repo (e.g., "app/Services/UserService.php")')
                ->required(),
            'repo' => $schema->string()
                ->description('Repository identifier (e.g., "local/pstrax-laravel"). Defaults to "local/knowledge".'),
        ];
    }
}
