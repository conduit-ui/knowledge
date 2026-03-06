<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\CodeIndexerService;
use App\Services\SymbolIndexService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class VectorizeCodeCommand extends Command
{
    protected $signature = 'vectorize-code
                            {repo : Repository identifier (e.g. local/pstrax-laravel)}
                            {--kind=* : Symbol kinds to include (e.g. class, method, function)}
                            {--language= : Filter by language (e.g. php, typescript)}';

    protected $description = 'Vectorize tree-sitter symbols into Qdrant for semantic code search';

    public function handle(SymbolIndexService $symbolIndex, CodeIndexerService $codeIndexer): int
    {
        $repo = $this->argument('repo');
        if (! is_string($repo)) {
            error('Repository argument is required.');

            return self::FAILURE;
        }

        $home = getenv('HOME') !== false ? (string) getenv('HOME') : '/tmp';
        $indexPath = "{$home}/.code-index/".str_replace('/', '-', $repo).'.json';

        if (! file_exists($indexPath)) {
            error("Index not found at {$indexPath}. Run index-code first.");

            return self::FAILURE;
        }

        if (! $codeIndexer->ensureCollection()) {
            error('Failed to create/verify Qdrant code collection.');

            return self::FAILURE;
        }

        /** @var array<string> $kinds */
        $kinds = $this->option('kind');
        $language = $this->option('language');
        $language = is_string($language) ? $language : null;

        $label = $repo;
        if ($kinds !== []) {
            $label .= ' ('.implode(', ', $kinds).')';
        }
        if ($language !== null) {
            $label .= " [{$language}]";
        }

        info("Vectorizing symbols from {$label}");

        $lastReport = 0;
        $result = $codeIndexer->vectorizeFromIndex(
            $indexPath,
            $repo,
            $symbolIndex,
            $kinds,
            $language,
            function (int $success, int $failed, int $total) use (&$lastReport): void {
                $done = $success + $failed;
                if ($done - $lastReport >= 100 || $done === $total) {
                    $lastReport = $done;
                    note("{$done}/{$total} processed ({$success} ok, {$failed} failed)");
                }
            },
        );

        info("Done: {$result['success']}/{$result['total']} symbols vectorized, {$result['failed']} failed");

        return self::SUCCESS;
    }
}
