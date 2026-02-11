<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\DeletionTracker;
use App\Services\QdrantService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LaravelZero\Framework\Commands\Command;

class SyncPurgeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sync:purge
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--tracked-only : Only purge entries tracked in the deletion log}';

    /**
     * @var string
     */
    protected $description = 'Delete cloud entries that don\'t exist locally';

    private string $baseUrl = '';

    protected ?Client $client = null;

    public function handle(QdrantService $qdrant, DeletionTracker $tracker): int
    {
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

        $dryRun = (bool) $this->option('dry-run');
        $trackedOnly = (bool) $this->option('tracked-only');

        if ($trackedOnly) {
            return $this->purgeTrackedDeletions($token, $tracker, $dryRun);
        }

        return $this->purgeOrphanedEntries($token, $qdrant, $tracker, $dryRun);
    }

    /**
     * Get or create HTTP client.
     */
    protected function getClient(): Client
    {
        if (! $this->client instanceof \GuzzleHttp\Client) {
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
     * Purge only entries tracked in the deletion log.
     */
    private function purgeTrackedDeletions(string $token, DeletionTracker $tracker, bool $dryRun): int
    {
        $trackedDeletions = $tracker->all();

        if ($trackedDeletions === []) {
            $this->info('No tracked deletions to purge.');

            return self::SUCCESS;
        }

        $this->info('Found '.count($trackedDeletions).' tracked deletions.');

        if ($dryRun) {
            $this->warn('[DRY RUN] Would purge '.count($trackedDeletions).' tracked entries from cloud.');

            return self::SUCCESS;
        }

        try {
            // Fetch cloud entries to map unique_ids to cloud IDs
            $response = $this->getClient()->get('/api/knowledge/entries', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            $responseData = json_decode((string) $response->getBody(), true);

            if (! is_array($responseData) || ! isset($responseData['data'])) {
                $this->error('Invalid response from cloud API.');

                return self::FAILURE;
            }

            $cloudIdMap = [];
            foreach ($responseData['data'] as $entry) {
                if (isset($entry['unique_id']) && isset($entry['id'])) {
                    $cloudIdMap[$entry['unique_id']] = $entry['id'];
                }
            }

            $deleted = 0;
            $failed = 0;
            $successfulDeletions = [];

            foreach (array_keys($trackedDeletions) as $uniqueId) {
                if (! isset($cloudIdMap[$uniqueId])) {
                    $successfulDeletions[] = $uniqueId;

                    continue;
                }

                try {
                    $this->getClient()->delete("/api/knowledge/{$cloudIdMap[$uniqueId]}", [
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

            if ($successfulDeletions !== []) {
                $tracker->removeMany($successfulDeletions);
            }

            $this->info("Purged {$deleted} entries from cloud. Failed: {$failed}.");
        } catch (GuzzleException $e) { // @codeCoverageIgnoreStart
            $this->error('Failed to purge tracked deletions: '.$e->getMessage());

            return self::FAILURE;
        } // @codeCoverageIgnoreEnd

        return self::SUCCESS;
    }

    /**
     * Purge orphaned cloud entries (compare local vs cloud).
     */
    private function purgeOrphanedEntries(string $token, QdrantService $qdrant, DeletionTracker $tracker, bool $dryRun): int
    {
        $this->info('Comparing local entries with cloud...');

        try {
            // Get cloud entries
            $response = $this->getClient()->get('/api/knowledge/entries', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
            ]);

            $responseData = json_decode((string) $response->getBody(), true);

            if (! is_array($responseData) || ! isset($responseData['data'])) {
                $this->error('Invalid response from cloud API.');

                return self::FAILURE;
            }

            $cloudEntries = $responseData['data'];

            // Build cloud ID map
            $cloudIdMap = [];
            foreach ($cloudEntries as $entry) {
                if (isset($entry['unique_id']) && isset($entry['id'])) {
                    $cloudIdMap[$entry['unique_id']] = [
                        'id' => $entry['id'],
                        'title' => $entry['title'] ?? 'Unknown',
                    ];
                }
            }

            if ($cloudIdMap === []) {
                $this->info('No cloud entries found.');

                return self::SUCCESS;
            }

            // Get local entries
            $localEntries = $qdrant->search('', [], 10000);
            $localUniqueIds = [];
            foreach ($localEntries as $entry) {
                $localUniqueIds[] = hash('sha256', $entry['id'].'-'.$entry['title']);
            }

            // Also include tracked deletions as "to delete"
            $trackedDeletionIds = $tracker->getDeletedIds();

            // Find orphans (in cloud but not in local, or tracked for deletion)
            $toDelete = [];
            foreach ($cloudIdMap as $uniqueId => $cloudEntry) {
                $isOrphan = ! in_array($uniqueId, $localUniqueIds, true);
                $isTracked = in_array($uniqueId, $trackedDeletionIds, true);

                if ($isOrphan || $isTracked) {
                    $toDelete[$uniqueId] = $cloudEntry;
                }
            }

            if ($toDelete === []) {
                $this->info('No orphaned cloud entries found. Everything is in sync.');

                return self::SUCCESS;
            }

            $this->info('Found '.count($toDelete).' orphaned cloud entries.');

            if ($dryRun) {
                $this->warn('[DRY RUN] Would delete the following cloud entries:');
                $rows = [];
                foreach ($toDelete as $entry) {
                    $rows[] = [$entry['id'], $entry['title']];
                }
                $this->table(['Cloud ID', 'Title'], $rows);

                return self::SUCCESS;
            }

            $deleted = 0;
            $failed = 0;
            $bar = $this->output->createProgressBar(count($toDelete));
            $bar->start();

            $purgedUniqueIds = [];

            foreach ($toDelete as $uniqueId => $cloudEntry) {
                try {
                    $this->getClient()->delete("/api/knowledge/{$cloudEntry['id']}", [
                        'headers' => [
                            'Authorization' => "Bearer {$token}",
                        ],
                    ]);

                    $deleted++;
                    $purgedUniqueIds[] = $uniqueId;
                    // @codeCoverageIgnoreStart
                } catch (GuzzleException) {
                    $failed++;
                }
                // @codeCoverageIgnoreEnd

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();

            // Clear purged entries from deletion tracker
            if ($purgedUniqueIds !== []) {
                $tracker->removeMany($purgedUniqueIds);
            }

            $this->info("Purged {$deleted} orphaned entries from cloud. Failed: {$failed}.");
        } catch (GuzzleException $e) { // @codeCoverageIgnoreStart
            $this->error('Failed to purge orphaned entries: '.$e->getMessage());

            return self::FAILURE;
        } // @codeCoverageIgnoreEnd

        return self::SUCCESS;
    }
}
