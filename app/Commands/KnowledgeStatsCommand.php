<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\QdrantService;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class KnowledgeStatsCommand extends Command
{
    protected $signature = 'stats';

    protected $description = 'Display analytics dashboard for knowledge entries';

    public function handle(QdrantService $qdrant): int
    {
        $total = spin(
            fn (): int => $qdrant->count(),
            'Loading knowledge base...'
        );

        // Get a sample of entries for category/status breakdown (limit to 1000 for performance)
        $entries = $qdrant->scroll([], min($total, 1000));

        $this->renderDashboard($entries, $total);

        return self::SUCCESS;
    }

    private function renderDashboard(Collection $entries, int $total): void
    {
        info("Knowledge Base: {$total} entries");
        $this->newLine();

        // Overview metrics
        $totalUsage = $entries->sum('usage_count');
        $avgUsage = round($entries->avg('usage_count') ?? 0);

        $this->line('<fg=gray>Overview</>');
        table(
            ['Metric', 'Value'],
            [
                ['Total Entries', (string) $total],
                ['Total Usage', (string) $totalUsage],
                ['Avg Usage', (string) $avgUsage],
            ]
        );

        // Status breakdown
        $statusGroups = $entries->groupBy('status');
        if ($statusGroups->isNotEmpty()) {
            $this->newLine();
            $this->line('<fg=gray>By Status</>');
            $statusRows = [];
            foreach ($statusGroups as $status => $group) {
                $count = $group->count();
                $pct = $total > 0 ? round(($count / $total) * 100) : 0;
                $color = match ($status) {
                    'validated' => 'green',
                    'deprecated' => 'red',
                    default => 'yellow',
                };
                $statusRows[] = ["<fg={$color}>{$status}</>", "{$count} ({$pct}%)"];
            }
            table(['Status', 'Count'], $statusRows);
        }

        // Category breakdown
        $categoryGroups = $entries->whereNotNull('category')->groupBy('category');
        if ($categoryGroups->isNotEmpty()) {
            $this->newLine();
            $this->line('<fg=gray>By Category</>');
            $categoryRows = [];
            foreach ($categoryGroups as $category => $group) {
                $categoryRows[] = [$category, (string) $group->count()];
            }
            $uncategorized = $entries->whereNull('category')->count();
            if ($uncategorized > 0) {
                $categoryRows[] = ['<fg=gray>(none)</>', (string) $uncategorized];
            }
            table(['Category', 'Count'], $categoryRows);
        }

        // Most used
        $mostUsed = $entries->sortByDesc('usage_count')->first();
        if ($mostUsed && $mostUsed['usage_count'] > 0) {
            $this->newLine();
            $this->line('<fg=gray>Most Used</>');
            $this->line("  <fg=cyan>\"{$mostUsed['title']}\"</> ({$mostUsed['usage_count']} uses)");
        }
    }
}
