<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\SymbolIndexService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class IndexCodeCommand extends Command
{
    protected $signature = 'index-code
                            {path? : Path to index (defaults to current directory)}
                            {--incremental : Only re-index changed files}
                            {--list : List all indexed repositories}';

    protected $description = 'Index code files using tree-sitter AST parsing';

    public function handle(SymbolIndexService $indexer): int
    {
        if ((bool) $this->option('list')) {
            return $this->listRepos($indexer);
        }

        $pathArg = $this->argument('path');
        $path = is_string($pathArg) ? $pathArg : getcwd();
        $incremental = (bool) $this->option('incremental');

        if ($path === false || ! is_dir($path)) {
            error("Invalid path: {$path}");

            return self::FAILURE;
        }

        info('Symbol Indexer (tree-sitter AST)');
        note('Path: '.$path);

        /** @var array{success: bool, repo?: string, file_count?: int, symbol_count?: int, languages?: array<string, int>, error?: string, incremental?: bool, changed?: int, new?: int, deleted?: int, warnings?: array<string>} $result */
        $result = spin(
            fn (): array => $indexer->indexFolder($path, $incremental),
            $incremental ? 'Incremental indexing...' : 'Indexing with tree-sitter...'
        );

        if (! $result['success']) {
            error($result['error'] ?? 'Indexing failed.');

            return self::FAILURE;
        }

        if (isset($result['message'])) {
            info($result['message']);

            return self::SUCCESS;
        }

        info('Indexing complete!');

        $rows = [
            ['Repository', $result['repo'] ?? 'unknown'],
        ];

        if (isset($result['incremental']) && $result['incremental']) {
            $rows[] = ['Changed files', (string) ($result['changed'] ?? 0)];
            $rows[] = ['New files', (string) ($result['new'] ?? 0)];
            $rows[] = ['Deleted files', (string) ($result['deleted'] ?? 0)];
        } else {
            $rows[] = ['Files indexed', (string) ($result['file_count'] ?? 0)];
        }

        $rows[] = ['Symbols extracted', (string) ($result['symbol_count'] ?? 0)];

        if (isset($result['languages'])) {
            $langStr = implode(', ', array_map(
                fn (string $lang, int $count): string => "{$lang}: {$count}",
                array_keys($result['languages']),
                array_values($result['languages'])
            ));
            $rows[] = ['Languages', $langStr];
        }

        table(['Metric', 'Value'], $rows);

        if (isset($result['warnings']) && $result['warnings'] !== []) {
            foreach (array_slice($result['warnings'], 0, 5) as $warn) {
                warning($warn);
            }
        }

        return self::SUCCESS;
    }

    private function listRepos(SymbolIndexService $indexer): int
    {
        $repos = $indexer->listRepos();

        if ($repos === []) {
            info('No indexed repositories found.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($repos as $repo) {
            $langs = implode(', ', array_keys($repo['languages']));
            $rows[] = [
                $repo['repo'],
                (string) $repo['file_count'],
                (string) $repo['symbol_count'],
                $langs,
                $repo['indexed_at'],
            ];
        }

        table(['Repository', 'Files', 'Symbols', 'Languages', 'Indexed At'], $rows);

        return self::SUCCESS;
    }
}
