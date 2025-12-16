<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Services\ConfidenceService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeStatsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'knowledge:stats';

    /**
     * @var string
     */
    protected $description = 'Display analytics dashboard for knowledge entries';

    public function __construct(
        private readonly ConfidenceService $confidenceService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Knowledge Base Analytics');
        $this->newLine();

        $this->displayOverview();
        $this->newLine();

        $this->displayStatusBreakdown();
        $this->newLine();

        $this->displayCategoryBreakdown();
        $this->newLine();

        $this->displayUsageStatistics();
        $this->newLine();

        $this->displayStaleEntries();

        return self::SUCCESS;
    }

    private function displayOverview(): void
    {
        $total = Entry::query()->count();
        $this->line("Total Entries: {$total}");
    }

    private function displayStatusBreakdown(): void
    {
        $this->comment('Entries by Status:');

        $statuses = Entry::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get();

        if ($statuses->isEmpty()) {
            $this->line('  No entries found');

            return;
        }

        foreach ($statuses as $status) {
            $this->line("  {$status->status}: {$status->count}");
        }
    }

    private function displayCategoryBreakdown(): void
    {
        $this->comment('Entries by Category:');

        $categories = Entry::query()
            ->selectRaw('category, count(*) as count')
            ->whereNotNull('category')
            ->groupBy('category')
            ->get();

        if ($categories->isEmpty()) {
            $this->line('  No categorized entries found');

            return;
        }

        foreach ($categories as $category) {
            $this->line("  {$category->category}: {$category->count}");
        }

        $uncategorized = Entry::query()->whereNull('category')->count();
        if ($uncategorized > 0) {
            $this->line("  (uncategorized): {$uncategorized}");
        }
    }

    private function displayUsageStatistics(): void
    {
        $this->comment('Usage Statistics:');

        $totalUsage = Entry::query()->sum('usage_count');
        $this->line("  Total Usage: {$totalUsage}");

        $avgUsage = Entry::query()->avg('usage_count');
        $this->line('  Average Usage: '.round($avgUsage ?? 0));

        $mostUsed = Entry::query()->orderBy('usage_count', 'desc')->first();
        if ($mostUsed !== null && $mostUsed->usage_count > 0) {
            $this->line("  Most Used: \"{$mostUsed->title}\" ({$mostUsed->usage_count} times)");
        }

        $recentlyUsed = Entry::query()->whereNotNull('last_used')
            ->orderBy('last_used', 'desc')
            ->first();

        if ($recentlyUsed !== null && $recentlyUsed->last_used !== null) {
            $daysAgo = $recentlyUsed->last_used->diffInDays(now());
            $this->line("  Last Used: \"{$recentlyUsed->title}\" ({$daysAgo} days ago)");
        }
    }

    private function displayStaleEntries(): void
    {
        $this->comment('Maintenance:');

        $staleCount = $this->confidenceService->getStaleEntries()->count();
        $this->line("  Stale Entries (90+ days): {$staleCount}");

        if ($staleCount > 0) {
            $this->line('  Tip: Run "knowledge:stale" to review and validate them');
        }
    }
}
