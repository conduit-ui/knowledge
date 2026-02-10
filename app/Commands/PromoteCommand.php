<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\DailyLogService;
use App\Services\QdrantService;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class PromoteCommand extends Command
{
    protected $signature = 'promote
                            {--auto : Auto-promote entries matching categories with high confidence}
                            {--date= : Promote entries from a specific date (YYYY-MM-DD)}
                            {--id= : Promote a specific entry by ID}
                            {--all : Promote all entries past retention period}
                            {--retention= : Override retention period in days}
                            {--dry-run : Show what would be promoted without actually promoting}';

    protected $description = 'Promote staged daily log entries to permanent storage';

    public function handle(DailyLogService $dailyLog, QdrantService $qdrant): int
    {
        /** @var bool $auto */
        $auto = (bool) $this->option('auto');
        /** @var string|null $date */
        $date = is_string($this->option('date')) ? $this->option('date') : null;
        /** @var string|null $entryId */
        $entryId = is_string($this->option('id')) ? $this->option('id') : null;
        /** @var bool $all */
        $all = (bool) $this->option('all');
        /** @var bool $dryRun */
        $dryRun = (bool) $this->option('dry-run');

        if ($entryId !== null) {
            return $this->promoteById($dailyLog, $qdrant, $entryId, $dryRun);
        }

        if ($date !== null) {
            return $this->promoteByDate($dailyLog, $qdrant, $date, $dryRun);
        }

        if ($auto) {
            return $this->autoPromote($dailyLog, $qdrant, $dryRun);
        }

        $retentionOption = $this->option('retention');
        $retentionDays = is_numeric($retentionOption) ? (int) $retentionOption : $dailyLog->getRetentionDays();

        if ($all) {
            return $this->promoteAll($dailyLog, $qdrant, $retentionDays, $dryRun);
        }

        // Default: show promotable entries
        return $this->showPromotable($dailyLog, $retentionDays);
    }

    private function promoteById(DailyLogService $dailyLog, QdrantService $qdrant, string $entryId, bool $dryRun): int
    {
        foreach ($dailyLog->listDailyLogs() as $date) {
            $entries = $dailyLog->readDailyLog($date);
            foreach ($entries as $entry) {
                if ($entry['id'] === $entryId) {
                    if ($dryRun) {
                        info("[dry-run] Would promote: {$entry['title']}");

                        return self::SUCCESS;
                    }

                    return $this->promoteEntry($dailyLog, $qdrant, $entry, $date);
                }
            }
        }

        error("Entry not found: {$entryId}");

        return self::FAILURE;
    }

    private function promoteByDate(DailyLogService $dailyLog, QdrantService $qdrant, string $date, bool $dryRun): int
    {
        $entries = $dailyLog->readDailyLog($date);

        if ($entries === []) {
            warning("No entries found for date: {$date}");

            return self::SUCCESS;
        }

        $promoted = 0;
        foreach ($entries as $entry) {
            if ($dryRun) {
                info("[dry-run] Would promote: {$entry['title']}");
                $promoted++;

                continue;
            }

            $result = $this->promoteEntry($dailyLog, $qdrant, $entry, $date);
            if ($result === self::SUCCESS) {
                $promoted++;
            }
        }

        info("Promoted {$promoted} entries from {$date}");

        return self::SUCCESS;
    }

    private function autoPromote(DailyLogService $dailyLog, QdrantService $qdrant, bool $dryRun): int
    {
        $entries = $dailyLog->getAutoPromotableEntries();

        if ($entries === []) {
            info('No entries eligible for auto-promotion');

            return self::SUCCESS;
        }

        $promoted = 0;
        foreach ($entries as $entry) {
            if ($dryRun) {
                info("[dry-run] Would auto-promote: {$entry['title']} (confidence: {$entry['confidence']}%, category: {$entry['category']})");
                $promoted++;

                continue;
            }

            $result = $this->promoteEntry($dailyLog, $qdrant, $entry, $entry['date']);
            if ($result === self::SUCCESS) {
                $promoted++;
            }
        }

        info("Auto-promoted {$promoted} entries");

        return self::SUCCESS;
    }

    private function promoteAll(DailyLogService $dailyLog, QdrantService $qdrant, int $retentionDays, bool $dryRun): int
    {
        $entries = $dailyLog->getPromotableEntries($retentionDays);

        if ($entries === []) {
            info('No entries past retention period');

            return self::SUCCESS;
        }

        $promoted = 0;
        foreach ($entries as $entry) {
            if ($dryRun) {
                info("[dry-run] Would promote: {$entry['title']} (from {$entry['date']})");
                $promoted++;

                continue;
            }

            $result = $this->promoteEntry($dailyLog, $qdrant, $entry, $entry['date']);
            if ($result === self::SUCCESS) {
                $promoted++;
            }
        }

        info("Promoted {$promoted} entries past {$retentionDays}-day retention period");

        return self::SUCCESS;
    }

    /**
     * @param  array{id: string, title: string, content: string, section: string, category: ?string, tags: array<string>, priority: string, confidence: int}  $entry
     */
    private function promoteEntry(DailyLogService $dailyLog, QdrantService $qdrant, array $entry, string $date): int
    {
        $data = [
            'id' => Str::uuid()->toString(),
            'title' => $entry['title'],
            'content' => $entry['content'],
            'tags' => $entry['tags'],
            'priority' => $entry['priority'],
            'confidence' => $entry['confidence'],
            'status' => 'validated',
        ];

        if ($entry['category'] !== null) {
            $data['category'] = $entry['category'];
        }

        try {
            $success = spin(
                fn (): bool => $qdrant->upsert($data, 'default', false),
                "Promoting: {$entry['title']}..."
            );

            if (! $success) {
                error("Failed to promote: {$entry['title']}");

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            error("Failed to promote '{$entry['title']}': {$e->getMessage()}");

            return self::FAILURE;
        }

        $dailyLog->removeEntry($date, $entry['id']);

        return self::SUCCESS;
    }

    private function showPromotable(DailyLogService $dailyLog, int $retentionDays): int
    {
        $entries = $dailyLog->getPromotableEntries($retentionDays);
        $autoEntries = $dailyLog->getAutoPromotableEntries();
        $allLogs = $dailyLog->listDailyLogs();

        if ($allLogs === []) {
            info('No daily logs in staging');

            return self::SUCCESS;
        }

        $totalEntries = 0;
        foreach ($allLogs as $date) {
            $totalEntries += count($dailyLog->readDailyLog($date));
        }

        table(
            ['Metric', 'Value'],
            [
                ['Daily logs', (string) count($allLogs)],
                ['Total staged entries', (string) $totalEntries],
                ['Past retention period', (string) count($entries)],
                ['Eligible for auto-promote', (string) count($autoEntries)],
                ['Retention period', "{$retentionDays} days"],
            ]
        );

        if ($entries !== []) {
            info('Use --all to promote entries past retention period');
        }

        if ($autoEntries !== []) {
            info('Use --auto to auto-promote high-confidence entries');
        }

        return self::SUCCESS;
    }
}
