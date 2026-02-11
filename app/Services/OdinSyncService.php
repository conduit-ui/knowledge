<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class OdinSyncService
{
    private readonly string $queuePath;

    private readonly string $statusPath;

    protected ?Client $client = null;

    public function __construct(
        private readonly KnowledgePathService $pathService,
    ) {
        $knowledgeDir = $this->pathService->getKnowledgeDirectory();
        $this->queuePath = $knowledgeDir.'/sync_queue.json';
        $this->statusPath = $knowledgeDir.'/sync_status.json';
    }

    /**
     * Check if Odin sync is enabled in configuration.
     */
    public function isEnabled(): bool
    {
        return (bool) config('services.odin.enabled', true);
    }

    /**
     * Check connectivity to Odin server.
     */
    public function isAvailable(): bool
    {
        $url = $this->getBaseUrl();
        $token = $this->getToken();

        if ($url === '' || $token === '') {
            return false;
        }

        try {
            $response = $this->getClient()->get('/api/knowledge/entries', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ],
                'query' => ['per_page' => 1],
                'timeout' => 5,
            ]);

            return $response->getStatusCode() === 200;
        } catch (GuzzleException) {
            return false;
        }
    }

    /**
     * Queue an entry for sync to Odin.
     *
     * @param  array<string, mixed>  $entry
     */
    public function queueForSync(array $entry, string $operation = 'upsert', string $project = 'default'): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $queue = $this->loadQueue();

        $queue[] = [
            'operation' => $operation,
            'project' => $project,
            'entry' => $entry,
            'queued_at' => now()->toIso8601String(),
        ];

        $this->saveQueue($queue);
    }

    /**
     * Process the sync queue, pushing pending items to Odin.
     *
     * @return array{synced: int, failed: int, remaining: int}
     */
    public function processQueue(): array
    {
        $queue = $this->loadQueue();
        $synced = 0;
        $failed = 0;

        if ($queue === []) {
            $this->updateStatus('idle', 0);

            return ['synced' => 0, 'failed' => 0, 'remaining' => 0];
        }

        $token = $this->getToken();
        if ($token === '') {
            return ['synced' => 0, 'failed' => 0, 'remaining' => count($queue)];
        }

        $remaining = [];
        $batchSize = max(1, (int) config('services.odin.batch_size', 50));
        $batches = array_chunk($queue, $batchSize);

        foreach ($batches as $batch) {
            $upserts = [];
            $deletes = [];

            foreach ($batch as $item) {
                if ($item['operation'] === 'delete') {
                    $deletes[] = $item;
                } else {
                    $upserts[] = $item;
                }
            }

            if ($upserts !== []) {
                $result = $this->pushBatch($token, $upserts);
                $synced += $result['synced'];
                $failed += $result['failed'];
                foreach ($result['failedItems'] as $failedItem) {
                    $remaining[] = $failedItem;
                }
            }

            if ($deletes !== []) {
                $result = $this->deleteBatch($token, $deletes);
                $synced += $result['synced'];
                $failed += $result['failed'];
                foreach ($result['failedItems'] as $failedItem) {
                    $remaining[] = $failedItem;
                }
            }
        }

        $this->saveQueue($remaining);
        $this->updateStatus($remaining === [] ? 'synced' : 'partial', count($remaining));

        return ['synced' => $synced, 'failed' => $failed, 'remaining' => count($remaining)];
    }

    /**
     * Pull fresh entries from Odin for a project.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pullFromOdin(string $project = 'default'): array
    {
        $token = $this->getToken();
        if ($token === '') {
            return [];
        }

        try {
            $response = $this->getClient()->get('/api/knowledge/entries', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ],
                'query' => ['project' => $project],
            ]);

            if ($response->getStatusCode() !== 200) { // @codeCoverageIgnoreStart
                return [];
            } // @codeCoverageIgnoreEnd

            $data = json_decode((string) $response->getBody(), true);
            if (! is_array($data) || ! isset($data['data'])) {
                return [];
            }

            /** @var array<int, array<string, mixed>> */
            return $data['data'];
        } catch (GuzzleException) {
            return [];
        }
    }

    /**
     * List all projects that have been synced to Odin.
     *
     * @return array<int, array{name: string, entry_count: int, last_synced: string|null}>
     */
    public function listProjects(): array
    {
        $token = $this->getToken();
        if ($token === '') {
            return [];
        }

        try {
            $response = $this->getClient()->get('/api/knowledge/projects', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() !== 200) { // @codeCoverageIgnoreStart
                return [];
            } // @codeCoverageIgnoreEnd

            $data = json_decode((string) $response->getBody(), true);
            if (! is_array($data) || ! isset($data['data'])) {
                return [];
            }

            /** @var array<int, array{name: string, entry_count: int, last_synced: string|null}> */
            return $data['data'];
        } catch (GuzzleException) {
            return [];
        }
    }

    /**
     * Get the current sync status.
     *
     * @return array{status: string, pending: int, last_synced: string|null, last_error: string|null}
     */
    public function getStatus(): array
    {
        $default = [
            'status' => 'idle',
            'pending' => 0,
            'last_synced' => null,
            'last_error' => null,
        ];

        if (! file_exists($this->statusPath)) {
            $queue = $this->loadQueue();
            $default['pending'] = count($queue);
            $default['status'] = $queue !== [] ? 'pending' : 'idle';

            return $default;
        }

        $content = file_get_contents($this->statusPath);
        if ($content === false) { // @codeCoverageIgnoreStart
            return $default;
        } // @codeCoverageIgnoreEnd

        $status = json_decode($content, true);
        if (! is_array($status)) {
            return $default;
        }

        // Always refresh the pending count from the actual queue
        $queue = $this->loadQueue();
        $status['pending'] = count($queue);
        if ($queue !== [] && $status['status'] === 'idle') {
            $status['status'] = 'pending';
        }

        /** @var int $pendingCount */
        $pendingCount = $status['pending'];

        return [
            'status' => $status['status'] ?? 'idle',
            'pending' => $pendingCount,
            'last_synced' => $status['last_synced'] ?? null,
            'last_error' => $status['last_error'] ?? null,
        ];
    }

    /**
     * Get the pending queue count.
     */
    public function getPendingCount(): int
    {
        return count($this->loadQueue());
    }

    /**
     * Resolve conflicts using last-write-wins strategy.
     *
     * @param  array<string, mixed>  $local
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>
     */
    public function resolveConflict(array $local, array $remote): array
    {
        $localTime = $local['updated_at'] ?? '';
        $remoteTime = $remote['updated_at'] ?? '';

        if ($localTime === '' && $remoteTime === '') {
            return $local;
        }

        if ($localTime === '') {
            return $remote;
        }

        if ($remoteTime === '') {
            return $local;
        }

        return strtotime($localTime) >= strtotime($remoteTime) ? $local : $remote;
    }

    /**
     * Clear the sync queue.
     */
    public function clearQueue(): void
    {
        $this->saveQueue([]);
        $this->updateStatus('idle', 0);
    }

    /**
     * Push a batch of entries to Odin.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{synced: int, failed: int, failedItems: array<int, array<string, mixed>>}
     */
    private function pushBatch(string $token, array $items): array
    {
        $synced = 0;
        $failed = 0;
        $failedItems = [];

        $payload = [];
        foreach ($items as $item) {
            $entry = $item['entry'];
            $payload[] = [
                'unique_id' => $this->generateUniqueId($entry),
                'title' => mb_substr((string) ($entry['title'] ?? ''), 0, 255),
                'content' => $entry['content'] ?? '',
                'category' => $entry['category'] ?? null,
                'tags' => $entry['tags'] ?? [],
                'module' => $entry['module'] ?? null,
                'priority' => $entry['priority'] ?? null,
                'confidence' => $entry['confidence'] ?? 0,
                'status' => $entry['status'] ?? null,
                'project' => $item['project'] ?? 'default',
                'source' => null,
                'ticket' => null,
                'files' => [],
                'repo' => null,
                'branch' => null,
                'commit' => null,
                'author' => null,
            ];
        }

        try {
            $response = $this->getClient()->post('/api/knowledge/sync', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => ['entries' => $payload],
            ]);

            if ($response->getStatusCode() === 200) {
                $synced = count($items);
                $this->updateStatus('synced', 0, now()->toIso8601String());
            } else { // @codeCoverageIgnoreStart
                $failed = count($items);
                $failedItems = $items;
                $this->updateStatus('error', count($items), null, 'HTTP '.$response->getStatusCode());
            } // @codeCoverageIgnoreEnd
        } catch (GuzzleException $e) {
            $failed = count($items);
            $failedItems = $items;
            $this->updateStatus('error', count($items), null, $e->getMessage());
        }

        return ['synced' => $synced, 'failed' => $failed, 'failedItems' => $failedItems];
    }

    /**
     * Delete a batch of entries from Odin.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{synced: int, failed: int, failedItems: array<int, array<string, mixed>>}
     */
    private function deleteBatch(string $token, array $items): array
    {
        $synced = 0;
        $failed = 0;
        $failedItems = [];

        foreach ($items as $item) {
            $entry = $item['entry'];
            $uniqueId = $this->generateUniqueId($entry);

            try {
                $response = $this->getClient()->delete("/api/knowledge/by-unique-id/{$uniqueId}", [
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                        'Accept' => 'application/json',
                    ],
                ]);

                if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                    $synced++;
                } else { // @codeCoverageIgnoreStart
                    $failed++;
                    $failedItems[] = $item;
                } // @codeCoverageIgnoreEnd
            } catch (GuzzleException) {
                $failed++;
                $failedItems[] = $item;
            }
        }

        return ['synced' => $synced, 'failed' => $failed, 'failedItems' => $failedItems];
    }

    /**
     * Generate unique ID for an entry.
     *
     * @param  array<string, mixed>  $entry
     */
    private function generateUniqueId(array $entry): string
    {
        return hash('sha256', ($entry['id'] ?? '').'-'.($entry['title'] ?? ''));
    }

    /**
     * Load the sync queue from disk.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadQueue(): array
    {
        if (! file_exists($this->queuePath)) {
            return [];
        }

        $content = file_get_contents($this->queuePath);
        if ($content === false) { // @codeCoverageIgnoreStart
            return [];
        } // @codeCoverageIgnoreEnd

        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Save the sync queue to disk.
     *
     * @param  array<int, array<string, mixed>>  $queue
     */
    private function saveQueue(array $queue): void
    {
        $dir = dirname($this->queuePath);
        if (! is_dir($dir)) { // @codeCoverageIgnoreStart
            mkdir($dir, 0755, true);
        } // @codeCoverageIgnoreEnd

        file_put_contents($this->queuePath, json_encode($queue, JSON_PRETTY_PRINT));
    }

    /**
     * Update the sync status file.
     */
    private function updateStatus(string $status, int $pending, ?string $lastSynced = null, ?string $lastError = null): void
    {
        $current = $this->getStatus();

        $data = [
            'status' => $status,
            'pending' => $pending,
            'last_synced' => $lastSynced ?? $current['last_synced'],
            'last_error' => $lastError,
        ];

        $dir = dirname($this->statusPath);
        if (! is_dir($dir)) { // @codeCoverageIgnoreStart
            mkdir($dir, 0755, true);
        } // @codeCoverageIgnoreEnd

        file_put_contents($this->statusPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Get the Odin API base URL.
     */
    private function getBaseUrl(): string
    {
        $url = config('services.odin.url', '');

        return is_string($url) ? $url : '';
    }

    /**
     * Get the Odin API token.
     */
    private function getToken(): string
    {
        $token = config('services.odin.token', '');

        return is_string($token) ? $token : '';
    }

    /**
     * Get or create HTTP client.
     */
    protected function getClient(): Client
    {
        if (! $this->client instanceof Client) {
            $this->client = app()->bound(Client::class)
                ? app(Client::class)
                : $this->createClient(); // @codeCoverageIgnore
        }

        return $this->client;
    }

    /**
     * Create a new HTTP client instance.
     *
     * @codeCoverageIgnore HTTP client factory - tested via integration
     */
    protected function createClient(): Client
    {
        return new Client([
            'base_uri' => $this->getBaseUrl(),
            'timeout' => (int) config('services.odin.timeout', 10),
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }
}
