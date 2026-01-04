<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class MilestonesCommand extends Command
{
    protected $signature = 'milestones
                            {--since=7 : Number of days to look back (default: 7)}
                            {--project= : Filter by project/module}';

    protected $description = 'Show accomplished work and wins from recent activity';

    public function handle(): int
    {
        $sinceDays = (int) $this->option('since');
        /** @var string|null $project */
        $project = $this->option('project');

        $this->info("Milestones (Last {$sinceDays} days)");

        if ($project !== null) {
            $this->line("Project: {$project}");
        }

        $this->newLine();

        // Display knowledge entries
        $milestones = $this->getMilestones($sinceDays, $project);
        $this->displayKnowledgeMilestones($milestones);

        $this->newLine();

        // Display GitHub PRs
        $prs = $this->getMergedPRs($sinceDays, $project);
        $this->displayMergedPRs($prs);

        $this->newLine();

        // Display GitHub issues
        $issues = $this->getClosedIssues($sinceDays, $project);
        $this->displayClosedIssues($issues);

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Entry>
     */
    private function getMilestones(int $sinceDays, ?string $project): Collection
    {
        $sinceDate = now()->subDays($sinceDays);

        /** @var \Illuminate\Database\Eloquent\Builder<Entry> $query */
        $query = Entry::query()
            ->where(function ($query) {
                $query->where('content', 'like', '%✅%')
                    ->orWhere('content', 'like', '%## Milestones%')
                    ->orWhere('tags', 'like', '%milestone%')
                    ->orWhere('tags', 'like', '%accomplished%')
                    ->orWhere('tags', 'like', '%completed%');
            })
            ->where('status', 'validated')
            ->where('created_at', '>=', $sinceDate);

        if ($project !== null) {
            $query->where(function ($q) use ($project) {
                $q->where('tags', 'like', "%{$project}%")
                    ->orWhere('module', $project);
            });
        }

        /** @var Collection<int, Entry> */
        /** @phpstan-ignore-next-line */
        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getMergedPRs(int $sinceDays, ?string $project): array
    {
        $sinceDate = now()->subDays($sinceDays)->toIso8601String();
        $repoName = $this->getRepoName();

        $args = [
            'gh', 'pr', 'list',
            '--repo', $repoName,
            '--state', 'merged',
            '--json', 'number,title,mergedAt,url',
            '--limit', '100',
        ];

        $result = Process::run($args);

        if (! $result->successful()) {
            return [];
        }

        $output = trim($result->output());

        $prs = json_decode($output, true);

        if (! is_array($prs)) {
            return [];
        }

        return array_values(array_filter($prs, function ($pr) use ($sinceDate, $project) {
            $mergedAt = $pr['mergedAt'] ?? null;

            if (! $mergedAt || $mergedAt < $sinceDate) {
                return false;
            }

            if ($project !== null) {
                return str_contains(strtolower($pr['title']), strtolower($project));
            }

            return true;
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getClosedIssues(int $sinceDays, ?string $project): array
    {
        $sinceDate = now()->subDays($sinceDays)->toIso8601String();
        $repoName = $this->getRepoName();

        $args = [
            'gh', 'issue', 'list',
            '--repo', $repoName,
            '--state', 'closed',
            '--json', 'number,title,closedAt,url',
            '--limit', '100',
        ];

        $result = Process::run($args);

        if (! $result->successful()) {
            return [];
        }

        $issues = json_decode(trim($result->output()), true);

        if (! is_array($issues)) {
            return [];
        }

        return array_values(array_filter($issues, function ($issue) use ($sinceDate, $project) {
            $closedAt = $issue['closedAt'] ?? null;

            if (! $closedAt || $closedAt < $sinceDate) {
                return false;
            }

            if ($project !== null) {
                return str_contains(strtolower($issue['title']), strtolower($project));
            }

            return true;
        }));
    }

    private function getRepoName(): string
    {
        $result = Process::run(['git', 'remote', 'get-url', 'origin']);

        if ($result->successful()) {
            $remote = trim($result->output());

            if (preg_match('#github\.com[:/](.+/.+?)(?:\.git)?$#', $remote, $matches) === 1) {
                return $matches[1];
            }
        }

        return 'conduit-ui/knowledge';
    }

    /**
     * @param  Collection<int, Entry>  $milestones
     */
    private function displayKnowledgeMilestones(Collection $milestones): void
    {
        $this->line('<fg=cyan>Knowledge Milestones:</fg=cyan>');

        if ($milestones->isEmpty()) {
            $this->line('  No milestones found in knowledge base');

            return;
        }

        $grouped = $this->groupByDate($milestones);

        foreach ($grouped as $timeGroup => $items) {
            $this->line("<fg=green>{$timeGroup}:</>");

            foreach ($items as $milestone) {
                $this->displayMilestone($milestone);
            }

            $this->newLine();
        }
    }

    private function displayMilestone(Entry $milestone): void
    {
        $this->line("  <fg=green>✓</> <fg=cyan>[{$milestone->id}]</> {$milestone->title}");

        $details = $this->extractMilestoneDetails($milestone);

        foreach ($details as $detail) {
            $this->line("    <fg=green>•</> {$detail}");
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractMilestoneDetails(Entry $milestone): array
    {
        $details = [];
        $content = $milestone->content;

        if (preg_match('/## Milestones(.+?)(?=##|$)/s', $content, $matches) === 1) {
            $milestonesSection = $matches[1];

            if (preg_match_all('/[-•]\s*✅\s*(.+?)(?=\n|$)/u', $milestonesSection, $itemMatches) > 0) {
                foreach ($itemMatches[1] as $item) {
                    $details[] = trim($item);
                }
            }

            if (preg_match_all('/✅\s*(.+?)(?=\n|$)/u', $milestonesSection, $checkMatches) > 0) {
                foreach ($checkMatches[1] as $item) {
                    $cleaned = trim($item);
                    if (! in_array($cleaned, $details, true)) {
                        $details[] = $cleaned;
                    }
                }
            }
        }

        if ($details === []) {
            return [$milestone->title];
        }

        return $details;
    }

    /**
     * @param  Collection<int, Entry>  $milestones
     * @return array<string, Collection<int, Entry>>
     */
    private function groupByDate(Collection $milestones): array
    {
        /** @var Collection<int, Entry> $today */
        $today = new Collection;
        /** @var Collection<int, Entry> $thisWeek */
        $thisWeek = new Collection;
        /** @var Collection<int, Entry> $older */
        $older = new Collection;

        foreach ($milestones as $milestone) {
            $age = (int) $milestone->created_at->diffInDays(now());

            if ($age === 0) {
                $today->push($milestone);
            } elseif ($age <= 7) {
                $thisWeek->push($milestone);
            } else {
                $older->push($milestone);
            }
        }

        /** @var array<string, Collection<int, Entry>> $groups */
        $groups = [];

        if ($today->isNotEmpty()) {
            $groups['Today'] = $today;
        }

        if ($thisWeek->isNotEmpty()) {
            $groups['This Week'] = $thisWeek;
        }

        if ($older->isNotEmpty()) {
            $groups['Older'] = $older;
        }

        return $groups;
    }

    /**
     * @param  array<int, array<string, mixed>>  $prs
     */
    private function displayMergedPRs(array $prs): void
    {
        $this->line('<fg=cyan>Merged Pull Requests:</fg=cyan>');

        if ($prs === []) {
            $this->line('  No merged PRs found');

            return;
        }

        $grouped = $this->groupGitHubItemsByDate($prs, 'mergedAt');

        foreach ($grouped as $timeGroup => $items) {
            $this->line("<fg=green>{$timeGroup}:</>");

            foreach ($items as $pr) {
                $mergedAt = Carbon::parse($pr['mergedAt'])->diffForHumans();
                $this->line("  <fg=green>✓</> <fg=cyan>#{$pr['number']}</> {$pr['title']} ({$mergedAt})");
            }

            $this->newLine();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     */
    private function displayClosedIssues(array $issues): void
    {
        $this->line('<fg=cyan>Closed Issues:</fg=cyan>');

        if ($issues === []) {
            $this->line('  No closed issues found');

            return;
        }

        $grouped = $this->groupGitHubItemsByDate($issues, 'closedAt');

        foreach ($grouped as $timeGroup => $items) {
            $this->line("<fg=green>{$timeGroup}:</>");

            foreach ($items as $issue) {
                $closedAt = Carbon::parse($issue['closedAt'])->diffForHumans();
                $this->line("  <fg=green>✓</> <fg=cyan>#{$issue['number']}</> {$issue['title']} ({$closedAt})");
            }

            $this->newLine();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupGitHubItemsByDate(array $items, string $dateField): array
    {
        $today = [];
        $thisWeek = [];
        $older = [];

        foreach ($items as $item) {
            $date = Carbon::parse($item[$dateField]);
            $age = (int) $date->diffInDays(now());

            if ($age === 0) {
                $today[] = $item;
            } elseif ($age <= 7) {
                $thisWeek[] = $item;
            } else {
                $older[] = $item;
            }
        }

        $groups = [];

        if ($today !== []) {
            $groups['Today'] = $today;
        }

        if ($thisWeek !== []) {
            $groups['This Week'] = $thisWeek;
        }

        if ($older !== []) {
            $groups['Older'] = $older;
        }

        return $groups;
    }
}
