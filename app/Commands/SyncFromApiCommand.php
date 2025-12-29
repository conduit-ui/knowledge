<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use LaravelZero\Framework\Commands\Command;

class SyncFromApiCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sync
                            {--from= : API URL to sync from (defaults to prefrontal-cortex)}
                            {--full : Perform full sync, ignoring last sync timestamp}';

    /**
     * @var string
     */
    protected $description = 'Sync knowledge entries from prefrontal-cortex API';

    private const DEFAULT_API_URL = 'https://prefrontal-cortex.laravel.cloud/api/knowledge/all';

    private const CACHE_KEY_LAST_SYNC = 'knowledge.last_sync_timestamp';

    public function handle(): int
    {
        $apiUrl = $this->option('from') ?? self::DEFAULT_API_URL;
        $fullSync = $this->option('full') ?? false;

        // Validate API token
        $apiToken = env('PREFRONTAL_API_TOKEN');
        if ($apiToken === null || $apiToken === '') {
            $this->error('PREFRONTAL_API_TOKEN environment variable is not set.');
            $this->line('Please add PREFRONTAL_API_TOKEN to your .env file.');

            return self::FAILURE;
        }

        $this->info('Starting sync from: '.$apiUrl);

        // Get last sync timestamp
        $lastSync = null;
        if (! $fullSync) {
            $lastSync = Cache::get(self::CACHE_KEY_LAST_SYNC);
            if ($lastSync !== null) {
                $this->line('Last sync: '.$lastSync);
            }
        }

        // Fetch from API
        try {
            $entries = $this->fetchFromApi($apiUrl, $apiToken, $lastSync);
        } catch (\Exception $e) {
            $this->error('Failed to fetch from API: '.$e->getMessage());

            return self::FAILURE;
        }

        if (count($entries) === 0) {
            $this->info('No new entries to sync.');

            return self::SUCCESS;
        }

        $this->info('Found '.count($entries).' entries to sync.');

        // Process each entry
        $created = 0;
        $updated = 0;
        $failed = 0;

        $progressBar = $this->output->createProgressBar(count($entries));
        $progressBar->start();

        foreach ($entries as $apiEntry) {
            try {
                $result = $this->processEntry($apiEntry);
                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'updated') {
                    $updated++;
                }
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->warn('Failed to process entry: '.$e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Update last sync timestamp
        Cache::forever(self::CACHE_KEY_LAST_SYNC, now()->toIso8601String());

        // Display summary
        $this->info('Sync completed successfully!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Created', $created],
                ['Updated', $updated],
                ['Failed', $failed],
                ['Total', count($entries)],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Fetch entries from the API.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchFromApi(string $apiUrl, string $apiToken, ?string $lastSync): array
    {
        $client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);

        try {
            // Build query parameters
            $queryParams = [];
            if ($lastSync !== null) {
                $queryParams['since'] = $lastSync;
            }

            $response = $client->request('GET', $apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer '.$apiToken,
                    'Accept' => 'application/json',
                ],
                'query' => $queryParams,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('API returned status code: '.$response->getStatusCode());
            }

            $data = json_decode((string) $response->getBody(), true);

            if (! is_array($data)) {
                throw new \RuntimeException('Invalid API response format');
            }

            // Support both direct array and data-wrapped response
            if (isset($data['data']) && is_array($data['data'])) {
                return $data['data'];
            }

            return $data;
        } catch (GuzzleException $e) {
            throw new \RuntimeException('HTTP request failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Process a single entry from the API.
     *
     * @param  array<string, mixed>  $apiEntry
     */
    private function processEntry(array $apiEntry): string
    {
        // Map API response to Entry fields
        $title = $apiEntry['title'] ?? 'Untitled';
        $content = $apiEntry['content'] ?? '';
        $type = $apiEntry['type'] ?? null;
        $tags = $apiEntry['tags'] ?? null;
        $url = $apiEntry['url'] ?? null;
        $repo = $apiEntry['repo'] ?? null;

        // Validate required fields
        if ($content === '') {
            throw new \RuntimeException('Entry missing required content field');
        }

        // Prepare entry data
        $entryData = [
            'title' => $title,
            'content' => $content,
            'category' => 'github',
            'source' => $url,
            'module' => $repo,
            'priority' => 'medium',
            'confidence' => 70,
            'status' => 'draft',
        ];

        // Handle tags (convert to array if string)
        if (is_string($tags)) {
            $entryData['tags'] = array_map('trim', explode(',', $tags));
        } elseif (is_array($tags)) {
            $entryData['tags'] = $tags;
        }

        // Check if entry already exists (using source URL as unique identifier)
        $existing = null;
        if ($url !== null) {
            $existing = Entry::where('source', $url)->first();
        }

        if ($existing !== null) {
            // Update existing entry
            $existing->update($entryData);

            return 'updated';
        }

        // Create new entry (Entry model events will auto-index to ChromaDB)
        Entry::create($entryData);

        return 'created';
    }
}
