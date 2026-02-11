<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\CodeIndexerService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class IndexCodeCommand extends Command
{
    protected $signature = 'index-code
                            {--path=* : Additional paths to index}
                            {--dry-run : Show what would be indexed without actually indexing}
                            {--stats : Show indexing statistics only}';

    protected $description = 'Index code files for semantic search';

    /** @var array<string> */
    private const DEFAULT_PATHS = [];

    public function handle(CodeIndexerService $indexer): int
    {
        /** @var array<string> $additionalPaths */
        $additionalPaths = (array) $this->option('path');
        $dryRun = (bool) $this->option('dry-run');
        $statsOnly = (bool) $this->option('stats');

        $paths = array_unique(array_merge(self::DEFAULT_PATHS, $additionalPaths));

        // Filter to existing paths
        $validPaths = array_filter($paths, fn ($p): bool => is_dir($p));

        if ($validPaths === []) {
            error('No valid paths to index.');

            return self::FAILURE;
        }

        info('Code Indexer');
        note('Paths to index: '.implode(', ', array_map('basename', $validPaths)));

        // Ensure collection exists
        if (! $dryRun) {
            $created = spin(
                fn (): bool => $indexer->ensureCollection(),
                'Ensuring code collection exists...'
            );

            if (! $created) {
                error('Failed to create/verify code collection in Qdrant.');

                return self::FAILURE;
            }
        }

        // Collect all files first
        $files = [];
        foreach ($indexer->findFiles($validPaths) as $file) {
            $files[] = $file;
        }

        $totalFiles = count($files);
        info("Found {$totalFiles} files to index.");

        if ($statsOnly || $dryRun) {
            $this->showStats($files);

            if ($dryRun) {
                warning('Dry run - no files were indexed.');
            }

            return self::SUCCESS;
        }

        if ($totalFiles === 0) {
            warning('No files found to index.');

            return self::SUCCESS;
        }

        // Index files with progress bar
        $indexed = 0;
        $failed = 0;
        $totalChunks = 0;
        $errors = [];

        $progress = progress(
            label: 'Indexing files...',
            steps: $totalFiles
        );

        $progress->start();

        foreach ($files as $file) {
            $result = $indexer->indexFile($file['path'], $file['repo']);

            if ($result['success']) {
                $indexed++;
                $totalChunks += $result['chunks'];
            } else {
                $failed++;
                if (isset($result['error'])) {
                    $errors[$file['path']] = $result['error'];
                }
            }

            $progress->advance();
        }

        $progress->finish();

        // Show results
        info('Indexing complete!');

        table(
            ['Metric', 'Value'],
            [
                ['Files indexed', (string) $indexed],
                ['Files failed', (string) $failed],
                ['Total chunks', (string) $totalChunks],
            ]
        );

        if ($errors !== [] && count($errors) <= 10) {
            warning('Errors:');
            foreach ($errors as $path => $err) {
                note("  {$path}: {$err}");
            }
        } elseif ($errors !== []) {
            warning(count($errors).' files had errors.');
        }

        return $failed > 0 && $indexed === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Show statistics about files to be indexed.
     *
     * @param  array<array{path: string, repo: string}>  $files
     */
    private function showStats(array $files): void
    {
        $byRepo = [];
        $byLang = [];

        foreach ($files as $file) {
            $repo = $file['repo'];
            $ext = pathinfo($file['path'], PATHINFO_EXTENSION);
            $lang = $this->extToLang($ext);

            $byRepo[$repo] = ($byRepo[$repo] ?? 0) + 1;
            $byLang[$lang] = ($byLang[$lang] ?? 0) + 1;
        }

        info('Files by repository:');
        $repoRows = [];
        foreach ($byRepo as $repo => $count) {
            $repoRows[] = [$repo, (string) $count];
        }
        table(['Repository', 'Files'], $repoRows);

        info('Files by language:');
        $langRows = [];
        foreach ($byLang as $lang => $count) {
            $langRows[] = [$lang, (string) $count];
        }
        table(['Language', 'Files'], $langRows);
    }

    private function extToLang(string $ext): string
    {
        return match (strtolower($ext)) {
            'php' => 'PHP',
            'py' => 'Python',
            'js', 'jsx' => 'JavaScript',
            'ts', 'tsx' => 'TypeScript',
            'vue' => 'Vue',
            default => 'Other',
        };
    }
}
