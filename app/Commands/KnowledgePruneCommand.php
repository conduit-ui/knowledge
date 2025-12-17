<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Models\Relationship;
use Illuminate\Support\Carbon;
use LaravelZero\Framework\Commands\Command;

class KnowledgePruneCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'knowledge:prune
                            {--older-than=1y : Age threshold (e.g., 30d, 6m, 1y)}
                            {--deprecated-only : Only prune deprecated entries}
                            {--dry-run : Show what would be deleted without deleting}
                            {--force : Skip confirmation prompt}';

    /**
     * @var string
     */
    protected $description = 'Permanently delete old entries and clean up orphaned data';

    public function handle(): int
    {
        /** @var string $olderThan */
        $olderThan = $this->option('older-than') ?? '1y';

        /** @var bool $deprecatedOnly */
        $deprecatedOnly = (bool) $this->option('deprecated-only');

        /** @var bool $dryRun */
        $dryRun = (bool) $this->option('dry-run');

        /** @var bool $force */
        $force = (bool) $this->option('force');

        // Parse the age threshold
        $threshold = $this->parseThreshold($olderThan);

        if ($threshold === null) {
            $this->error('Invalid threshold format. Use: 30d (days), 6m (months), or 1y (years)');

            return self::FAILURE;
        }

        // Find entries to prune
        $query = Entry::query()->where('created_at', '<', $threshold);

        if ($deprecatedOnly) {
            $query->where('status', 'deprecated');
        }

        $entries = $query->get();

        if ($entries->isEmpty()) {
            $this->info('No entries found matching the criteria.');

            return self::SUCCESS;
        }

        $this->displayEntriesInfo($entries, $threshold);

        if ($dryRun) {
            $this->newLine();
            $this->comment('Dry run - no changes made.');

            return self::SUCCESS;
        }

        // Confirm unless --force is used
        if (! $force) {
            if (! $this->confirm('Are you sure you want to permanently delete these entries?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        // Delete entries and their relationships
        $deletedCount = $this->deleteEntries($entries);

        $this->newLine();
        $this->info("Pruned {$deletedCount} ".str('entry')->plural($deletedCount).'.');

        return self::SUCCESS;
    }

    /**
     * Parse age threshold string into a Carbon date.
     */
    private function parseThreshold(string $threshold): ?Carbon
    {
        if (preg_match('/^(\d+)([dmy])$/', $threshold, $matches) !== 1) {
            return null;
        }

        $value = (int) $matches[1];
        $unit = $matches[2];

        return match ($unit) {
            'd' => now()->subDays($value),
            'm' => now()->subMonths($value),
            default => now()->subYears($value),
        };
    }

    /**
     * Display information about entries to be pruned.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Entry>  $entries
     */
    private function displayEntriesInfo(\Illuminate\Database\Eloquent\Collection $entries, Carbon $threshold): void
    {
        $this->warn("Found {$entries->count()} ".str('entry')->plural($entries->count())." older than {$threshold->diffForHumans(now())}:");
        $this->newLine();

        $byStatus = $entries->groupBy('status');

        foreach ($byStatus as $status => $statusEntries) {
            $this->line("  {$status}: {$statusEntries->count()}");
        }

        $this->newLine();

        // Show sample entries
        $sample = $entries->take(5);
        foreach ($sample as $entry) {
            $age = $entry->created_at->diffForHumans();
            $this->line("  #{$entry->id} {$entry->title} <fg=gray>(created {$age})</>");
        }

        if ($entries->count() > 5) {
            $remaining = $entries->count() - 5;
            $this->line("  ... and {$remaining} more");
        }
    }

    /**
     * Delete entries and their relationships.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Entry>  $entries
     */
    private function deleteEntries(\Illuminate\Database\Eloquent\Collection $entries): int
    {
        $count = 0;

        foreach ($entries as $entry) {
            // Delete relationships
            Relationship::query()
                ->where('from_entry_id', $entry->id)
                ->orWhere('to_entry_id', $entry->id)
                ->delete();

            // Delete entry
            $entry->delete();
            $count++;
        }

        return $count;
    }
}
