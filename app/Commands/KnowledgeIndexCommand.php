<?php

declare(strict_types=1);

namespace App\Commands;

use App\Contracts\EmbeddingServiceInterface;
use App\Models\Entry;
use App\Services\ChromaDBIndexService;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;

class KnowledgeIndexCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'index
                            {--force : Force reindexing of all entries}
                            {--batch=100 : Batch size for indexing}';

    /**
     * @var string
     */
    protected $description = 'Index or reindex knowledge entries for semantic search';

    public function handle(EmbeddingServiceInterface $embeddingService): int
    {
        /** @var bool $force */
        $force = $this->option('force');

        /** @var int $batchSize */
        $batchSize = (int) $this->option('batch');

        // Check if ChromaDB is enabled
        /** @var bool $chromaDBEnabled */
        $chromaDBEnabled = config('search.chromadb.enabled', false);

        if (! $chromaDBEnabled) {
            $this->warn('ChromaDB is not enabled.');
            $this->line('Enable it with: ./know knowledge:config set chromadb.enabled true');
            $this->newLine();

            return $this->showDryRun($force);
        }

        // Check if embedding service is available
        $testEmbedding = $embeddingService->generate('test');
        if (count($testEmbedding) === 0) {
            $this->warn('Embedding service is not responding.');
            $this->line('Ensure the embedding server is running:');
            $this->line('  ./know knowledge:serve start');
            $this->newLine();

            return $this->showDryRun($force);
        }

        // Get entries to index
        $entries = $this->getEntriesToIndex($force);

        if ($entries->isEmpty()) {
            $this->info('All entries are already indexed.');

            return self::SUCCESS;
        }

        $this->info('Indexing '.$entries->count().' '.str('entry')->plural($entries->count()).' to ChromaDB...');
        $this->newLine();

        return $this->indexEntries($entries, $batchSize);
    }

    /**
     * Show dry-run information when indexing is not configured.
     */
    private function showDryRun(bool $force): int
    {
        if ($force) {
            $entryCount = Entry::count();
            $message = "Would reindex all {$entryCount} ".str('entry')->plural($entryCount);
        } else {
            $entryCount = Entry::whereNull('embedding')->count();
            $message = "Would index {$entryCount} new ".str('entry')->plural($entryCount);
        }

        $this->info($message);

        if ($entryCount === 0) {
            $this->line('No entries to index.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Once configured, this command will:');
        $this->line('  1. Generate embeddings for entry content');
        $this->line('  2. Store embeddings in ChromaDB');
        $this->line('  3. Enable semantic search');

        return self::SUCCESS;
    }

    /**
     * Get entries that need to be indexed.
     *
     * @return Collection<int, Entry>
     */
    private function getEntriesToIndex(bool $force): Collection
    {
        if ($force) {
            return Entry::all();
        }

        return Entry::whereNull('embedding')->get();
    }

    /**
     * Index entries using ChromaDB.
     *
     * @param  Collection<int, Entry>  $entries
     */
    private function indexEntries(Collection $entries, int $batchSize): int
    {
        $indexService = app(ChromaDBIndexService::class);
        $indexed = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($entries->count());
        $bar->start();

        $entries->chunk($batchSize)->each(function (Collection $batch) use ($indexService, &$indexed, &$failed, $bar): void {
            try {
                $indexService->indexBatch($batch);
                $indexed += $batch->count();
            } catch (\Throwable $e) {
                // Fall back to individual indexing on batch failure
                $batch->each(function (Entry $entry) use ($indexService, &$indexed, &$failed): void {
                    try {
                        $indexService->indexEntry($entry);
                        $indexed++;
                    } catch (\Throwable) {
                        $failed++;
                    }
                });
            }

            $bar->advance($batch->count());
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Indexed {$indexed} ".str('entry')->plural($indexed).' successfully.');

        if ($failed > 0) {
            $this->warn("Failed to index {$failed} ".str('entry')->plural($failed).'.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
