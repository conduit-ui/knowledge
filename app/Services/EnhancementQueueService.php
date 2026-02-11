<?php

declare(strict_types=1);

namespace App\Services;

class EnhancementQueueService
{
    private readonly string $queuePath;

    private readonly string $statusPath;

    public function __construct(
        private readonly KnowledgePathService $pathService,
    ) {
        $knowledgeDir = $this->pathService->getKnowledgeDirectory();
        $this->queuePath = $knowledgeDir.'/enhance_queue.json';
        $this->statusPath = $knowledgeDir.'/enhance_status.json';
    }

    /**
     * Queue an entry for enhancement.
     *
     * @param  array{id: string|int, title: string, content: string, category?: string|null, tags?: array<string>}  $entry
     */
    public function queue(array $entry, string $project = 'default'): void
    {
        $items = $this->loadQueue();

        $items[] = [
            'entry_id' => $entry['id'],
            'title' => $entry['title'],
            'content' => $entry['content'],
            'category' => $entry['category'] ?? null,
            'tags' => $entry['tags'] ?? [],
            'project' => $project,
            'queued_at' => now()->toIso8601String(),
        ];

        $this->saveQueue($items);
    }

    /**
     * Dequeue the next entry for processing.
     *
     * @return array{entry_id: string|int, title: string, content: string, category?: string|null, tags?: array<string>, project: string, queued_at: string}|null
     */
    public function dequeue(): ?array
    {
        $items = $this->loadQueue();

        if ($items === []) {
            return null;
        }

        $item = array_shift($items);
        $this->saveQueue($items);

        /** @var array{entry_id: string|int, title: string, content: string, category?: string|null, tags?: array<string>, project: string, queued_at: string} $item */
        return $item;
    }

    /**
     * Get the number of pending items in the queue.
     */
    public function pendingCount(): int
    {
        return count($this->loadQueue());
    }

    /**
     * Check if a specific entry is in the queue.
     */
    public function isQueued(string|int $entryId): bool
    {
        $items = $this->loadQueue();

        foreach ($items as $item) {
            if (($item['entry_id'] ?? null) === $entryId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear the enhancement queue.
     */
    public function clear(): void
    {
        $this->saveQueue([]);
        $this->updateStatus('idle', 0);
    }

    /**
     * Get the current enhancement status.
     *
     * @return array{status: string, pending: int, processed: int, failed: int, last_processed: string|null, last_error: string|null}
     */
    public function getStatus(): array
    {
        $default = [
            'status' => 'idle',
            'pending' => 0,
            'processed' => 0,
            'failed' => 0,
            'last_processed' => null,
            'last_error' => null,
        ];

        $pendingCount = $this->pendingCount();

        if (! file_exists($this->statusPath)) {
            $default['pending'] = $pendingCount;
            $default['status'] = $pendingCount > 0 ? 'pending' : 'idle';

            return $default;
        }

        $content = file_get_contents($this->statusPath);
        if ($content === false) {
            $default['pending'] = $pendingCount;

            return $default;
        }

        $status = json_decode($content, true);
        if (! is_array($status)) {
            $default['pending'] = $pendingCount;

            return $default;
        }

        return [
            'status' => $pendingCount > 0 ? 'pending' : ($status['status'] ?? 'idle'),
            'pending' => $pendingCount,
            'processed' => (int) ($status['processed'] ?? 0),
            'failed' => (int) ($status['failed'] ?? 0),
            'last_processed' => $status['last_processed'] ?? null,
            'last_error' => $status['last_error'] ?? null,
        ];
    }

    /**
     * Record a successful enhancement.
     */
    public function recordSuccess(): void
    {
        $status = $this->getStatus();
        $this->updateStatus(
            $this->pendingCount() > 0 ? 'processing' : 'idle',
            $this->pendingCount(),
            $status['processed'] + 1,
            $status['failed'],
            now()->toIso8601String()
        );
    }

    /**
     * Record a failed enhancement.
     */
    public function recordFailure(string $error): void
    {
        $status = $this->getStatus();
        $this->updateStatus(
            'error',
            $this->pendingCount(),
            $status['processed'],
            $status['failed'] + 1,
            $status['last_processed'],
            $error
        );
    }

    /**
     * Load the enhancement queue from disk.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadQueue(): array
    {
        if (! file_exists($this->queuePath)) {
            return [];
        }

        $content = file_get_contents($this->queuePath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Save the enhancement queue to disk.
     *
     * @param  array<int, array<string, mixed>>  $queue
     */
    private function saveQueue(array $queue): void
    {
        $dir = dirname($this->queuePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->queuePath, json_encode($queue, JSON_PRETTY_PRINT));
    }

    /**
     * Update the enhancement status file.
     */
    private function updateStatus(
        string $status,
        int $pending,
        int $processed = 0,
        int $failed = 0,
        ?string $lastProcessed = null,
        ?string $lastError = null
    ): void {
        $data = [
            'status' => $status,
            'pending' => $pending,
            'processed' => $processed,
            'failed' => $failed,
            'last_processed' => $lastProcessed,
            'last_error' => $lastError,
        ];

        $dir = dirname($this->statusPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->statusPath, json_encode($data, JSON_PRETTY_PRINT));
    }
}
