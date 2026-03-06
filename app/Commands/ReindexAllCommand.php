<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\CodeIndexerService;
use App\Services\SymbolIndexService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

class ReindexAllCommand extends Command
{
    protected $signature = 'reindex:all
                            {--path= : Base directory containing git repos (default: ~/projects)}
                            {--kind=* : Symbol kinds to vectorize (default: class)}
                            {--skip-vectorize : Only re-index symbols, skip vectorization}';

    protected $description = 'Incrementally re-index and vectorize all git repositories';

    public function handle(
        SymbolIndexService $symbolIndex,
        CodeIndexerService $codeIndexer,
    ): int {
        $basePath = $this->option('path');
        if (! is_string($basePath) || $basePath === '') {
            $home = getenv('HOME') !== false ? (string) getenv('HOME') : '/tmp';
            $basePath = "{$home}/projects";
        }

        if (! is_dir($basePath)) {
            error("Directory not found: {$basePath}");

            return self::FAILURE;
        }

        $dirs = glob("{$basePath}/*/", GLOB_ONLYDIR);
        if ($dirs === false || $dirs === []) {
            warning("No subdirectories found in {$basePath}");

            return self::SUCCESS;
        }

        $repos = array_filter($dirs, fn (string $dir): bool => is_dir("{$dir}.git"));
        if ($repos === []) {
            warning("No git repositories found in {$basePath}");

            return self::SUCCESS;
        }

        info('Found '.count($repos).' git repositories');

        $skipVectorize = (bool) $this->option('skip-vectorize');

        /** @var array<string> $kinds */
        $kinds = $this->option('kind');
        if ($kinds === []) {
            $kinds = ['class'];
        }

        $indexed = 0;
        $vectorized = 0;
        $errors = 0;

        foreach ($repos as $dir) {
            $name = basename(rtrim($dir, '/'));
            $repo = "local/{$name}";

            note("Indexing {$repo}...");
            $result = $symbolIndex->indexFolder($dir, incremental: true);

            if (! ($result['success'] ?? false)) {
                warning('  Failed: '.($result['error'] ?? 'unknown error'));
                $errors++;

                continue;
            }

            $indexed++;
            $symbolCount = $result['symbol_count'] ?? 0;
            note("  {$symbolCount} symbols indexed");

            if ($skipVectorize) {
                continue;
            }

            $home = getenv('HOME') !== false ? (string) getenv('HOME') : '/tmp';
            $indexPath = "{$home}/.code-index/".str_replace('/', '-', $repo).'.json';

            if (! file_exists($indexPath)) {
                warning('  Index file not found, skipping vectorization');

                continue;
            }

            if (! $codeIndexer->ensureCollection()) {
                warning('  Failed to ensure Qdrant collection');

                continue;
            }

            $vResult = $codeIndexer->vectorizeFromIndex(
                $indexPath,
                $repo,
                $symbolIndex,
                $kinds,
            );

            $vectorized++;
            note("  Vectorized: {$vResult['success']}/{$vResult['total']} ({$vResult['failed']} failed)");

            $pruneResult = $codeIndexer->pruneStaleSymbols($indexPath, $repo);
            if ($pruneResult['deleted'] > 0) {
                note("  Pruned {$pruneResult['deleted']} stale symbols");
            }
        }

        info("Done: {$indexed} indexed, {$vectorized} vectorized, {$errors} errors");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
