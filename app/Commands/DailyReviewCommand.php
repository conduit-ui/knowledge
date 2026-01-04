<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class DailyReviewCommand extends Command
{
    protected $signature = 'daily-review {--quick : Quick review with fewer questions}';

    protected $description = 'End-of-day reflection ritual to review accomplishments and learnings';

    public function handle(): int
    {
        // Check for duplicate review today
        if ($this->hasTodayReview()) {
            $this->error('You have already completed a daily review today.');

            return self::FAILURE;
        }

        $isQuick = (bool) $this->option('quick');

        // Display header
        $this->displayHeader();

        // Retrieve and display milestones
        $milestones = $this->getTodayMilestones();
        $this->displayMilestones($milestones);

        // Prompt for reflections
        $reflections = $this->promptReflections($isQuick);

        // Save entry
        $entry = $this->saveEntry($reflections, $milestones, $isQuick);

        // Display success
        $this->newLine();
        $this->info("✓ Daily review saved successfully as entry #{$entry->id}");

        return self::SUCCESS;
    }

    private function hasTodayReview(): bool
    {
        return Entry::query()
            ->where('category', 'reflection')
            ->where('tags', 'like', '%daily-review%')
            ->whereDate('created_at', today())
            ->exists();
    }

    private function displayHeader(): void
    {
        $this->newLine();
        $this->line('<fg=cyan>Daily Review - '.now()->format('l, F j, Y').'</>');
        $this->newLine();
    }

    /**
     * @return array{
     *     knowledge: Collection<int, Entry>,
     *     prs: array<int, array<string, mixed>>,
     *     issues: array<int, array<string, mixed>>
     * }
     */
    private function getTodayMilestones(): array
    {
        return [
            'knowledge' => $this->getKnowledgeMilestones(),
            'prs' => $this->getMergedPRsToday(),
            'issues' => $this->getClosedIssuesToday(),
        ];
    }

    /**
     * @return Collection<int, Entry>
     */
    private function getKnowledgeMilestones(): Collection
    {
        /** @var Collection<int, Entry> */
        return Entry::query()
            ->where(function ($query) {
                $query->where('content', 'like', '%✅%')
                    ->orWhere('content', 'like', '%## Milestones%')
                    ->orWhere('tags', 'like', '%milestone%');
            })
            ->where('status', 'validated')
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getMergedPRsToday(): array
    {
        $todayStart = now()->startOfDay()->toIso8601String();
        $repoName = $this->getRepoName();

        if ($repoName === null) {
            return [];
        }

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

        $prs = json_decode(trim($result->output()), true);

        if (! is_array($prs)) {
            return [];
        }

        return array_values(array_filter($prs, function ($pr) use ($todayStart) {
            $mergedAt = $pr['mergedAt'] ?? null;

            return $mergedAt && $mergedAt >= $todayStart;
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getClosedIssuesToday(): array
    {
        $todayStart = now()->startOfDay()->toIso8601String();
        $repoName = $this->getRepoName();

        if ($repoName === null) {
            return [];
        }

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

        return array_values(array_filter($issues, function ($issue) use ($todayStart) {
            $closedAt = $issue['closedAt'] ?? null;

            return $closedAt && $closedAt >= $todayStart;
        }));
    }

    private function getRepoName(): ?string
    {
        $result = Process::run(['git', 'remote', 'get-url', 'origin']);

        if (! $result->successful()) {
            return null;
        }

        $remote = trim($result->output());

        if (preg_match('#github\.com[:/](.+/.+?)(?:\.git)?$#', $remote, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param array{
     *     knowledge: Collection<int, Entry>,
     *     prs: array<int, array<string, mixed>>,
     *     issues: array<int, array<string, mixed>>
     * } $milestones
     */
    private function displayMilestones(array $milestones): void
    {
        $hasKnowledge = $milestones['knowledge']->isNotEmpty();
        $hasPRs = $milestones['prs'] !== [];
        $hasIssues = $milestones['issues'] !== [];

        if (! $hasKnowledge && ! $hasPRs && ! $hasIssues) {
            $this->line('No milestones found');
            $this->newLine();

            return;
        }

        $this->line("<fg=cyan>Today's Accomplishments</>");
        $this->newLine();

        if ($hasKnowledge) {
            $this->displayKnowledgeMilestones($milestones['knowledge']);
        }

        if ($hasPRs) {
            $this->displayMergedPRs($milestones['prs']);
        }

        if ($hasIssues) {
            $this->displayClosedIssues($milestones['issues']);
        }

        $this->newLine();
    }

    /**
     * @param  Collection<int, Entry>  $milestones
     */
    private function displayKnowledgeMilestones(Collection $milestones): void
    {
        $this->line('<fg=cyan>Knowledge Milestones:</>');

        foreach ($milestones as $milestone) {
            $this->line("  <fg=green>✓</> {$milestone->title}");

            $details = $this->extractMilestoneDetails($milestone);
            foreach ($details as $detail) {
                $this->line("    <fg=green>•</> {$detail}");
            }
        }

        $this->newLine();
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

            if (empty($details) && preg_match_all('/✅\s*(.+?)(?=\n|$)/u', $milestonesSection, $checkMatches) > 0) {
                foreach ($checkMatches[1] as $item) {
                    $cleaned = trim($item);
                    if (! in_array($cleaned, $details, true)) {
                        $details[] = $cleaned;
                    }
                }
            }
        }

        return $details;
    }

    /**
     * @param  array<int, array<string, mixed>>  $prs
     */
    private function displayMergedPRs(array $prs): void
    {
        $this->line('<fg=cyan>Merged Pull Requests:</>');

        foreach ($prs as $pr) {
            $this->line("  <fg=green>✓</> <fg=cyan>#{$pr['number']}</> {$pr['title']}");
        }

        $this->newLine();
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     */
    private function displayClosedIssues(array $issues): void
    {
        $this->line('<fg=cyan>Closed Issues:</>');

        foreach ($issues as $issue) {
            $this->line("  <fg=green>✓</> <fg=cyan>#{$issue['number']}</> {$issue['title']}");
        }

        $this->newLine();
    }

    /**
     * @return array<string, string>
     */
    private function promptReflections(bool $isQuick): array
    {
        $reflections = [];

        if ($isQuick) {
            $reflections['what_went_well'] = (string) $this->ask('What went well today?');
            $reflections['what_learned'] = (string) $this->ask('What did you learn?');
            $reflections['key_takeaways'] = (string) $this->ask('What are your key takeaways?');
        } else {
            $reflections['what_went_well'] = (string) $this->ask('What went well today?');
            $reflections['biggest_challenges'] = (string) $this->ask('What were the biggest challenges?');
            $reflections['what_learned'] = (string) $this->ask('What did you learn?');
            $reflections['do_differently'] = (string) $this->ask('What would you do differently?');
            $reflections['key_takeaways'] = (string) $this->ask('What are your key takeaways?');
        }

        return $reflections;
    }

    /**
     * @param  array<string, string>  $reflections
     * @param array{
     *     knowledge: Collection<int, Entry>,
     *     prs: array<int, array<string, mixed>>,
     *     issues: array<int, array<string, mixed>>
     * } $milestones
     */
    private function saveEntry(array $reflections, array $milestones, bool $isQuick): Entry
    {
        $content = $this->formatContent($reflections, $milestones, $isQuick);
        $title = $this->generateTitle($milestones);
        $tags = $this->generateTags($isQuick);

        /** @var Entry */
        return Entry::create([
            'title' => $title,
            'content' => $content,
            'category' => 'reflection',
            'tags' => $tags,
            'status' => 'validated',
            'confidence' => 95,
            'validation_date' => now(),
            'repo' => $this->getCurrentRepo(),
            'branch' => $this->getCurrentBranch(),
            'commit' => $this->getCurrentCommit(),
        ]);
    }

    /**
     * @param  array<string, string>  $reflections
     * @param array{
     *     knowledge: Collection<int, Entry>,
     *     prs: array<int, array<string, mixed>>,
     *     issues: array<int, array<string, mixed>>
     * } $milestones
     */
    private function formatContent(array $reflections, array $milestones, bool $isQuick): string
    {
        $content = [];

        // Add milestones section
        $milestonesList = $this->formatMilestonesList($milestones);
        if (! empty($milestonesList)) {
            $content[] = "## Milestones\n".implode("\n", $milestonesList);
        }

        // Add reflection sections
        if (isset($reflections['what_went_well'])) {
            $content[] = "## What Went Well\n".$reflections['what_went_well'];
        }

        if (isset($reflections['biggest_challenges'])) {
            $content[] = "## Biggest Challenges\n".$reflections['biggest_challenges'];
        }

        if (isset($reflections['what_learned'])) {
            $content[] = "## What I Learned\n".$reflections['what_learned'];
        }

        if (isset($reflections['do_differently'])) {
            $content[] = "## What I Would Do Differently\n".$reflections['do_differently'];
        }

        if (isset($reflections['key_takeaways'])) {
            $content[] = "## Key Takeaways\n".$reflections['key_takeaways'];
        }

        return implode("\n\n", $content);
    }

    /**
     * @param array{
     *     knowledge: Collection<int, Entry>,
     *     prs: array<int, array<string, mixed>>,
     *     issues: array<int, array<string, mixed>>
     * } $milestones
     * @return array<int, string>
     */
    private function formatMilestonesList(array $milestones): array
    {
        $list = [];

        foreach ($milestones['knowledge'] as $milestone) {
            $list[] = "- ✅ {$milestone->title}";
        }

        foreach ($milestones['prs'] as $pr) {
            $list[] = "- ✅ PR #{$pr['number']}: {$pr['title']}";
        }

        foreach ($milestones['issues'] as $issue) {
            $list[] = "- ✅ Issue #{$issue['number']}: {$issue['title']}";
        }

        return $list;
    }

    /**
     * @param array{
     *     knowledge: Collection<int, Entry>,
     *     prs: array<int, array<string, mixed>>,
     *     issues: array<int, array<string, mixed>>
     * } $milestones
     */
    private function generateTitle(array $milestones): string
    {
        $date = now()->format('Y-m-d');
        $count = $milestones['knowledge']->count() + count($milestones['prs']) + count($milestones['issues']);

        if ($count > 0) {
            return "Daily Review - {$date} ({$count} milestones)";
        }

        return "Daily Review - {$date}";
    }

    /**
     * @return array<int, string>
     */
    private function generateTags(bool $isQuick): array
    {
        $tags = ['daily-review', now()->format('Y-m-d')];

        if ($isQuick) {
            $tags[] = 'quick';
        }

        return $tags;
    }

    private function getCurrentRepo(): ?string
    {
        $result = Process::run(['git', 'remote', 'get-url', 'origin']);

        if (! $result->successful()) {
            return null;
        }

        return trim($result->output());
    }

    private function getCurrentBranch(): ?string
    {
        try {
            $result = Process::run(['git', 'branch', '--show-current']);

            if (! $result->successful()) {
                return null;
            }

            return trim($result->output());
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getCurrentCommit(): ?string
    {
        try {
            $result = Process::run(['git', 'rev-parse', 'HEAD']);

            if (! $result->successful()) {
                return null;
            }

            return trim($result->output());
        } catch (\Throwable $e) {
            return null;
        }
    }
}
