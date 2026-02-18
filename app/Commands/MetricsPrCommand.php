<?php

declare(strict_types=1);

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\spin;

class MetricsPrCommand extends Command
{
    protected $signature = 'metrics:pr {--since=30d} {--repos=* : Repos, default top owned} {--json}';

    protected $description = 'Gorgeous PR metrics dashboard';

    public function handle(): int
    {
        $days = $this->parseDays($this->option('since'));
        $repos = $this->option('repos') ?: $this->getTopRepos();
        $json = (bool) $this->option('json');

        $since = date('Y-m-d', strtotime("-$days days"));

        info("PR Dashboard (last {$days}d | Repos: " . implode(', ', $repos) . ')');

        $metrics = spin(fn () => $this->fetchMetrics($repos, $since), 'Fetching...');

        if ($json) {
            $this->line(json_encode($metrics, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->displayDashboard($metrics);

        return self::SUCCESS;
    }

    private function parseDays(string $since): int
    {
        return (int) preg_replace('/[^0-9]/', '', $since);
    }

    private function getTopRepos(): array
    {
        $process = Process::fromShellCommandline('gh repo list --limit 10 --json nameWithOwner');
        $process->run();

        return collect(json_decode($process->getOutput(), true) ?? [])
            ->pluck('nameWithOwner')
            ->values()
            ->toArray();
    }

    private function fetchMetrics(array $repos, string $since): array
    {
        $data = [];
        $totals = collect(['opened' => 0, 'merged' => 0, 'open' => 0, 'reviews' => 0, 'prs' => []]);

        foreach ($repos as $repo) {
            $process = Process::fromShellCommandline("gh pr list --repo {$repo} --state all --limit 50 --search 'created:>{$since}' --json number,title,createdAt,mergedAt,state,comments,reviews,author.login,repository");
            $process->run();

            $prs = collect(json_decode($process->getOutput(), true) ?? []);

            $repoData = [
                'opened' => $prs->where('state', 'OPEN')->count(),
                'merged' => $prs->where('state', 'MERGED')->count(),
                'open' => $prs->where('state', 'OPEN')->count(),
                'reviews' => $prs->sum(fn ($pr) => count($pr['reviews'] ?? [])),
                'prs' => $prs->toArray(),
                'velocity' => $this->avgVelocity($prs),
            ];

            $data[$repo] = $repoData;

            $totals['opened'] += $repoData['opened'];
            $totals['merged'] += $repoData['merged'];
            $totals['open'] += $repoData['open'];
            $totals['reviews'] += $repoData['reviews'];
            $totals['prs'] = array_merge($totals['prs'], $prs->toArray());
        }

        $totals['velocity'] = $this->avgVelocity(collect($totals['prs']));

        return ['repos' => $data, 'totals' => $totals->toArray()];
    }

    private function avgVelocity(Collection $prs): float
    {
        $merged = $prs->filter(fn ($pr) => isset($pr['mergedAt']));
        if ($merged->isEmpty()) return 0.0;

        $days = $merged->sum(fn ($pr) => (new \DateTime($pr['mergedAt']))->diff(new \DateTime($pr['createdAt']))->days);

        return round($days / $merged->count(), 1);
    }

    private function displayDashboard(array $metrics): void
    {
        $totals = collect($metrics['totals']);

        // Overall
        table(
            ['Metric', 'Value'],
            [
                ['Opened', $totals['opened']],
                ['Merged', $totals['merged']],
                ['Open', $totals['open']],
                ['Reviews', $totals['reviews']],
                ['Avg Velocity (Days)', $totals['velocity']],
            ]
        );

        // Repo table
        $rows = collect($metrics['repos'])->map(fn ($data, $repo) => [
            $repo,
            $data['opened'],
            $data['merged'],
            $data['open'],
            $data['velocity'],
            $data['reviews'],
        ])->values()->toArray();

        table(
            ['Repo', 'Opened', 'Merged', 'Open', 'Velocity', 'Reviews'],
            $rows
        );

        // Top PRs
        $prs = collect($metrics['totals']['prs']);
        $prs = $prs->sortByDesc(fn ($pr) => $pr['mergedAt'] ?? '0000-00-00');
        $topPrs = $prs->take(5)->map(fn ($pr) => [
            $pr['repository']['nameWithOwner'],
            $pr['number'],
            substr($pr['title'], 0, 40) . '...',
            $pr['author']['login'],
            $pr['mergedAt'] ? round((new \DateTime($pr['mergedAt']))->diff(new \DateTime($pr['createdAt']))->days, 1) : 'Open',
            count($pr['reviews'] ?? []),
        ])->values()->toArray();

        table(
            ['Repo', '#', 'Title', 'Author', 'Velocity', 'Reviews'],
            $topPrs
        );
    }
}


    private function fetchMetrics(array $repos, string $since): array
    {
        $data = [];
        $totals = ['opened' => 0, 'merged' => 0, 'open' => 0, 'reviews' => 0, 'prs' => []];

        foreach ($repos as $repo) {
            $cmd = ['gh', 'pr', 'list', '--repo', $repo, '--state', 'all', '--limit', '50', '--search', "created:>{$since}"];
            $process = Process::fromShellCommandline(implode(' ', $cmd));
            $process->run();

            $raw = json_decode($process->getOutput(), true) ?? [];
            $prs = array_values(array_filter($raw, fn ($pr) => (new \DateTime($pr['createdAt'])) > new \DateTime($since)));

            $repoData = [
                'opened' => count(array_filter($prs, fn ($pr) => $pr['state'] === 'OPEN')),
                'merged' => count(array_filter($prs, fn ($pr) => $pr['state'] === 'MERGED')),
                'open' => count(array_filter($prs, fn ($pr) => $pr['state'] === 'OPEN')),
                'reviews' => array_sum(array_map(fn ($pr) => count($pr['reviews'] ?? []), $prs)),
                'prs' => $prs,
            ];

            $repoData['velocity'] = $this->avgVelocity($prs);

            $data[$repo] = $repoData;

            $totals['opened'] += $repoData['opened'];
            $totals['merged'] += $repoData['merged'];
            $totals['open'] += $repoData['open'];
            $totals['reviews'] += $repoData['reviews'];
            $totals['prs'] = array_merge($totals['prs'], $prs);
        }

        $totals['velocity'] = $this->avgVelocity($totals['prs']);

        return ['repos' => $data, 'totals' => $totals];
    }

    private function avgVelocity(array $prs): float
    {
        $merged = array_filter($prs, fn ($pr) => isset($pr['mergedAt']));
        if (empty($merged)) return 0.0;

        $days = 0.0;
        foreach ($merged as $pr) {
            $created = new \DateTime($pr['createdAt']);
            $mergedAt = new \DateTime($pr['mergedAt']);
            $days += $mergedAt->diff($created)->days;
        }

        return round($days / count($merged), 1);
    }

    private function getTopRepos(int $days): array
    {
        $process = Process::fromShellCommandline('gh repo list --limit 10 --json nameWithOwner');
        $process->run();

        $repos = json_decode($process->getOutput(), true) ?? [];
        return array_column($repos, 'nameWithOwner');
    }

    private function displayDashboard(array $metrics): void
    {
        $totals = $metrics['totals'];

        // Overall
        table(
            ['Metric', 'Value'],
            [
                ['Opened', $totals['opened']],
                ['Merged', $totals['merged']],
                ['Open', $totals['open']],
                ['Reviews', $totals['reviews']],
                ['Avg Velocity (Days)', $totals['velocity']],
            ]
        );

        // Repo table
        $rows = [];
        foreach ($metrics['repos'] as $repo => $data) {
            $rows[] = [
                $repo,
                $data['opened'],
                $data['merged'],
                $data['open'],
                $data['velocity'],
                $data['reviews'],
            ];
        }

        table(
            ['Repo', 'Opened', 'Merged', 'Open', 'Velocity', 'Reviews'],
            $rows
        );

        // Top PRs
        $prs = $totals['prs'];
        usort($prs, fn ($a, $b) => strcmp($b['mergedAt'] ?? '0000', $a['mergedAt'] ?? '0000'));

        $topRows = [];
        foreach (array_slice($prs, 0, 5) as $pr) {
            $repo = $pr['repository']['nameWithOwner'];
            $topRows[] = [
                $repo,
                $pr['number'],
                substr($pr['title'], 0, 40) . '...',
                $pr['author']['login'],
                $pr['mergedAt'] ? round((new \DateTime($pr['mergedAt'])->diff(new \DateTime($pr['createdAt']))->days, 1) : 'Open',
                count($pr['reviews'] ?? []),
            ];
        }

        table(
            ['Repo', '#', 'Title', 'Author', 'Velocity', 'Reviews'],
            $topRows
        );
    }
}
