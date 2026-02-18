<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmbeddingServiceInterface;
use App\Integrations\Qdrant\QdrantConnector;
use App\Integrations\Qdrant\Requests\CreateCollection;
use App\Integrations\Qdrant\Requests\GetCollectionInfo;
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

    private const SKIP_FILES = [
        'composer.lock',
        'package-lock.json',
        'yarn.lock',
        'pnpm-lock.yaml',
        'cargo.lock',
    ];

    private const MAX_LINES = 250;

    private const FILE_EXTENSIONS = [
        'php', 'py', 'js', 'ts', 'tsx', 'jsx', 'vue', 'md', 'json', // Existing
        'go', 'rs', 'java', 'c', 'cpp', 'h', 'hpp', 'sh', 'yaml', 'yml', 'toml', 'dockerfile' // Added
    ];

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
                ->notName(self::SKIP_FILES) // Exclude specific files
                ->exclude(self::SKIP_DIRS)
                ->ignoreDotFiles(true)
                ->ignoreVCS(true);

            // Detect repo name from path
            $repo = basename($basePath);

            foreach ($finder as $file) {
                // Skip large files (by line count)
                // We do a quick line count check here to avoid reading massive files fully if not needed
                // Or we can do it in indexFile. Doing it here saves resources.
                // Using iterator_count on file object might be slow for many files.
                // Let's just check size first? Or read line count.
                // SplFileObject::setFlags can read lines.
                // For now, let's just pass it and check in indexFile to keep loop fast.
                
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

        // Check line count
        $lineCount = substr_count($content, "\n") + 1;
        if ($lineCount > self::MAX_LINES) {
            return ['chunks' => 0, 'success' => false, 'error' => "Skipped: File too large ({$lineCount} lines > " . self::MAX_LINES . ")"];
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
     * @return array<array{filepath: string, repo: string, language: string, content: string, score: float, functions: array<string>}>
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
                'start_line' => $payload['start_line'] ?? 1,
                'end_line' => $payload['end_line'] ?? 1,
            ];
        }, $results);
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
            'go' => 'go',
            'rs' => 'rust',
            'java' => 'java',
            'c', 'h' => 'c',
            'cpp', 'hpp' => 'cpp',
            'sh' => 'bash',
            'yaml', 'yml' => 'yaml',
            'toml' => 'toml',
            'json' => 'json',
            'md' => 'markdown',
            'dockerfile' => 'dockerfile',
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
