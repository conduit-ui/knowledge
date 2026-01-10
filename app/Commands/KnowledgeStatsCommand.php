<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeStatsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'stats';

    /**
     * @var string
     */
    protected $description = 'Display analytics dashboard for knowledge entries';

    public function handle(QdrantService $qdrant): int
    {
        $this->info('Knowledge Base Analytics');
        $this->newLine();

        $this->displayOverview($qdrant);
        $this->newLine();

        $this->displayStatusBreakdown($qdrant);
        $this->newLine();

        $this->displayCategoryBreakdown($qdrant);
        $this->newLine();

        $this->displayUsageStatistics($qdrant);

        return self::SUCCESS;
    }

    /**
     * Display total entries count overview.
     */
    private function displayOverview(QdrantService $qdrant): void
    {
        $entries = $this->getAllEntries($qdrant);
        $this->line('Total Entries: '.$entries->count());
    }

    /**
     * Display breakdown of entries by status.
     */
    private function displayStatusBreakdown(QdrantService $qdrant): void
    {
        $this->comment('Entries by Status:');

        $entries = $this->getAllEntries($qdrant);
        $statusGroups = $entries->groupBy('status');

        if ($statusGroups->isEmpty()) {
            $this->line('  No entries found');

            return;
        }

        foreach ($statusGroups as $status => $group) {
            $this->line("  {$status}: ".$group->count());
        }
    }

    /**
     * Display breakdown of entries by category.
     */
    private function displayCategoryBreakdown(QdrantService $qdrant): void
    {
        $this->comment('Entries by Category:');

        $entries = $this->getAllEntries($qdrant);
        $categorized = $entries->whereNotNull('category');
        $categoryGroups = $categorized->groupBy('category');

        if ($categoryGroups->isEmpty()) {
            $this->line('  No categorized entries found');

            return;
        }

        foreach ($categoryGroups as $category => $group) {
            $this->line("  {$category}: ".$group->count());
        }

        $uncategorized = $entries->whereNull('category')->count();
        if ($uncategorized > 0) {
            $this->line("  (uncategorized): {$uncategorized}");
        }
    }

    /**
     * Display usage statistics for knowledge entries.
     */
    private function displayUsageStatistics(QdrantService $qdrant): void
    {
        $this->comment('Usage Statistics:');

        $entries = $this->getAllEntries($qdrant);

        $totalUsage = $entries->sum('usage_count');
        $this->line("  Total Usage: {$totalUsage}");

        $avgUsage = $entries->avg('usage_count');
        $this->line('  Average Usage: '.round($avgUsage ?? 0));

        $mostUsed = $entries->sortByDesc('usage_count')->first();
        if ($mostUsed !== null && $mostUsed['usage_count'] > 0) {
            $this->line("  Most Used: \"{$mostUsed['title']}\" ({$mostUsed['usage_count']} times)");
        }
    }

    /**
     * Get all entries from Qdrant (cached for multiple calls).
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function getAllEntries(QdrantService $qdrant)
    {
        static $entries = null;

        if ($entries === null) {
            // Fetch all entries with high limit (Qdrant default is ~1000, adjust as needed)
            $entries = $qdrant->search('', [], 10000);
        }

        return $entries;
    }
}
