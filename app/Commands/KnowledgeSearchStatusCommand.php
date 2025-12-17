<?php

declare(strict_types=1);

namespace App\Commands;

use App\Contracts\EmbeddingServiceInterface;
use App\Models\Entry;
use LaravelZero\Framework\Commands\Command;

class KnowledgeSearchStatusCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'search:status';

    /**
     * @var string
     */
    protected $description = 'Show search capabilities and configuration status';

    public function handle(EmbeddingServiceInterface $embeddingService): int
    {
        $this->info('Knowledge Base Search Status');
        $this->newLine();

        // Keyword Search Status
        $this->line('<fg=green>✓</> Keyword Search: <fg=green>Enabled</>');
        $this->line('  Searches title and content fields using SQL LIKE queries');
        $this->newLine();

        // Semantic Search Status
        /** @var bool $semanticEnabled */
        $semanticEnabled = config('search.semantic_enabled');
        /** @var string $embeddingProvider */
        $embeddingProvider = config('search.embedding_provider');

        $testEmbedding = $embeddingService->generate('test');
        $hasEmbeddingSupport = count($testEmbedding) > 0;

        if ($semanticEnabled && $hasEmbeddingSupport) {
            $this->line('<fg=green>✓</> Semantic Search: <fg=green>Enabled</>');
            $this->line("  Provider: {$embeddingProvider}");
        } else {
            $this->line('<fg=yellow>○</> Semantic Search: <fg=yellow>Not Configured</>');
            $this->line("  Provider: {$embeddingProvider}");
            $this->line('  To enable: Set SEMANTIC_SEARCH_ENABLED=true and configure an embedding provider');
        }

        $this->newLine();

        // Database Statistics
        $totalEntries = Entry::count();
        $entriesWithEmbeddings = Entry::whereNotNull('embedding')->count();

        $this->line('<fg=cyan>Database Statistics</>');
        $this->line("  Total entries: {$totalEntries}");
        $this->line("  Entries with embeddings: {$entriesWithEmbeddings}");

        if ($totalEntries > 0) {
            $percentage = round(($entriesWithEmbeddings / $totalEntries) * 100, 1);
            $this->line("  Indexed: {$percentage}%");
        }

        $this->newLine();

        // Usage Instructions
        $this->line('<fg=cyan>Usage</>');
        $this->line('  Keyword search:  ./know knowledge:search "your query"');
        if ($semanticEnabled && $hasEmbeddingSupport) {
            $this->line('  Semantic search: ./know knowledge:search "your query" --semantic');
        } else {
            $this->line('  Semantic search: <fg=yellow>Not available</> (configure embedding provider first)');
        }
        $this->line('  Index entries:   ./know knowledge:index');

        return self::SUCCESS;
    }
}
