<?php

declare(strict_types=1);

namespace App\Commands;

use App\Contracts\EmbeddingServiceInterface;
use LaravelZero\Framework\Commands\Command;

use function Termwind\render;

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
        // Gather data
        /** @var bool $semanticEnabled */
        $semanticEnabled = config('search.semantic_enabled');
        /** @var string $vectorStore */
        $vectorStore = config('search.vector_store', 'qdrant');
        /** @var string|null $embeddingProvider */
        $embeddingProvider = config('search.embedding_provider') ?: 'none';

        $testEmbedding = $embeddingService->generate('test');
        $hasEmbeddingSupport = $testEmbedding !== [];

        $qdrant = app(\App\Services\QdrantService::class);
        $entries = $qdrant->search('', [], 10000);
        $totalEntries = $entries->count();

        $semanticHealthy = $semanticEnabled && $hasEmbeddingSupport;

        // Header
        render(<<<'HTML'
            <div class="mx-2 my-1">
                <div class="px-4 py-2 bg-gray-800">
                    <span class="text-gray-400 font-bold">SEARCH CAPABILITIES STATUS</span>
                </div>
            </div>
        HTML);

        // Keyword Search Card
        render(<<<'HTML'
            <div class="mx-2">
                <div class="px-4 py-2 bg-gray-900 mb-1">
                    <div>
                        <span class="text-green mr-1">✓</span>
                        <span class="text-white font-bold">Keyword Search</span>
                        <span class="text-green font-bold ml-1">[Enabled]</span>
                    </div>
                    <div class="text-gray-500 ml-2">SQL LIKE queries on title and content</div>
                </div>
            </div>
        HTML);

        // Semantic Search Card
        $semanticColor = $semanticHealthy ? 'green' : 'yellow';
        $semanticIcon = $semanticHealthy ? '✓' : '○';
        $semanticStatus = $semanticHealthy ? 'Enabled' : 'Not Configured';

        render(<<<HTML
            <div class="mx-2">
                <div class="px-4 py-2 bg-gray-900 mb-1">
                    <div>
                        <span class="text-{$semanticColor} mr-1">{$semanticIcon}</span>
                        <span class="text-white font-bold">Semantic Search</span>
                        <span class="text-{$semanticColor} font-bold ml-1">[{$semanticStatus}]</span>
                    </div>
                    <div class="text-gray-500 ml-2">Embedding: {$embeddingProvider} · Vector: {$vectorStore}</div>
                </div>
            </div>
        HTML);

        // Database Statistics
        render(<<<'HTML'
            <div class="mx-2 my-1 mt-2">
                <div class="px-2 py-1">
                    <span class="text-gray-400 font-bold">DATABASE</span>
                </div>
            </div>
        HTML);

        render(<<<HTML
            <div class="mx-2">
                <div class="px-4 py-2 bg-gray-900 mb-1">
                    <div>
                        <span class="text-gray-400">Total Entries:</span>
                        <span class="text-white font-bold ml-1">{$totalEntries}</span>
                    </div>
                    <div class="mt-1">
                        <span class="text-gray-400">Vector Store:</span>
                        <span class="text-cyan-400 ml-1">{$vectorStore}</span>
                    </div>
                </div>
            </div>
        HTML);

        // Usage Instructions
        $semanticCommand = $semanticHealthy
            ? './know knowledge:search "query" --semantic'
            : '<span class="text-yellow">Configure provider first</span>';

        render(<<<'HTML'
            <div class="mx-2 my-1 mt-2">
                <div class="px-2 py-1">
                    <span class="text-gray-400 font-bold">USAGE</span>
                </div>
            </div>
        HTML);

        render(<<<'HTML'
            <div class="mx-2">
                <div class="px-4 py-1 bg-gray-900 mb-1">
                    <div>
                        <span class="text-gray-400">Keyword Search:</span>
                        <span class="text-cyan-400 ml-1">./know knowledge:search "query"</span>
                    </div>
                </div>
            </div>
        HTML);

        render(<<<HTML
            <div class="mx-2">
                <div class="px-4 py-1 bg-gray-900 mb-1">
                    <div>
                        <span class="text-gray-400">Semantic Search:</span>
                        <span class="ml-1">{$semanticCommand}</span>
                    </div>
                </div>
            </div>
        HTML);

        render(<<<'HTML'
            <div class="mx-2">
                <div class="px-4 py-1 bg-gray-900 mb-1">
                    <div>
                        <span class="text-gray-400">Index Entries:</span>
                        <span class="text-cyan-400 ml-1">./know knowledge:index</span>
                    </div>
                </div>
            </div>
        HTML);

        return self::SUCCESS;
    }
}
