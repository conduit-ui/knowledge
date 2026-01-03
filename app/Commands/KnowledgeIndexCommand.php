<?php

declare(strict_types=1);

namespace App\Commands;

use App\Contracts\ChromaDBClientInterface;
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
                            {--prune : Remove orphaned entries from ChromaDB}
                            {--dry-run : Show what would be pruned without deleting}
                            {--batch=100 : Batch size for indexing}';

    /**
     * @var string
     */
    protected $description = 'Index or reindex knowledge entries for semantic search';

    public function handle(EmbeddingServiceInterface $embeddingService, ChromaDBClientInterface $chromaClient): int
    {
        /** @var bool $force */
        $force = $this->option('force');

        /** @var bool $prune */
        $prune = $this->option('prune');

        /** @var bool $dryRun */
        $dryRun = $this->option('dry-run');

        /** @var int $batchSize */
        $batchSize = (int) $this->option('batch');

        // Handle prune operation (only needs ChromaDB connectivity, not full config)
        if ($prune) {
            if (! $chromaClient->isAvailable()) {
                $this->error('ChromaDB is not available. Check connection settings.');

                return self::FAILURE;
            }

            return $this->pruneOrphans($chromaClient, $dryRun);
        }

        // Check if ChromaDB is enabled for indexing
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

    /**
     * Remove orphaned entries from ChromaDB that no longer exist in SQLite.
     */
    private function pruneOrphans(ChromaDBClientInterface $chromaClient, bool $dryRun): int
    {
        $this->info('Scanning ChromaDB for orphaned entries...');

        try {
            $collection = $chromaClient->getOrCreateCollection('knowledge_entries');
            $chromaData = $chromaClient->getAll($collection['id']);
        } catch (\RuntimeException $e) {
            $this->error('Failed to connect to ChromaDB: '.$e->getMessage());

            return self::FAILURE;
        }

        $chromaIds = $chromaData['ids'];
        $chromaMetadatas = $chromaData['metadatas'];

        $this->line('Found '.count($chromaIds).' documents in ChromaDB');

        // Get all valid entry IDs from SQLite
        $validEntryIds = Entry::pluck('id')->toArray();
        $validEntryIdSet = array_flip($validEntryIds);

        $this->line('Found '.count($validEntryIds).' entries in SQLite');
        $this->newLine();

        // Find orphaned documents (entry_id not in SQLite, or no entry_id at all)
        $orphanedDocIds = [];
        $orphanedWithEntryId = 0;
        $orphanedNoEntryId = 0;

        foreach ($chromaIds as $index => $docId) {
            $metadata = $chromaMetadatas[$index] ?? [];
            $entryId = $metadata['entry_id'] ?? null;

            if ($entryId === null) {
                // Document has no entry_id (e.g., vision docs)
                $orphanedDocIds[] = $docId;
                $orphanedNoEntryId++;
            } elseif (! isset($validEntryIdSet[$entryId])) {
                // entry_id doesn't exist in SQLite
                $orphanedDocIds[] = $docId;
                $orphanedWithEntryId++;
            }
        }

        if (count($orphanedDocIds) === 0) {
            $this->info('No orphaned entries found. ChromaDB is in sync.');

            return self::SUCCESS;
        }

        $this->warn('Found '.count($orphanedDocIds).' orphaned documents:');
        $this->line("  - {$orphanedWithEntryId} with deleted entry_ids");
        $this->line("  - {$orphanedNoEntryId} without entry_ids (external sources)");
        $this->newLine();

        if ($dryRun) {
            $this->info('Dry run - no changes made.');
            $this->line('Run without --dry-run to delete these documents.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Delete these orphaned documents from ChromaDB?')) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        // Delete in batches
        $batchSize = 100;
        $deleted = 0;

        $bar = $this->output->createProgressBar(count($orphanedDocIds));
        $bar->start();

        foreach (array_chunk($orphanedDocIds, $batchSize) as $batch) {
            try {
                $chromaClient->delete($collection['id'], $batch);
                $deleted += count($batch);
            } catch (\RuntimeException $e) {
                $this->newLine();
                $this->warn('Failed to delete batch: '.$e->getMessage());
            }
            $bar->advance(count($batch));
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Deleted {$deleted} orphaned documents from ChromaDB.");

        return self::SUCCESS;
    }
}
