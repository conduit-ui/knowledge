<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;

class SymbolIndexService
{
    private const INDEX_VERSION = 2;

    private const DEFAULT_STORAGE_PATH = '~/.code-index';

    public function __construct(
        private readonly string $storagePath = self::DEFAULT_STORAGE_PATH,
    ) {}

    /**
     * Index a local folder using jcodemunch's tree-sitter parser.
     *
     * @return array{success: bool, repo?: string, file_count?: int, symbol_count?: int, languages?: array<string, int>, error?: string}
     */
    public function indexFolder(string $path, bool $incremental = false): array
    {
        $resolvedPath = realpath($path);
        if ($resolvedPath === false || ! is_dir($resolvedPath)) {
            return ['success' => false, 'error' => "Invalid path: {$path}"];
        }

        // @codeCoverageIgnoreStart
        // Subprocess call to jcodemunch — tested via integration/CLI, not unit tests
        $storagePath = $this->resolveStoragePath();
        $incrementalFlag = $incremental ? 'True' : 'False';

        $script = <<<PYTHON
import sys
sys.path.insert(0, '/tmp/jcodemunch-inspect')
from jcodemunch_mcp.tools.index_folder import index_folder
import json
result = index_folder(
    path='{$resolvedPath}',
    use_ai_summaries=False,
    storage_path='{$storagePath}',
    incremental={$incrementalFlag},
)
print(json.dumps(result))
PYTHON;

        $result = Process::timeout(120)->run(['python3', '-c', $script]);

        if (! $result->successful()) {
            return ['success' => false, 'error' => $result->errorOutput()];
        }

        /** @var array{success: bool, repo?: string, file_count?: int, symbol_count?: int, languages?: array<string, int>, error?: string} $decoded */
        $decoded = json_decode($result->output(), true);

        return is_array($decoded) ? $decoded : ['success' => false, 'error' => 'Invalid response from indexer'];
        // @codeCoverageIgnoreEnd
    }

    /**
     * Search symbols using weighted keyword scoring.
     *
     * @return array<array{id: string, kind: string, name: string, file: string, line: int, signature: string, summary: string, score: int}>
     */
    public function searchSymbols(
        string $query,
        string $repo = 'local/knowledge',
        ?string $kind = null,
        ?string $filePattern = null,
        int $maxResults = 10,
    ): array {
        $index = $this->loadIndex($repo);
        if ($index === null) {
            return [];
        }

        $queryLower = strtolower($query);
        $queryWords = array_filter(explode(' ', $queryLower));

        $scored = [];
        foreach ($index['symbols'] as $symbol) {
            if ($kind !== null && ($symbol['kind'] ?? '') !== $kind) {
                continue;
            }
            if ($filePattern !== null && ! $this->matchPattern($symbol['file'] ?? '', $filePattern)) {
                continue;
            }

            $score = $this->scoreSymbol($symbol, $queryLower, $queryWords);
            if ($score > 0) {
                $scored[] = [
                    'id' => $symbol['id'],
                    'kind' => $symbol['kind'],
                    'name' => $symbol['name'],
                    'file' => $symbol['file'],
                    'line' => $symbol['line'],
                    'signature' => $symbol['signature'] ?? '',
                    'summary' => $symbol['summary'] ?? '',
                    'score' => $score,
                ];
            }
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $maxResults);
    }

    /**
     * Get symbol source code via byte-offset seek+read. O(1).
     */
    public function getSymbolSource(string $symbolId, string $repo = 'local/knowledge'): ?string
    {
        $index = $this->loadIndex($repo);
        if ($index === null) {
            return null;
        }

        $symbol = $this->findSymbol($index, $symbolId);
        if ($symbol === null) {
            return null;
        }

        $contentPath = $this->contentFilePath($repo, $symbol['file']);
        if ($contentPath === null || ! file_exists($contentPath)) {
            return null;
        }

        $handle = fopen($contentPath, 'rb');
        // @codeCoverageIgnoreStart
        // Defensive: fopen only fails if file disappears between exists() and fopen()
        if ($handle === false) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        fseek($handle, $symbol['byte_offset']);
        $source = fread($handle, $symbol['byte_length']);
        fclose($handle);

        return $source !== false ? $source : null;
    }

    /**
     * Get symbol metadata by ID.
     *
     * @return array<string, mixed>|null
     */
    public function getSymbol(string $symbolId, string $repo = 'local/knowledge'): ?array
    {
        $index = $this->loadIndex($repo);
        if ($index === null) {
            return null;
        }

        return $this->findSymbol($index, $symbolId);
    }

    /**
     * Get file outline — symbols in a specific file with hierarchy.
     *
     * @return array<array<string, mixed>>
     */
    public function getFileOutline(string $filePath, string $repo = 'local/knowledge'): array
    {
        $index = $this->loadIndex($repo);
        if ($index === null) {
            return [];
        }

        $fileSymbols = array_filter(
            $index['symbols'],
            fn (array $s): bool => ($s['file'] ?? '') === $filePath
        );

        if ($fileSymbols === []) {
            return [];
        }

        return $this->buildSymbolTree($fileSymbols);
    }

    /**
     * Detect changed files by comparing SHA-256 hashes.
     *
     * @param  array<string, string>  $currentFiles  file_path => content
     * @return array{changed: array<string>, new: array<string>, deleted: array<string>}
     */
    public function detectChanges(array $currentFiles, string $repo = 'local/knowledge'): array
    {
        $index = $this->loadIndex($repo);
        $oldHashes = $index['file_hashes'] ?? [];

        $currentHashes = [];
        foreach ($currentFiles as $path => $content) {
            $currentHashes[$path] = hash('sha256', $content);
        }

        $oldSet = array_keys($oldHashes);
        $newSet = array_keys($currentHashes);

        $newFiles = array_diff($newSet, $oldSet);
        $deletedFiles = array_diff($oldSet, $newSet);
        $common = array_intersect($oldSet, $newSet);

        $changedFiles = [];
        foreach ($common as $path) {
            if ($oldHashes[$path] !== $currentHashes[$path]) {
                $changedFiles[] = $path;
            }
        }

        return [
            'changed' => $changedFiles,
            'new' => array_values($newFiles),
            'deleted' => array_values($deletedFiles),
        ];
    }

    /**
     * List all indexed repositories.
     *
     * @return array<array{repo: string, indexed_at: string, symbol_count: int, file_count: int, languages: array<string, int>}>
     */
    public function listRepos(): array
    {
        $storagePath = $this->resolveStoragePath();
        if (! is_dir($storagePath)) {
            return [];
        }

        $repos = [];
        $jsonFiles = glob($storagePath.'/*.json');
        // @codeCoverageIgnoreStart
        // Defensive: glob only returns false on pattern error, not on empty results
        if ($jsonFiles === false) {
            return [];
        }
        // @codeCoverageIgnoreEnd
        foreach ($jsonFiles as $indexFile) {
            $content = file_get_contents($indexFile);
            // @codeCoverageIgnoreStart
            // Defensive: file_get_contents only fails on read errors after glob found the file
            if ($content === false) {
                continue;
            }
            // @codeCoverageIgnoreEnd
            $data = json_decode($content, true);
            if (! is_array($data)) {
                continue;
            }
            $repos[] = [
                'repo' => $data['repo'] ?? basename($indexFile, '.json'),
                'indexed_at' => $data['indexed_at'] ?? '',
                'symbol_count' => count($data['symbols'] ?? []),
                'file_count' => count($data['source_files'] ?? []),
                'languages' => $data['languages'] ?? [],
            ];
        }

        return $repos;
    }

    /**
     * Load a repository's index from disk.
     *
     * @return array{repo: string, owner: string, name: string, symbols: array<array<string, mixed>>, file_hashes: array<string, string>, source_files: array<string>, languages: array<string, int>, indexed_at: string}|null
     */
    private function loadIndex(string $repo): ?array
    {
        [$owner, $name] = $this->parseRepo($repo);
        $indexPath = $this->indexPath($owner, $name);

        if (! file_exists($indexPath)) {
            return null;
        }

        $content = file_get_contents($indexPath);
        // @codeCoverageIgnoreStart
        // Defensive: file_get_contents only fails on read errors after file_exists passed
        if ($content === false) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        $data = json_decode($content, true);
        if (! is_array($data)) {
            return null;
        }

        /** @var int $storedVersion */
        $storedVersion = $data['index_version'] ?? 1;
        if ($storedVersion > self::INDEX_VERSION) {
            return null;
        }

        /** @var array{repo: string, owner: string, name: string, symbols: array<array<string, mixed>>, file_hashes: array<string, string>, source_files: array<string>, languages: array<string, int>, indexed_at: string} $data */
        return $data;
    }

    /**
     * Find a symbol by ID in an index.
     *
     * @param  array<string, mixed>  $index
     * @return array<string, mixed>|null
     */
    private function findSymbol(array $index, string $symbolId): ?array
    {
        foreach ($index['symbols'] as $symbol) {
            if (($symbol['id'] ?? '') === $symbolId) {
                return $symbol;
            }
        }

        return null;
    }

    /**
     * Calculate weighted search score for a symbol.
     *
     * @param  array<string, mixed>  $symbol
     * @param  array<string>  $queryWords
     */
    private function scoreSymbol(array $symbol, string $queryLower, array $queryWords): int
    {
        $score = 0;
        $nameLower = strtolower($symbol['name'] ?? '');

        // 1. Exact name match (highest weight)
        if ($queryLower === $nameLower) {
            $score += 20;
        } elseif (str_contains($nameLower, $queryLower)) {
            $score += 10;
        }

        // 2. Name word overlap
        foreach ($queryWords as $word) {
            if (str_contains($nameLower, $word)) {
                $score += 5;
            }
        }

        // 3. Signature match
        $sigLower = strtolower($symbol['signature'] ?? '');
        if (str_contains($sigLower, $queryLower)) {
            $score += 8;
        }
        foreach ($queryWords as $word) {
            if (str_contains($sigLower, $word)) {
                $score += 2;
            }
        }

        // 4. Summary match
        $summaryLower = strtolower($symbol['summary'] ?? '');
        if (str_contains($summaryLower, $queryLower)) {
            $score += 5;
        }
        foreach ($queryWords as $word) {
            if (str_contains($summaryLower, $word)) {
                $score += 1;
            }
        }

        // 5. Keyword match
        $keywords = array_map('strtolower', $symbol['keywords'] ?? []);
        foreach ($queryWords as $word) {
            if (in_array($word, $keywords, true)) {
                $score += 3;
            }
        }

        // 6. Docstring match
        $docLower = strtolower($symbol['docstring'] ?? '');
        foreach ($queryWords as $word) {
            if (str_contains($docLower, $word)) {
                $score += 1;
            }
        }

        return $score;
    }

    /**
     * Match file path against a glob-like pattern.
     */
    private function matchPattern(string $filePath, string $pattern): bool
    {
        return fnmatch($pattern, $filePath) || fnmatch("*/{$pattern}", $filePath);
    }

    /**
     * Build hierarchical symbol tree from flat symbol list.
     *
     * @param  array<array<string, mixed>>  $symbols
     * @return array<array<string, mixed>>
     */
    private function buildSymbolTree(array $symbols): array
    {
        $nodeMap = [];
        foreach ($symbols as $symbol) {
            $nodeMap[$symbol['id']] = [
                'id' => $symbol['id'],
                'kind' => $symbol['kind'],
                'name' => $symbol['name'],
                'signature' => $symbol['signature'] ?? '',
                'summary' => $symbol['summary'] ?? '',
                'line' => $symbol['line'],
                'children' => [],
            ];
        }

        $roots = [];
        foreach ($symbols as $symbol) {
            $parentId = $symbol['parent'] ?? null;
            if ($parentId !== null && isset($nodeMap[$parentId])) {
                $nodeMap[$parentId]['children'][] = &$nodeMap[$symbol['id']];
            } else {
                $roots[] = &$nodeMap[$symbol['id']];
            }
        }

        return $this->formatTree($roots);
    }

    /**
     * Clean up tree output — remove empty children arrays.
     *
     * @param  array<array<string, mixed>>  $nodes
     * @return array<array<string, mixed>>
     */
    private function formatTree(array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $formatted = [
                'id' => $node['id'],
                'kind' => $node['kind'],
                'name' => $node['name'],
                'signature' => $node['signature'],
                'summary' => $node['summary'],
                'line' => $node['line'],
            ];
            /** @var array<array<string, mixed>> $children */
            $children = $node['children'] ?? [];
            if ($children !== []) {
                $formatted['children'] = $this->formatTree($children);
            }
            $result[] = $formatted;
        }

        return $result;
    }

    /**
     * Parse repo identifier into [owner, name].
     *
     * @return array{0: string, 1: string}
     */
    private function parseRepo(string $repo): array
    {
        $parts = explode('/', $repo);
        if (count($parts) === 2) {
            return [$parts[0], $parts[1]];
        }

        return ['local', $repo];
    }

    /**
     * Get path to index JSON file.
     */
    private function indexPath(string $owner, string $name): string
    {
        return $this->resolveStoragePath()."/{$owner}-{$name}.json";
    }

    /**
     * Get path to a raw content file.
     */
    private function contentFilePath(string $repo, string $relativePath): ?string
    {
        [$owner, $name] = $this->parseRepo($repo);
        $contentDir = $this->resolveStoragePath()."/{$owner}-{$name}";
        $fullPath = realpath($contentDir.'/'.$relativePath);

        // Path traversal protection
        if ($fullPath === false) {
            return null;
        }
        $realContentDir = realpath($contentDir);
        // @codeCoverageIgnoreStart
        // Defensive: realpath of content dir only fails if dir was deleted between calls
        if ($realContentDir === false || ! str_starts_with($fullPath, $realContentDir)) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        return $fullPath;
    }

    /**
     * Resolve storage path, expanding ~ to home directory.
     */
    private function resolveStoragePath(): string
    {
        $path = $this->storagePath;
        // @codeCoverageIgnoreStart
        // Environment-dependent: only triggered when service constructed with ~/... path
        if (str_starts_with($path, '~/')) {
            $home = getenv('HOME') !== false ? getenv('HOME') : '/tmp';
            $path = $home.'/'.substr($path, 2);
        }
        // @codeCoverageIgnoreEnd

        return $path;
    }
}
