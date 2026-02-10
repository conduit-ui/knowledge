<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\OdinSyncService;
use App\Services\QdrantService;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class OdinSyncCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sync:odin
                            {--push : Push queued entries to Odin}
                            {--pull : Pull entries from Odin}
                            {--status : Show sync status only}
                            {--clear : Clear the sync queue}
                            {--project=default : Project namespace to sync}';

    /**
     * @var string
     */
    protected $description = 'Synchronize knowledge with Odin centralized server';

    public function handle(OdinSyncService $odinSync, QdrantService $qdrant): int
    {
        if (! $odinSync->isEnabled()) {
            $this->error('Odin sync is disabled. Set ODIN_SYNC_ENABLED=true to enable.');

            return self::FAILURE;
        }

        // Status-only mode
        if ((bool) $this->option('status')) {
            $this->displayStatus($odinSync);

            return self::SUCCESS;
        }

        // Clear queue
        if ((bool) $this->option('clear')) {
            $odinSync->clearQueue();
            $this->info('Sync queue cleared.');

            return self::SUCCESS;
        }

        $push = (bool) $this->option('push');
        $pull = (bool) $this->option('pull');
        /** @var string $project */
        $project = $this->option('project');

        // Default: push then pull
        if (! $push && ! $pull) {
            $push = true;
            $pull = true;
        }

        // Check connectivity
        $this->line('Checking Odin connectivity...');
        $available = $odinSync->isAvailable();

        if (! $available) {
            $this->warn('Odin server is not reachable. Operations will remain queued for later sync.');

            $status = $odinSync->getStatus();
            $this->line("Pending operations: {$status['pending']}");

            return self::SUCCESS;
        }

        $this->info('Odin server connected.');

        $pushResult = ['synced' => 0, 'failed' => 0, 'remaining' => 0];
        $pullCount = 0;

        // Push queued items
        if ($push) {
            $this->line('Processing sync queue...');
            $pushResult = $odinSync->processQueue();
        }

        // Pull from Odin
        if ($pull) {
            $this->line("Pulling entries from Odin for project '{$project}'...");
            $entries = $odinSync->pullFromOdin($project);
            $pullCount = $this->mergeEntries($entries, $qdrant, $odinSync, $project);
        }

        $this->displaySyncSummary($pushResult, $pullCount, $pull);

        return self::SUCCESS;
    }

    /**
     * Merge pulled entries into local Qdrant using last-write-wins.
     *
     * @param  array<int, array<string, mixed>>  $remoteEntries
     */
    private function mergeEntries(
        array $remoteEntries,
        QdrantService $qdrant,
        OdinSyncService $odinSync,
        string $project,
    ): int {
        $merged = 0;

        foreach ($remoteEntries as $remote) {
            $title = $remote['title'] ?? '';
            if ($title === '') {
                continue;
            }

            // Search for existing local entry by title
            $existing = $qdrant->search($title, [], 1, $project);
            $local = $existing->first();

            if ($local !== null) {
                // Conflict resolution: last-write-wins
                $winner = $odinSync->resolveConflict($local, $remote);
                if ($winner === $remote) {
                    $entry = $this->buildEntryFromRemote($remote, $local['id']);
                    $qdrant->upsert($entry, $project);
                    $merged++;
                }
            } else {
                // New entry from Odin
                $entry = $this->buildEntryFromRemote($remote, Str::uuid()->toString());
                $qdrant->upsert($entry, $project);
                $merged++;
            }
        }

        return $merged;
    }

    /**
     * Build a local entry from remote data.
     *
     * @param  array<string, mixed>  $remote
     * @return array{id: string|int, title: string, content: string, tags: array<string>, category?: string, module?: string, priority: string, status: string, confidence: int, usage_count: int, created_at: string, updated_at: string}
     */
    private function buildEntryFromRemote(array $remote, string|int $id): array
    {
        /** @var array<string> $tags */
        $tags = $remote['tags'] ?? [];

        $entry = [
            'id' => $id,
            'title' => (string) ($remote['title'] ?? 'Untitled'),
            'content' => (string) ($remote['content'] ?? ''),
            'tags' => $tags,
            'priority' => (string) ($remote['priority'] ?? 'medium'),
            'confidence' => (int) ($remote['confidence'] ?? 50),
            'status' => (string) ($remote['status'] ?? 'draft'),
            'usage_count' => (int) ($remote['usage_count'] ?? 0),
            'created_at' => (string) ($remote['created_at'] ?? now()->toIso8601String()),
            'updated_at' => (string) ($remote['updated_at'] ?? now()->toIso8601String()),
        ];

        if (isset($remote['category']) && is_string($remote['category'])) {
            $entry['category'] = $remote['category'];
        }

        if (isset($remote['module']) && is_string($remote['module'])) {
            $entry['module'] = $remote['module'];
        }

        return $entry;
    }

    private function displayStatus(OdinSyncService $odinSync): void
    {
        $status = $odinSync->getStatus();

        $statusColor = match ($status['status']) {
            'synced' => 'green',
            'error' => 'red',
            'pending', 'partial' => 'yellow',
            default => 'gray',
        };

        $this->newLine();
        $this->line('<fg=gray>Odin Sync Status</>');
        $this->table(
            ['Property', 'Value'],
            [
                ['Status', "<fg={$statusColor}>{$status['status']}</>"],
                ['Pending Operations', (string) $status['pending']],
                ['Last Synced', $status['last_synced'] ?? 'Never'],
                ['Last Error', $status['last_error'] ?? 'None'],
                ['Odin URL', (string) config('services.odin.url', 'Not configured')],
            ]
        );
    }

    /**
     * @param  array{synced: int, failed: int, remaining: int}  $pushResult
     */
    private function displaySyncSummary(array $pushResult, int $pullCount, bool $pulled): void
    {
        $this->newLine();
        $this->info('=== Odin Sync Summary ===');

        $rows = [
            ['Pushed (synced)', (string) $pushResult['synced']],
            ['Push failures', (string) $pushResult['failed']],
            ['Remaining in queue', (string) $pushResult['remaining']],
        ];

        if ($pulled) {
            $rows[] = ['Pulled & merged', (string) $pullCount];
        }

        $this->table(['Operation', 'Count'], $rows);
    }
}
