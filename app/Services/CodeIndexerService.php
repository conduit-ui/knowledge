<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmbeddingServiceInterface;
use App\Integrations\Qdrant\QdrantConnector;
use App\Integrations\Qdrant\Requests\CreateCollection;
use App\Integrations\Qdrant\Requests\DeletePoints;
use App\Integrations\Qdrant\Requests\GetCollectionInfo;
use App\Integrations\Qdrant\Requests\ScrollPoints;
use App\Integrations\Qdrant\Requests\SearchPoints;
use App\Integrations\Qdrant\Requests\UpsertPoints;
use Symfony\Component\Finder\Finder;

class CodeIndexerService
{
    private QdrantConnector $connector;

    private const COLLECTION_NAME = 'code';

    private const CHUNK_SIZE = 2000;

    private const SKIP_DIRS = [
        'vendor',
        'node_modules',
        '.git',
        '.idea',
        '.vscode',
        'storage',
        'cache',
        'build',
        'dist',
        '.next',
        '__pycache__',
        '.pytest_cache',
    ];

    private const FILE_EXTENSIONS = ['php', 'py', 'js', 'ts', 'tsx', 'jsx', 'vue'];

    public function __construct(
        private readonly EmbeddingServiceInterface $embeddingService,
        private readonly int $vectorSize = 1024,
    ) {
        $this->connector = new QdrantConnector(
            host: config('search.qdrant.host', 'localhost'),
            port: (int) config('search.qdrant.port', 6333),
            apiKey: config('search.qdrant.api_key'),
            secure: (bool) config('search.qdrant.secure', false),
        );
    }

    /**
     * Ensure the code collection exists.
     */
    public function ensureCollection(): bool
    {
        $response = $this->connector->send(new GetCollectionInfo(self::COLLECTION_NAME));

        if ($response->successful()) {
            return true;
        }

        if ($response->status() === 404) {
            $createResponse = $this->connector->send(
                new CreateCollection(self::COLLECTION_NAME, $this->vectorSize, 'Cosine')
            );

            return $createResponse->successful();
        }

        return false;
    }

    /**
     * Find all indexable code files in the given paths.
     *
     * @param  array<string>  $paths
     * @return \Generator<array{path: string, repo: string}>
     */
    public function findFiles(array $paths): \Generator
    {
        foreach ($paths as $basePath) {
            if (! is_dir($basePath)) {
                continue;
            }

            $finder = new Finder;
            $finder->files()
                ->in($basePath)
                ->name(array_map(fn ($ext): string => '*.'.$ext, self::FILE_EXTENSIONS))
                ->exclude(self::SKIP_DIRS)
                ->ignoreDotFiles(true)
                ->ignoreVCS(true);

            // Detect repo name from path
            $repo = basename($basePath);

            foreach ($finder as $file) {
                yield [
                    'path' => $file->getRealPath(),
                    'repo' => $repo,
                ];
            }
        }
    }

    /**
     * Index a single file.
     *
     * @return array{chunks: int, success: bool, error?: string}
     */
    public function indexFile(string $filepath, string $repo): array
    {
        $content = @file_get_contents($filepath);

        if ($content === false) {
            return ['chunks' => 0, 'success' => false, 'error' => 'Could not read file'];
        }

        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $language = $this->detectLanguage($extension);
        $functions = $this->extractFunctionNames($content, $language);

        // Chunk content if too large
        $chunks = $this->chunkContent($content, self::CHUNK_SIZE);
        $points = [];

        foreach ($chunks as $index => $chunk) {
            $id = md5($filepath.'_'.$index);
            $text = $this->buildSearchableText($chunk['content'], $filepath, $functions);

            $vector = $this->embeddingService->generate($text);

            if ($vector === []) {
                continue;
            }

            $points[] = [
                'id' => $id,
                'vector' => $vector,
                'payload' => [
                    'filepath' => $filepath,
                    'repo' => $repo,
                    'language' => $language,
                    'functions' => $functions,
                    'chunk_index' => $index,
                    'total_chunks' => count($chunks),
                    'start_line' => $chunk['start_line'],
                    'end_line' => $chunk['end_line'],
                    'content' => $chunk['content'],
                    'indexed_at' => now()->toIso8601String(),
                ],
            ];
        }

        if ($points === []) {
            return ['chunks' => 0, 'success' => false, 'error' => 'Failed to generate embeddings'];
        }

        // Batch upsert
        $response = $this->connector->send(new UpsertPoints(self::COLLECTION_NAME, $points));

        if (! $response->successful()) {
            return ['chunks' => count($points), 'success' => false, 'error' => 'Upsert failed'];
        }

        return ['chunks' => count($points), 'success' => true];
    }

    /**
     * Search code semantically.
     *
     * @param  array{repo?: string, language?: string}  $filters
     * @return array<array{filepath: string, repo: string, language: string, content: string, score: float, functions: array<string>, symbol_name: string|null, symbol_kind: string|null, signature: string|null, start_line: int, end_line: int}>
     */
    public function search(string $query, int $limit = 10, array $filters = []): array
    {
        $vector = $this->embeddingService->generate($query);

        if ($vector === []) {
            return [];
        }

        $qdrantFilter = $this->buildFilter($filters);

        $response = $this->connector->send(
            new SearchPoints(self::COLLECTION_NAME, $vector, $limit, 0.3, $qdrantFilter)
        );

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();
        $results = $data['result'] ?? [];

        return array_map(function (array $result): array {
            $payload = $result['payload'] ?? [];

            return [
                'filepath' => $payload['filepath'] ?? '',
                'repo' => $payload['repo'] ?? '',
                'language' => $payload['language'] ?? '',
                'content' => $payload['content'] ?? '',
                'score' => $result['score'] ?? 0.0,
                'functions' => $payload['functions'] ?? [],
                'symbol_name' => $payload['symbol_name'] ?? null,
                'symbol_kind' => $payload['symbol_kind'] ?? null,
                'signature' => $payload['signature'] ?? null,
                'start_line' => $payload['start_line'] ?? $payload['line'] ?? 1,
                'end_line' => $payload['end_line'] ?? $payload['line'] ?? 1,
            ];
        }, $results);
    }

    /**
     * Index a single tree-sitter symbol into Qdrant.
     *
     * @return array{success: bool, error?: string}
     */
    public function indexSymbol(
        string $text,
        string $filepath,
        string $repo,
        string $language,
        string $symbolName,
        string $symbolKind,
        int $line,
        string $signature,
    ): array {
        $vector = $this->embeddingService->generate($text);

        if ($vector === []) {
            return ['success' => false, 'error' => 'Empty embedding'];
        }

        $id = md5("{$repo}:{$filepath}:{$symbolName}:{$line}");

        $points = [[
            'id' => $id,
            'vector' => $vector,
            'payload' => [
                'filepath' => $filepath,
                'repo' => $repo,
                'language' => $language,
                'symbol_name' => $symbolName,
                'symbol_kind' => $symbolKind,
                'line' => $line,
                'signature' => $signature,
                'content' => mb_substr($text, 0, 4000),
                'indexed_at' => now()->toIso8601String(),
            ],
        ]];

        $response = $this->connector->send(new UpsertPoints(self::COLLECTION_NAME, $points));

        return $response->successful()
            ? ['success' => true]
            : ['success' => false, 'error' => 'Upsert failed'];
    }

    /**
     * Batch-vectorize symbols from a tree-sitter index file.
     *
     * @param  array<string>  $kinds  Symbol kinds to include (empty = all structural kinds)
     * @param  callable(int $success, int $failed, int $total): void  $onProgress
     * @return array{success: int, failed: int, total: int}
     */
    public function vectorizeFromIndex(
        string $indexPath,
        string $repo,
        SymbolIndexService $symbolIndex,
        array $kinds = [],
        ?string $language = null,
        ?callable $onProgress = null,
    ): array {
        $content = file_get_contents($indexPath);
        if ($content === false) {
            return ['success' => 0, 'failed' => 0, 'total' => 0];
        }

        /** @var array{symbols: array<array<string, mixed>>}|null $index */
        $index = json_decode($content, true);
        if (! is_array($index) || ! isset($index['symbols'])) {
            return ['success' => 0, 'failed' => 0, 'total' => 0];
        }

        $allowedKinds = $kinds !== [] ? $kinds : ['class', 'method', 'function', 'interface', 'trait', 'enum'];

        $symbols = array_values(array_filter(
            $index['symbols'],
            function (array $s) use ($allowedKinds, $language): bool {
                if (! in_array($s['kind'] ?? '', $allowedKinds, true)) {
                    return false;
                }
                if ($language !== null) {
                    $ext = strtolower(pathinfo($s['file'] ?? '', PATHINFO_EXTENSION));
                    $fileLang = $this->detectLanguage($ext);
                    if ($fileLang !== $language) {
                        return false;
                    }
                }

                return true;
            },
        ));

        $total = count($symbols);
        $success = 0;
        $failed = 0;

        foreach ($symbols as $symbol) {
            $text = $this->buildSymbolText($symbol);
            if (trim($text) === '') {
                $failed++;

                continue;
            }

            $source = $symbolIndex->getSymbolSource($symbol['id'] ?? '', $repo);
            if ($source !== null) {
                $text .= "\n".$source;
            }

            $ext = strtolower(pathinfo($symbol['file'] ?? '', PATHINFO_EXTENSION));
            $symbolLanguage = $this->detectLanguage($ext);

            $result = $this->indexSymbol(
                text: $text,
                filepath: $symbol['file'] ?? '',
                repo: $repo,
                language: $symbolLanguage,
                symbolName: $symbol['name'] ?? '',
                symbolKind: $symbol['kind'] ?? '',
                line: (int) ($symbol['line'] ?? 0),
                signature: $symbol['signature'] ?? '',
            );

            $result['success'] ? $success++ : $failed++;

            if ($onProgress !== null) {
                $onProgress($success, $failed, $total);
            }
        }

        return ['success' => $success, 'failed' => $failed, 'total' => $total];
    }

    /**
     * Remove vectorized symbols that no longer exist in the current index.
     *
     * @return array{deleted: int, total_checked: int}
     */
    public function pruneStaleSymbols(string $indexPath, string $repo): array
    {
        $content = @file_get_contents($indexPath);
        if ($content === false) {
            return ['deleted' => 0, 'total_checked' => 0];
        }

        /** @var array{symbols: array<array<string, mixed>>}|null $index */
        $index = json_decode($content, true);
        if (! is_array($index) || ! isset($index['symbols'])) {
            return ['deleted' => 0, 'total_checked' => 0];
        }

        // Build set of valid point IDs from the current index
        $validIds = [];
        foreach ($index['symbols'] as $symbol) {
            $id = md5("{$repo}:{$symbol['file']}:{$symbol['name']}:{$symbol['line']}");
            $validIds[$id] = true;
        }

        // Scroll through all points for this repo in Qdrant
        $staleIds = [];
        $totalChecked = 0;
        $offset = null;

        do {
            $filter = ['must' => [['key' => 'repo', 'match' => ['value' => $repo]]]];
            $response = $this->connector->send(
                new ScrollPoints(self::COLLECTION_NAME, 100, $filter, $offset)
            );

            if (! $response->successful()) {
                break;
            }

            $data = $response->json();
            $points = $data['result']['points'] ?? [];
            $offset = $data['result']['next_page_offset'] ?? null;

            foreach ($points as $point) {
                // Only check symbol points (they have symbol_name in payload)
                if (! isset($point['payload']['symbol_name'])) {
                    continue;
                }

                $totalChecked++;
                $pointId = $point['id'];

                if (! isset($validIds[$pointId])) {
                    $staleIds[] = $pointId;
                }
            }
        } while ($offset !== null && $points !== []);

        // Delete stale points in batches
        $deleted = 0;
        foreach (array_chunk($staleIds, 100) as $batch) {
            $response = $this->connector->send(
                new DeletePoints(self::COLLECTION_NAME, $batch)
            );
            if ($response->successful()) {
                $deleted += count($batch);
            }
        }

        return ['deleted' => $deleted, 'total_checked' => $totalChecked];
    }

    /**
     * Build searchable text from a tree-sitter symbol.
     *
     * @param  array<string, mixed>  $symbol
     */
    private function buildSymbolText(array $symbol): string
    {
        return implode("\n", array_filter([
            ($symbol['kind'] ?? '').' '.($symbol['name'] ?? ''),
            $symbol['signature'] ?? '',
            $symbol['summary'] ?? '',
            $symbol['docstring'] ?? '',
            isset($symbol['file']) ? 'file: '.$symbol['file'] : '',
        ]));
    }

    /**
     * Chunk content into smaller pieces.
     *
     * @return array<array{content: string, start_line: int, end_line: int}>
     */
    private function chunkContent(string $content, int $maxChars): array
    {
        $lines = explode("\n", $content);
        $chunks = [];
        $currentChunk = '';
        $startLine = 1;
        $currentLine = 1;

        foreach ($lines as $line) {
            if (strlen($currentChunk) + strlen($line) + 1 > $maxChars && $currentChunk !== '') {
                $chunks[] = [
                    'content' => trim($currentChunk),
                    'start_line' => $startLine,
                    'end_line' => $currentLine - 1,
                ];
                $currentChunk = $line."\n";
                $startLine = $currentLine;
            } else {
                $currentChunk .= $line."\n";
            }
            $currentLine++;
        }

        // Add remaining content
        if (trim($currentChunk) !== '') {
            $chunks[] = [
                'content' => trim($currentChunk),
                'start_line' => $startLine,
                'end_line' => $currentLine - 1,
            ];
        }

        return $chunks;
    }

    /**
     * Detect programming language from file extension.
     */
    private function detectLanguage(string $extension): string
    {
        return match (strtolower($extension)) {
            'php' => 'php',
            'py' => 'python',
            'js', 'jsx' => 'javascript',
            'ts', 'tsx' => 'typescript',
            'vue' => 'vue',
            default => 'unknown',
        };
    }

    /**
     * Extract function/method names from code.
     *
     * @return array<string>
     */
    private function extractFunctionNames(string $content, string $language): array
    {
        $functions = [];

        $patterns = match ($language) {
            'php' => [
                '/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m',
                '/public\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m',
                '/private\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m',
                '/protected\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m',
            ],
            'python' => [
                '/def\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m',
                '/async\s+def\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/m',
            ],
            'javascript', 'typescript', 'vue' => [
                '/function\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/m',
                '/const\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*=\s*(?:async\s+)?\(/m',
                '/(?:async\s+)?([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\([^)]*\)\s*{/m',
            ],
            default => [],
        };

        foreach ($patterns as $pattern) {
            $matchResult = preg_match_all($pattern, $content, $matches);
            if ($matchResult !== false && $matchResult > 0) {
                $functions = array_merge($functions, $matches[1]);
            }
        }

        return array_unique(array_filter($functions));
    }

    /**
     * Build searchable text from content and metadata.
     *
     * @param  array<string>  $functions
     */
    private function buildSearchableText(string $content, string $filepath, array $functions): string
    {
        $filename = basename($filepath);
        $functionsStr = implode(' ', $functions);

        return trim("{$filename}\n{$functionsStr}\n{$content}");
    }

    /**
     * Build Qdrant filter from search filters.
     *
     * @param  array{repo?: string, language?: string}  $filters
     * @return array<string, mixed>|null
     */
    private function buildFilter(array $filters): ?array
    {
        if ($filters === []) {
            return null;
        }

        $must = [];

        if (isset($filters['repo'])) {
            $must[] = [
                'key' => 'repo',
                'match' => ['value' => $filters['repo']],
            ];
        }

        if (isset($filters['language'])) {
            $must[] = [
                'key' => 'language',
                'match' => ['value' => $filters['language']],
            ];
        }

        return $must === [] ? null : ['must' => $must];
    }
}
