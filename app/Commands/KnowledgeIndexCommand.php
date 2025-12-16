<?php

declare(strict_types=1);

namespace App\Commands;

use App\Contracts\EmbeddingServiceInterface;
use App\Models\Entry;
use LaravelZero\Framework\Commands\Command;

class KnowledgeIndexCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'knowledge:index
                            {--force : Force reindexing of all entries}';

    /**
     * @var string
     */
    protected $description = 'Index or reindex knowledge entries for semantic search';

    public function handle(EmbeddingServiceInterface $embeddingService): int
    {
        /** @var bool $force */
        $force = $this->option('force');

        // Check if semantic search is configured
        /** @var bool $semanticEnabled */
        $semanticEnabled = config('search.semantic_enabled');
        if (! $semanticEnabled) {
            $this->warn('Semantic search is not enabled.');
            $this->line('Set SEMANTIC_SEARCH_ENABLED=true in your .env file to enable it.');
            $this->newLine();
        }

        // Check if embedding service is available
        $testEmbedding = $embeddingService->generate('test');
        if (count($testEmbedding) === 0) {
            $this->warn('Semantic indexing is not configured.');
            /** @var string $provider */
            $provider = config('search.embedding_provider');
            $this->line("The current embedding provider is: {$provider}");
            $this->newLine();
            $this->line('To enable semantic search, configure one of the following:');
            $this->line('  - OpenAI API (future)');
            $this->line('  - ChromaDB (future)');
            $this->newLine();
        }

        // Count entries that would be indexed
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
        $this->line('Once semantic search is configured, this command will:');
        $this->line('  1. Generate embeddings for entry content');
        $this->line('  2. Store embeddings in the database');
        $this->line('  3. Enable semantic search via the --semantic flag');

        return self::SUCCESS;
    }
}
