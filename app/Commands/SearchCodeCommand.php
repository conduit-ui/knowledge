<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\SymbolIndexService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;

class SearchCodeCommand extends Command
{
    protected $signature = 'search-code
                            {query : The symbol search query}
                            {--limit=10 : Maximum number of results}
                            {--repo=local/knowledge : Repository identifier}
                            {--kind= : Filter by symbol kind (class, method, function, type)}
                            {--file= : Filter by file pattern (e.g. */Services/*)}
                            {--show-source : Show symbol source code}
                            {--outline= : Show file outline instead of searching}';

    protected $description = 'Search code symbols via tree-sitter index';

    public function handle(SymbolIndexService $indexer): int
    {
        $outlineFile = $this->option('outline');
        if (is_string($outlineFile) && $outlineFile !== '') {
            return $this->showOutline($indexer, $outlineFile);
        }

        $queryArg = $this->argument('query');
        $query = is_string($queryArg) ? $queryArg : '';
        $limit = (int) $this->option('limit');
        $repo = is_string($this->option('repo')) ? $this->option('repo') : 'local/knowledge';
        $kind = is_string($this->option('kind')) ? $this->option('kind') : null;
        $filePattern = is_string($this->option('file')) ? $this->option('file') : null;
        $showSource = (bool) $this->option('show-source');

        if (trim($query) === '') {
            error('Query cannot be empty.');

            return self::FAILURE;
        }

        /** @var array<array{id: string, kind: string, name: string, file: string, line: int, signature: string, summary: string, score: int}> $results */
        $results = spin(
            fn (): array => $indexer->searchSymbols($query, $repo, $kind, $filePattern, $limit),
            'Searching symbols...'
        );

        if ($results === []) {
            info('No results found.');

            return self::SUCCESS;
        }

        info(count($results).' results found:');

        foreach ($results as $i => $result) {
            $num = $i + 1;

            note("[{$num}] {$result['name']} ({$result['kind']})");
            note("    {$result['file']}:{$result['line']}");
            note("    {$result['signature']}");

            if ($result['summary'] !== '' && $result['summary'] !== $result['signature']) {
                note("    {$result['summary']}");
            }

            if ($showSource) {
                $source = $indexer->getSymbolSource($result['id'], $repo);
                if ($source !== null) {
                    $this->line('');
                    $this->line('    '.str_repeat('-', 60));
                    $sourceLines = explode("\n", $source);
                    $preview = array_slice($sourceLines, 0, 20);
                    foreach ($preview as $line) {
                        $this->line('    '.$line);
                    }
                    if (count($sourceLines) > 20) {
                        $this->line('    ... ('.(count($sourceLines) - 20).' more lines)');
                    }
                    $this->line('    '.str_repeat('-', 60));
                }
            }

            $this->line('');
        }

        return self::SUCCESS;
    }

    private function showOutline(SymbolIndexService $indexer, string $filePath): int
    {
        $repo = is_string($this->option('repo')) ? $this->option('repo') : 'local/knowledge';

        $outline = $indexer->getFileOutline($filePath, $repo);

        if ($outline === []) {
            info('No symbols found in file.');

            return self::SUCCESS;
        }

        info("Outline: {$filePath}");
        $this->renderOutline($outline);

        return self::SUCCESS;
    }

    /**
     * @param  array<array<string, mixed>>  $nodes
     */
    private function renderOutline(array $nodes, int $depth = 0): void
    {
        $indent = str_repeat('  ', $depth);
        foreach ($nodes as $node) {
            $kind = $node['kind'] ?? '';
            $name = $node['name'] ?? '';
            $line = $node['line'] ?? 0;
            note("{$indent}{$kind} {$name} (line {$line})");

            if (isset($node['children']) && $node['children'] !== []) {
                $this->renderOutline($node['children'], $depth + 1);
            }
        }
    }
}
