<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class ContextCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'context {--full : Show full detailed output}';

    /**
     * @var string
     */
    protected $description = 'Quick project status lookup showing current context';

    public function handle(): int
    {
        $this->info('Project Context');
        $this->newLine();

        $this->displayRecentIntents();
        $this->newLine();

        $this->displayGitContext();
        $this->newLine();

        $this->displayBlockers();
        $this->newLine();

        $this->displayPullRequests();
        $this->newLine();

        $this->displayIssues();

        return self::SUCCESS;
    }

    /**
     * Display the last 3 user intents
     */
    private function displayRecentIntents(): void
    {
        $this->comment('Recent User Intents:');

        /** @phpstan-ignore-next-line */
        $intents = Entry::query()
            ->whereJsonContains('tags', 'user-intent')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        if ($intents->isEmpty()) {
            $this->line('  No recent user intents');

            return;
        }

        foreach ($intents as $intent) {
            $this->line("  - {$intent->title}");
        }
    }

    /**
     * Display current git branch and status
     */
    private function displayGitContext(): void
    {
        $this->comment('Git Context:');

        $branchResult = Process::run(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);

        if ($branchResult->successful()) {
            $branch = trim($branchResult->output());
            $this->line("  Branch: {$branch}");
        } else {
            $this->line('  Branch: Not a git repository');
        }

        $statusResult = Process::run(['git', 'status', '--short']);

        if ($statusResult->successful()) {
            $status = trim($statusResult->output());
            if ($status !== '') {
                $this->line('  Status: Changes present');
            } else {
                $this->line('  Status: Clean');
            }
        }
    }

    /**
     * Display unresolved blockers
     */
    private function displayBlockers(): void
    {
        $this->comment('Unresolved Blockers:');

        /** @phpstan-ignore-next-line */
        $blockers = Entry::query()
            ->whereJsonContains('tags', 'blocker')
            ->where('status', '!=', 'validated')
            ->get();

        if ($blockers->isEmpty()) {
            $this->line('  No blockers');

            return;
        }

        foreach ($blockers as $blocker) {
            $this->line("  - {$blocker->title}");
        }
    }

    /**
     * Display open pull requests
     */
    private function displayPullRequests(): void
    {
        $this->comment('Open Pull Requests:');

        $result = Process::run(['gh', 'pr', 'list', '--state', 'open', '--json', 'number,title,url', '--limit', '5']);

        if (! $result->successful()) {
            $this->line('  Unable to fetch PRs (gh CLI not available)');

            return;
        }

        $output = trim($result->output());
        $prs = json_decode($output, true);

        if (! is_array($prs) || count($prs) === 0) {
            $this->line('  No open pull requests');

            return;
        }

        foreach ($prs as $pr) {
            $this->line("  #{$pr['number']}: {$pr['title']}");
        }
    }

    /**
     * Display open issues
     */
    private function displayIssues(): void
    {
        $this->comment('Open Issues:');

        $result = Process::run(['gh', 'issue', 'list', '--state', 'open', '--json', 'number,title,url', '--limit', '5']);

        if (! $result->successful()) {
            $this->line('  Unable to fetch issues (gh CLI not available)');

            return;
        }

        $output = trim($result->output());
        $issues = json_decode($output, true);

        if (! is_array($issues) || count($issues) === 0) {
            $this->line('  No open issues');

            return;
        }

        foreach ($issues as $issue) {
            $this->line("  #{$issue['number']}: {$issue['title']}");
        }
    }
}
