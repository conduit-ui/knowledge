<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\EntryMetadataService;
use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class KnowledgeMaintainCommand extends Command
{
    protected $signature = 'maintain
                            {--limit=50 : Maximum number of entries to check}';

    protected $description = 'Surface stale knowledge entries that need review or re-verification';

    public function handle(QdrantService $qdrant, EntryMetadataService $metadata): int
    {
        $limit = (int) $this->option('limit');

        $entries = $qdrant->scroll([], $limit);

        if ($entries->isEmpty()) {
            info('No entries found in the knowledge base.');

            return self::SUCCESS;
        }

        $staleEntries = $entries->filter(fn (array $entry): bool => $metadata->isStale($entry));

        if ($staleEntries->isEmpty()) {
            info('All entries are up to date. No stale entries found.');

            return self::SUCCESS;
        }

        warning("Found {$staleEntries->count()} stale ".str('entry')->plural($staleEntries->count()).' needing review:');
        $this->newLine();

        $rows = $staleEntries->map(function (array $entry) use ($metadata): array {
            $days = $metadata->daysSinceVerification($entry);
            $effectiveConfidence = $metadata->calculateEffectiveConfidence($entry);
            $confidenceLevel = $metadata->confidenceLevel($effectiveConfidence);

            return [
                substr((string) $entry['id'], 0, 8).'...',
                substr($entry['title'], 0, 40).(strlen($entry['title']) > 40 ? '...' : ''),
                $entry['last_verified'] ?? 'Never',
                "{$days} days",
                "{$effectiveConfidence}% ({$confidenceLevel})",
                $entry['status'] ?? 'N/A',
            ];
        })->values()->toArray();

        table(
            ['ID', 'Title', 'Last Verified', 'Age', 'Confidence', 'Status'],
            $rows
        );

        $this->newLine();
        $this->line('Use <fg=cyan>./know validate {id}</> to re-verify an entry.');
        $this->line('Use <fg=cyan>./know update {id} --status=deprecated</> to deprecate outdated entries.');

        return self::SUCCESS;
    }
}
