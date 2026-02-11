<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\EnhancementQueueService;
use App\Services\OllamaService;
use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class EnhanceWorkerCommand extends Command
{
    protected $signature = 'enhance:worker
                            {--once : Process one item and exit}
                            {--status : Show queue status and exit}';

    protected $description = 'Process the enhancement queue using Ollama';

    public function handle(
        EnhancementQueueService $queue,
        OllamaService $ollama,
        QdrantService $qdrant,
    ): int {
        if ((bool) $this->option('status')) {
            return $this->showStatus($queue);
        }

        if (! $ollama->isAvailable()) {
            warning('Ollama is not available. Skipping enhancement processing.');

            return self::SUCCESS;
        }

        $processOnce = (bool) $this->option('once');

        if ($processOnce) {
            return $this->processOne($queue, $ollama, $qdrant);
        }

        return $this->processAll($queue, $ollama, $qdrant);
    }

    private function processOne(
        EnhancementQueueService $queue,
        OllamaService $ollama,
        QdrantService $qdrant,
    ): int {
        $item = $queue->dequeue();

        if ($item === null) {
            info('Enhancement queue is empty.');

            return self::SUCCESS;
        }

        return $this->processItem($item, $queue, $ollama, $qdrant);
    }

    private function processAll(
        EnhancementQueueService $queue,
        OllamaService $ollama,
        QdrantService $qdrant,
    ): int {
        $pending = $queue->pendingCount();

        if ($pending === 0) {
            info('Enhancement queue is empty.');

            return self::SUCCESS;
        }

        info("Processing {$pending} entries in enhancement queue...");

        $processed = 0;
        $failed = 0;

        while (($item = $queue->dequeue()) !== null) {
            $result = $this->processItem($item, $queue, $ollama, $qdrant);

            if ($result === self::SUCCESS) {
                $processed++;
            } else {
                $failed++;
            }
        }

        info("Enhancement complete: {$processed} processed, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{entry_id: string|int, title: string, content: string, category?: string|null, tags?: array<string>, project: string, queued_at: string}  $item
     */
    private function processItem(
        array $item,
        EnhancementQueueService $queue,
        OllamaService $ollama,
        QdrantService $qdrant,
    ): int {
        $entryId = $item['entry_id'];
        $project = $item['project'];

        $this->line("Enhancing entry: {$item['title']}");

        $enhancement = $ollama->enhance([
            'title' => $item['title'],
            'content' => $item['content'],
            'category' => $item['category'] ?? null,
            'tags' => $item['tags'] ?? [],
        ]);

        if ($enhancement['tags'] === [] && $enhancement['summary'] === '') {
            $queue->recordFailure("Empty enhancement response for entry {$entryId}");
            error("Failed to enhance entry {$entryId}: empty response");

            return self::FAILURE;
        }

        $fields = $this->buildUpdateFields($enhancement, $item);

        $success = $qdrant->updateFields($entryId, $fields, $project);

        if (! $success) {
            $queue->recordFailure("Failed to update Qdrant for entry {$entryId}");
            error("Failed to store enhancement for entry {$entryId}");

            return self::FAILURE;
        }

        $queue->recordSuccess();
        $this->line("<fg=green>Enhanced entry: {$item['title']}</>");

        return self::SUCCESS;
    }

    /**
     * Build the fields to update from enhancement results.
     *
     * @param  array{tags: array<string>, category: string|null, concepts: array<string>, summary: string}  $enhancement
     * @param  array{entry_id: string|int, title: string, content: string, category?: string|null, tags?: array<string>, project: string, queued_at: string}  $item
     * @return array<string, mixed>
     */
    private function buildUpdateFields(array $enhancement, array $item): array
    {
        $fields = [
            'enhanced' => true,
            'enhanced_at' => now()->toIso8601String(),
        ];

        // Merge AI tags with existing tags (deduplicated)
        $existingTags = $item['tags'] ?? [];
        $allTags = array_values(array_unique(array_merge($existingTags, $enhancement['tags'])));
        $fields['tags'] = $allTags;

        // Only set category if not already set
        if (($item['category'] ?? null) === null && $enhancement['category'] !== null) {
            $fields['category'] = $enhancement['category'];
        }

        if ($enhancement['concepts'] !== []) {
            $fields['concepts'] = $enhancement['concepts'];
        }

        if ($enhancement['summary'] !== '') {
            $fields['summary'] = $enhancement['summary'];
        }

        return $fields;
    }

    private function showStatus(EnhancementQueueService $queue): int
    {
        $status = $queue->getStatus();

        info('Enhancement Queue Status');

        table(
            ['Field', 'Value'],
            [
                ['Status', $status['status']],
                ['Pending', (string) $status['pending']],
                ['Processed', (string) $status['processed']],
                ['Failed', (string) $status['failed']],
                ['Last Processed', $status['last_processed'] ?? 'Never'],
                ['Last Error', $status['last_error'] ?? 'None'],
            ]
        );

        return self::SUCCESS;
    }
}
