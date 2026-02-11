<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\DeletionTracker;
use App\Services\QdrantService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class SyncCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sync
                            {--pull : Pull entries from cloud only}
                            {--push : Push local entries to cloud only}
                            {--delete : Delete cloud entries that do not exist locally (requires --push)}
                            {--full-sync : Compare local vs cloud and remove orphaned cloud entries}';

    /**
     * @var string
     */
    protected $description = 'Synchronize knowledge entries with prefrontal-cortex cloud';

    private string $baseUrl = '';

    protected ?Client $client = null;

    public function handle(QdrantService $qdrant, DeletionTracker $tracker): int
    {
        $pull = (bool) $this->option('pull');
        $push = (bool) $this->option('push');
        $delete = (bool) $this->option('delete');
        $fullSync = (bool) $this->option('full-sync');

        // Validate --delete requires --push
        if ($delete && ! $push && ! $fullSync) {
            $this->error('The --delete option requires --push or --full-sync to be specified.');

            return self::FAILURE;
        }

        // Validate API token
        $token = config('services.prefrontal.token');
        if (! is_string($token) || $token === '') {
            $this->error('PREFRONTAL_API_TOKEN environment variable is not set.');

            return self::FAILURE;
        }

        // Get API URL from config
        $baseUrl = config('services.prefrontal.url');
        if (! is_string($baseUrl) || $baseUrl === '') {
            $this->error('PREFRONTAL_API_URL environment variable is not set.');

            return self::FAILURE;
        }
        $this->baseUrl = $baseUrl;

        // Full sync mode: push + delete orphans + process tracked deletions
        if ($fullSync) {
            return $this->handleFullSync($token, $qdrant, $tracker);
        }

        // Default behavior: two-way sync (pull then push)
        if (! $pull && ! $push) {
            $this->info('Starting two-way sync (pull then push)...');
            $pullResult = $this->pullFromCloud($token, $qdrant);
            $pushResult = $this->pushToCloud($token, $qdrant);

            $this->displaySummary($pullResult, $pushResult);

            return self::SUCCESS;
        }

        // Pull only
        if ($pull) {
            $this->info('Pulling entries from cloud...');
            $result = $this->pullFromCloud($token, $qdrant);
            $this->displayPullSummary($result);

            return self::SUCCESS;
        }

        // Push only (with optional delete)
        if ($push) {
            $this->info('Pushing local entries to cloud...');
            $result = $this->pushToCloud($token, $qdrant);

            $deleteResult = ['deleted' => 0, 'failed' => 0];
            if ($delete) {
                $this->info('Processing tracked deletions...');
                $trackedResult = $this->processTrackedDeletions($token, $tracker);
                $deleteResult['deleted'] += $trackedResult['deleted'];
                $deleteResult['failed'] += $trackedResult['failed'];

                $this->info('Deleting cloud entries not present locally...');
                $orphanResult = $this->deleteOrphanedCloudEntries($token, $qdrant);
                $deleteResult['deleted'] += $orphanResult['deleted'];
                $deleteResult['failed'] += $orphanResult['failed'];
            }

            $this->displayPushSummary($result, $deleteResult);
        }

        return self::SUCCESS;
    }

    /**
     * Handle full sync: push all entries, process tracked deletions, and remove orphans.
     */
    private function handleFullSync(string $token, QdrantService $qdrant, DeletionTracker $tracker): int
    {
        $this->info('Starting full sync (push + delete orphans)...');

        // Push local entries
        $this->info('Pushing local entries to cloud...');
        $pushResult = $this->pushToCloud($token, $qdrant);

        // Process tracked deletions
        $this->info('Processing tracked deletions...');
        $trackedResult = $this->processTrackedDeletions($token, $tracker);

        // Delete orphaned cloud entries
        $this->info('Comparing local vs cloud to find orphans...');
        $orphanResult = $this->deleteOrphanedCloudEntries($token, $qdrant);

        $deleteResult = [
            'deleted' => $trackedResult['deleted'] + $orphanResult['deleted'],
            'failed' => $trackedResult['failed'] + $orphanResult['failed'],
        ];

        $this->displayPushSummary($pushResult, $deleteResult);

        return self::SUCCESS;
    }

    /**
     * Get or create HTTP client.
     */
    protected function getClient(): Client
    {
        if (! $this->client instanceof \GuzzleHttp\Client) {
            // Use container-bound client if available (for testing)
            // @codeCoverageIgnoreStart
            $this->client = app()->bound(Client::class)
                ? app(Client::class)
                : $this->createClient();
            // @codeCoverageIgnoreEnd
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
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Pull entries from cloud.
     *
     * @return array{created: int, updated: int, failed: int}
     */
    private function pullFromCloud(string $token, QdrantService $qdrant): array
    {
        $created = 0;
        $updated = 0;
        $failed = 0;

        try {
            $response = $this->getClient()->get('/api/knowledge/entries', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            // @codeCoverageIgnoreStart
            if ($response->getStatusCode() !== 200) {
                $this->error('Failed to pull from cloud: HTTP '.$response->getStatusCode());

                return ['created' => $created, 'updated' => $updated, 'failed' => $failed];
            }
            // @codeCoverageIgnoreEnd

            $responseData = json_decode((string) $response->getBody(), true);

            if (! is_array($responseData) || ! isset($responseData['data'])) {
                $this->error('Invalid response from cloud API.');

                return ['created' => $created, 'updated' => $updated, 'failed' => $failed];
            }

            $entries = $responseData['data'];

            $bar = $this->output->createProgressBar(count($entries));
            $bar->start();

            foreach ($entries as $entryData) {
                try {
                    $uniqueId = $entryData['unique_id'] ?? null;

                    if ($uniqueId === null) {
                        $failed++;
                        $bar->advance();

                        continue;
                    }

                    // Search for existing entry by title (best we can do without unique_id in Qdrant)
                    $title = $entryData['title'] ?? '';
                    $existing = $qdrant->search($title, [], 1);
                    $existingEntry = $existing->first();

                    // Prepare entry data for Qdrant
                    $entry = [
                        'id' => $existingEntry['id'] ?? Str::uuid()->toString(),
                        'title' => $entryData['title'] ?? 'Untitled',
                        'content' => $entryData['content'] ?? '',
                        'category' => $entryData['category'] ?? null,
                        'tags' => $entryData['tags'] ?? [],
                        'module' => $entryData['module'] ?? null,
                        'priority' => $entryData['priority'] ?? 'medium',
                        'confidence' => $entryData['confidence'] ?? 50,
                        'status' => $entryData['status'] ?? 'draft',
                        'usage_count' => $existingEntry['usage_count'] ?? 0,
                        'created_at' => $existingEntry['created_at'] ?? now()->toIso8601String(),
                        'updated_at' => now()->toIso8601String(),
                    ];

                    $qdrant->upsert($entry);

                    // @codeCoverageIgnoreStart
                    if ($existingEntry) {
                        $updated++;
                    } else {
                        $created++;
                    }
                    // @codeCoverageIgnoreEnd
                    // @codeCoverageIgnoreStart
                } catch (\Exception) {
                    $failed++;
                }
                // @codeCoverageIgnoreEnd

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (GuzzleException $e) { // @codeCoverageIgnoreStart
            $this->error('Failed to pull from cloud: '.$e->getMessage());
            $failed++;
        } // @codeCoverageIgnoreEnd

        return ['created' => $created, 'updated' => $updated, 'failed' => $failed];
    }

    /**
     * Push local entries to cloud.
     *
     * @return array{sent: int, created: int, updated: int, failed: int}
     */
    private function pushToCloud(string $token, QdrantService $qdrant): array
    {
        $sent = 0;
        $created = 0;
        $updated = 0;
        $failed = 0;

        try {
            // Get all entries from Qdrant
            $entries = $qdrant->search('', [], 10000);

            if ($entries->isEmpty()) {
                $this->warn('No local entries to push.');

                return ['sent' => $sent, 'created' => $created, 'updated' => $updated, 'failed' => $failed];
            }

            // Build payload array
            $allPayload = [];
            foreach ($entries as $entry) {
                $uniqueId = $this->generateUniqueId($entry);

                $allPayload[] = [
                    'unique_id' => $uniqueId,
                    'title' => mb_substr((string) $entry['title'], 0, 255),
                    'content' => $entry['content'],
                    'category' => $entry['category'],
                    'tags' => $entry['tags'] ?? [],
                    'module' => $entry['module'],
                    'priority' => $entry['priority'],
                    'confidence' => $entry['confidence'],
                    'source' => null,
                    'ticket' => null,
                    'files' => [],
                    'repo' => null,
                    'branch' => null,
                    'commit' => null,
                    'author' => null,
                    'status' => $entry['status'],
                ];
            }

            // Send in batches of 100
            $chunks = array_chunk($allPayload, 100);
            $totalChunks = count($chunks);

            $this->info("Sending {$entries->count()} entries in {$totalChunks} batches...");
            $bar = $this->output->createProgressBar($totalChunks);
            $bar->start();

            foreach ($chunks as $chunk) {
                $response = $this->getClient()->post('/api/knowledge/sync', [
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                    ],
                    'json' => ['entries' => $chunk],
                ]);

                // @codeCoverageIgnoreStart
                if ($response->getStatusCode() === 200) {
                    $result = json_decode((string) $response->getBody(), true);
                    if (is_array($result)) {
                        $sent += count($chunk);
                        // Handle both flat and nested response formats
                        $summary = $result['summary'] ?? $result;
                        $created += $summary['created'] ?? 0;
                        $updated += $summary['updated'] ?? 0;
                        $failed += $summary['failed'] ?? 0;
                    }
                } else {
                    $failed += count($chunk);
                }
                // @codeCoverageIgnoreEnd

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (GuzzleException $e) { // @codeCoverageIgnoreStart
            $this->error('Failed to push to cloud: '.$e->getMessage());
            $failed += count($allPayload ?? []);
        } // @codeCoverageIgnoreEnd

        return ['sent' => $sent, 'created' => $created, 'updated' => $updated, 'failed' => $failed];
    }

    /**
     * Process tracked deletions from the DeletionTracker.
     *
     * @return array{deleted: int, failed: int}
     */
    private function processTrackedDeletions(string $token, DeletionTracker $tracker): array
    {
        $deleted = 0;
        $failed = 0;

        $trackedDeletions = $tracker->all();

        if ($trackedDeletions === []) {
            $this->info('No tracked deletions to process.');

            return ['deleted' => $deleted, 'failed' => $failed];
        }

        $this->info('Found '.count($trackedDeletions).' tracked deletions to propagate.');

        // We need to find the cloud IDs for these unique_ids
        try {
            $response = $this->getClient()->get('/api/knowledge/entries', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            // @codeCoverageIgnoreStart
            if ($response->getStatusCode() !== 200) {
                $this->error('Failed to fetch cloud entries for deletion propagation.');

                return ['deleted' => $deleted, 'failed' => count($trackedDeletions)];
            }
            // @codeCoverageIgnoreEnd

            $responseData = json_decode((string) $response->getBody(), true);

            if (! is_array($responseData) || ! isset($responseData['data'])) {
                $this->error('Invalid response from cloud API.');

                return ['deleted' => $deleted, 'failed' => count($trackedDeletions)];
            }

            // Build map of cloud unique_ids to their cloud IDs
            $cloudIdMap = [];
            foreach ($responseData['data'] as $entry) {
                if (isset($entry['unique_id']) && isset($entry['id'])) {
                    $cloudIdMap[$entry['unique_id']] = $entry['id'];
                }
            }

            $successfulDeletions = [];

            foreach (array_keys($trackedDeletions) as $uniqueId) {
                if (! isset($cloudIdMap[$uniqueId])) {
                    // Entry doesn't exist in cloud, remove from tracker
                    $successfulDeletions[] = $uniqueId;

                    continue;
                }

                $cloudId = $cloudIdMap[$uniqueId];

                try {
                    $this->getClient()->delete("/api/knowledge/{$cloudId}", [
                        'headers' => [
                            'Authorization' => "Bearer {$token}",
                        ],
                    ]);

                    $deleted++;
                    $successfulDeletions[] = $uniqueId;
                    // @codeCoverageIgnoreStart
                } catch (GuzzleException) {
                    $failed++;
                }
                // @codeCoverageIgnoreEnd
            }

            // Clear successfully propagated deletions from tracker
            if ($successfulDeletions !== []) {
                $tracker->removeMany($successfulDeletions);
            }
        } catch (GuzzleException $e) { // @codeCoverageIgnoreStart
            $this->error('Failed to process tracked deletions: '.$e->getMessage());
            $failed += count($trackedDeletions);
        } // @codeCoverageIgnoreEnd

        return ['deleted' => $deleted, 'failed' => $failed];
    }

    /**
     * Generate unique ID for an entry.
     *
     * @param  array<string, mixed>  $entry
     */
    private function generateUniqueId(array $entry): string
    {
        // Use hash of id and title for uniqueness
        return hash('sha256', $entry['id'].'-'.$entry['title']);
    }

    /**
     * Display summary for two-way sync.
     *
     * @param  array{created: int, updated: int, failed: int}  $pullResult
     * @param  array{sent: int, created: int, updated: int, failed: int}  $pushResult
     */
    private function displaySummary(array $pullResult, array $pushResult): void
    {
        $this->newLine();
        $this->info('=== Sync Summary ===');

        $this->table(
            ['Direction', 'Operation', 'Count'],
            [
                ['Pull', 'Created', $pullResult['created']],
                ['Pull', 'Updated', $pullResult['updated']],
                ['Pull', 'Failed', $pullResult['failed']],
                ['Push', 'Sent', $pushResult['sent']],
                ['Push', 'Created (Cloud)', $pushResult['created']],
                ['Push', 'Updated (Cloud)', $pushResult['updated']],
                ['Push', 'Failed', $pushResult['failed']],
            ]
        );
    }

    /**
     * Display summary for pull-only operation.
     *
     * @param  array{created: int, updated: int, failed: int}  $result
     */
    private function displayPullSummary(array $result): void
    {
        $this->newLine();
        $this->info('=== Pull Summary ===');

        $this->table(
            ['Operation', 'Count'],
            [
                ['Created', $result['created']],
                ['Updated', $result['updated']],
                ['Failed', $result['failed']],
            ]
        );
    }

    /**
     * Display summary for push-only operation.
     *
     * @param  array{sent: int, created: int, updated: int, failed: int}  $result
     * @param  array{deleted: int, failed: int}  $deleteResult
     */
    private function displayPushSummary(array $result, array $deleteResult = ['deleted' => 0, 'failed' => 0]): void
    {
        $this->newLine();
        $this->info('=== Push Summary ===');

        $rows = [
            ['Sent', $result['sent']],
            ['Created (Cloud)', $result['created']],
            ['Updated (Cloud)', $result['updated']],
            ['Failed', $result['failed']],
        ];

        // Add delete stats if any deletions were attempted
        if ($deleteResult['deleted'] > 0 || $deleteResult['failed'] > 0) {
            $rows[] = ['Deleted (Cloud)', $deleteResult['deleted']];
            $rows[] = ['Delete Failed', $deleteResult['failed']];
        }

        $this->table(
            ['Operation', 'Count'],
            $rows
        );
    }

    /**
     * Delete cloud entries that do not exist locally.
     *
     * @return array{deleted: int, failed: int}
     */
    private function deleteOrphanedCloudEntries(string $token, QdrantService $qdrant): array
    {
        $deleted = 0;
        $failed = 0;

        try {
            // Get all cloud entries
            $response = $this->getClient()->get('/api/knowledge/entries', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            // @codeCoverageIgnoreStart
            if ($response->getStatusCode() !== 200) {
                $this->error('Failed to fetch cloud entries: HTTP '.$response->getStatusCode());

                return ['deleted' => $deleted, 'failed' => $failed];
            }
            // @codeCoverageIgnoreEnd

            $responseData = json_decode((string) $response->getBody(), true);

            if (! is_array($responseData) || ! isset($responseData['data'])) {
                $this->error('Invalid response from cloud API.');

                return ['deleted' => $deleted, 'failed' => $failed];
            }

            $cloudEntries = $responseData['data'];

            // Build map of cloud unique_ids to their cloud IDs
            $cloudIdMap = [];
            foreach ($cloudEntries as $entry) {
                if (isset($entry['unique_id']) && isset($entry['id'])) {
                    $cloudIdMap[$entry['unique_id']] = $entry['id'];
                }
            }

            if ($cloudIdMap === []) {
                $this->info('No cloud entries to process.');

                return ['deleted' => $deleted, 'failed' => $failed];
            }

            // Get all local entries and build unique_id set
            $localEntries = $qdrant->search('', [], 10000);
            $localUniqueIds = [];
            foreach ($localEntries as $entry) {
                $localUniqueIds[] = $this->generateUniqueId($entry);
            }

            // Find cloud entries that don't exist locally
            $toDelete = [];
            foreach ($cloudIdMap as $uniqueId => $cloudId) {
                if (! in_array($uniqueId, $localUniqueIds, true)) {
                    $toDelete[] = $cloudId;
                }
            }

            if ($toDelete === []) {
                $this->info('No orphaned cloud entries to delete.');

                return ['deleted' => $deleted, 'failed' => $failed];
            }

            $this->info('Found '.count($toDelete).' orphaned cloud entries to delete.');
            $bar = $this->output->createProgressBar(count($toDelete));
            $bar->start();

            // Delete each orphaned entry
            foreach ($toDelete as $cloudId) {
                try {
                    $deleteResponse = $this->getClient()->delete("/api/knowledge/{$cloudId}", [
                        'headers' => [
                            'Authorization' => "Bearer {$token}",
                        ],
                    ]);

                    // @codeCoverageIgnoreStart
                    if ($deleteResponse->getStatusCode() >= 200 && $deleteResponse->getStatusCode() < 300) {
                        $deleted++;
                    } else {
                        $failed++;
                    }
                    // @codeCoverageIgnoreEnd
                    // @codeCoverageIgnoreStart
                } catch (GuzzleException) {
                    $failed++;
                }
                // @codeCoverageIgnoreEnd

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (GuzzleException $e) { // @codeCoverageIgnoreStart
            $this->error('Failed to delete orphaned entries: '.$e->getMessage());
        } // @codeCoverageIgnoreEnd

        return ['deleted' => $deleted, 'failed' => $failed];
    }
}
