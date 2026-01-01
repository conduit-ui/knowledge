<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LaravelZero\Framework\Commands\Command;

class SyncCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sync
                            {--pull : Pull entries from cloud only}
                            {--push : Push local entries to cloud only}';

    /**
     * @var string
     */
    protected $description = 'Synchronize knowledge entries with prefrontal-cortex cloud';

    private string $baseUrl = 'https://prefrontal-cortex-master-iw3xyv.laravel.cloud';

    protected ?Client $client = null;

    public function handle(): int
    {
        $pull = $this->option('pull');
        $push = $this->option('push');

        // Validate API token
        $token = env('PREFRONTAL_API_TOKEN');
        if (empty($token)) {
            $this->error('PREFRONTAL_API_TOKEN environment variable is not set.');

            return self::FAILURE;
        }

        // Default behavior: two-way sync (pull then push)
        if (! $pull && ! $push) {
            $this->info('Starting two-way sync (pull then push)...');
            $pullResult = $this->pullFromCloud($token);
            $pushResult = $this->pushToCloud($token);

            $this->displaySummary($pullResult, $pushResult);

            return self::SUCCESS;
        }

        // Pull only
        if ($pull) {
            $this->info('Pulling entries from cloud...');
            $result = $this->pullFromCloud($token);
            $this->displayPullSummary($result);

            return self::SUCCESS;
        }

        // Push only
        if ($push) {
            $this->info('Pushing local entries to cloud...');
            $result = $this->pushToCloud($token);
            $this->displayPushSummary($result);
        }

        return self::SUCCESS;
    }

    /**
     * Get or create HTTP client.
     */
    protected function getClient(): Client
    {
        if ($this->client === null) {
            // Use container-bound client if available (for testing)
            $this->client = app()->bound(Client::class)
                ? app(Client::class)
                : $this->createClient();
        }

        return $this->client;
    }

    /**
     * Create a new HTTP client instance.
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
    private function pullFromCloud(string $token): array
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

            if ($response->getStatusCode() !== 200) {
                $this->error('Failed to pull from cloud: HTTP '.$response->getStatusCode());

                return compact('created', 'updated', 'failed');
            }

            $responseData = json_decode((string) $response->getBody(), true);

            if (! is_array($responseData) || ! isset($responseData['data'])) {
                $this->error('Invalid response from cloud API.');

                return compact('created', 'updated', 'failed');
            }

            $entries = $responseData['data'];

            $bar = $this->output->createProgressBar(count($entries));
            $bar->start();

            foreach ($entries as $entryData) {
                try {
                    // Check if entry exists by unique_id
                    $uniqueId = $entryData['unique_id'] ?? null;

                    if ($uniqueId === null) {
                        $failed++;
                        $bar->advance();
                        continue;
                    }

                    // For now, we'll match by title as we don't have unique_id field locally yet
                    // This will be improved when we add unique_id to the local schema
                    $existingEntry = Entry::where('title', $entryData['title'] ?? '')->first();

                    if ($existingEntry) {
                        $existingEntry->update([
                            'content' => $entryData['content'] ?? $existingEntry->content,
                            'category' => $entryData['category'] ?? $existingEntry->category,
                            'tags' => $entryData['tags'] ?? $existingEntry->tags,
                            'module' => $entryData['module'] ?? $existingEntry->module,
                            'priority' => $entryData['priority'] ?? $existingEntry->priority,
                            'confidence' => $entryData['confidence'] ?? $existingEntry->confidence,
                            'source' => $entryData['source'] ?? $existingEntry->source,
                            'ticket' => $entryData['ticket'] ?? $existingEntry->ticket,
                            'status' => $entryData['status'] ?? $existingEntry->status,
                        ]);
                        $updated++;
                    } else {
                        Entry::create([
                            'title' => $entryData['title'] ?? 'Untitled',
                            'content' => $entryData['content'] ?? '',
                            'category' => $entryData['category'] ?? null,
                            'tags' => $entryData['tags'] ?? null,
                            'module' => $entryData['module'] ?? null,
                            'priority' => $entryData['priority'] ?? 'medium',
                            'confidence' => $entryData['confidence'] ?? 50,
                            'source' => $entryData['source'] ?? null,
                            'ticket' => $entryData['ticket'] ?? null,
                            'status' => $entryData['status'] ?? 'draft',
                        ]);
                        $created++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (GuzzleException $e) {
            $this->error('Failed to pull from cloud: '.$e->getMessage());
            $failed++;
        }

        return compact('created', 'updated', 'failed');
    }

    /**
     * Push local entries to cloud.
     *
     * @return array{sent: int, created: int, updated: int, failed: int}
     */
    private function pushToCloud(string $token): array
    {
        $sent = 0;
        $created = 0;
        $updated = 0;
        $failed = 0;

        try {
            $entries = Entry::all();

            if ($entries->isEmpty()) {
                $this->warn('No local entries to push.');

                return compact('sent', 'created', 'updated', 'failed');
            }

            // Build payload array
            $allPayload = [];
            foreach ($entries as $entry) {
                $uniqueId = $this->generateUniqueId($entry);

                $allPayload[] = [
                    'unique_id' => $uniqueId,
                    'title' => mb_substr((string) $entry->title, 0, 255),
                    'content' => $entry->content,
                    'category' => $entry->category,
                    'tags' => $entry->tags ?? [],
                    'module' => $entry->module,
                    'priority' => $entry->priority,
                    'confidence' => $entry->confidence,
                    'source' => $entry->source,
                    'ticket' => $entry->ticket,
                    'files' => $entry->files ?? [],
                    'repo' => $entry->repo,
                    'branch' => $entry->branch,
                    'commit' => $entry->commit,
                    'author' => $entry->author,
                    'status' => $entry->status,
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

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        } catch (GuzzleException $e) {
            $this->error('Failed to push to cloud: '.$e->getMessage());
            $failed += count($allPayload ?? []);
        }

        return compact('sent', 'created', 'updated', 'failed');
    }

    /**
     * Generate unique ID for an entry.
     */
    private function generateUniqueId(Entry $entry): string
    {
        // Use hash of id and title for uniqueness
        return hash('sha256', $entry->id.'-'.$entry->title);
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
     */
    private function displayPushSummary(array $result): void
    {
        $this->newLine();
        $this->info('=== Push Summary ===');

        $this->table(
            ['Operation', 'Count'],
            [
                ['Sent', $result['sent']],
                ['Created (Cloud)', $result['created']],
                ['Updated (Cloud)', $result['updated']],
                ['Failed', $result['failed']],
            ]
        );
    }
}
