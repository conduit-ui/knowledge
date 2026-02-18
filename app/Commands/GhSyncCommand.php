<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\ResolvesProject;
use App\Services\GitContextService;
use App\Services\OpenCodeService;
use App\Services\QdrantService;
use ConduitUi\GitHubConnector\Connector;
use ConduitUI\Pr\DataTransferObjects\Comment;
use ConduitUI\Pr\DataTransferObjects\File;
use ConduitUI\Pr\PullRequest;
use ConduitUI\Pr\PullRequests;
use ConduitUI\Pr\Services\GitHubPrService;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class GhSyncCommand extends Command
{
    use ResolvesProject;

    protected $signature = 'gh:sync
                            {--repo=* : One or more GitHub repos (owner/name)}
                            {--since= : ISO timestamp cutoff (default: 7 days ago)}
                            {--limit=50 : Max items per type}
                            {--dry-run : Show what would be stored without writing}
                            {--enhance-opencode : Enrich entries via OpenCode when configured}
                            {--project= : Override project namespace}
                            {--global : Search across all projects}';

    protected $description = 'Sync GitHub issues and pull requests into knowledge base';

    public function handle(QdrantService $qdrant, GitContextService $git, OpenCodeService $openCode): int
    {
        $this->initConduit();

        $repos = $this->resolveRepos($git);
        if ($repos === []) {
            error('Provide --repo or run inside a git repo with an origin URL.');

            return self::FAILURE;
        }

        $since = $this->resolveSince();
        $limit = $this->resolveLimit();
        $project = $this->resolveProject();
        $dryRun = (bool) $this->option('dry-run');
        $useOpenCode = (bool) $this->option('enhance-opencode');

        $summary = ['fetched' => 0, 'new' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($repos as $repo) {
            info("Syncing {$repo} since {$since} (limit {$limit})".($dryRun ? ' [dry-run]' : ''));

            $prs = spin(
                fn (): array => $this->fetchPullRequests($repo, $since, $limit),
                'Fetching pull requests...'
            );

            $entries = [];

            foreach ($prs as $pr) {
                $entry = $this->buildEntry($repo, $pr);
                if ($useOpenCode) {
                    $entry = $openCode->enrich($entry);
                }
                $this->collectEntry($entries, $entry, $qdrant, $project, $summary);
            }

            $summary['fetched'] += count($entries);

            if ($dryRun) {
                $this->renderDryRun($entries);

                continue;
            }

            foreach ($entries as $entry) {
                $stored = spin(
                    fn (): bool => $qdrant->upsert($entry, $project, false),
                    'Storing entry '.$entry['id']
                );

                if ($stored) {
                    continue;
                }

                $summary['failed']++;
            }
        }

        table(
            ['fetched', 'new', 'updated', 'skipped', 'failed'],
            [[
                (string) $summary['fetched'],
                (string) $summary['new'],
                (string) $summary['updated'],
                (string) $summary['skipped'],
                (string) $summary['failed'],
            ]]
        );

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function initConduit(): void
    {
        $token = config('github.token') ?? env('GITHUB_TOKEN');
        if ($token === null || $token === '') {
            $envToken = getenv('GH_TOKEN');
            $token = $envToken !== false && $envToken !== '' ? $envToken : $this->getGhToken();
        }

        PullRequests::setService(new GitHubPrService(new Connector($token)));
    }

    private function getGhToken(): string
    {
        $process = new \Symfony\Component\Process\Process(['gh', 'auth', 'token']);
        $process->run();

        return trim($process->getOutput());
    }

    /**
     * @return array<int, PullRequest>
     */
    private function fetchPullRequests(string $repo, string $since, int $limit): array
    {
        $sinceDate = new \DateTime($since);

        $allPrs = \ConduitUI\Pr\PullRequests::for($repo)
            ->all()
            ->orderBy('updated', 'desc')
            ->take($limit)
            ->get();

        return array_values(array_filter(
            $allPrs,
            fn (PullRequest $pr) => $pr->data->updatedAt >= $sinceDate
        ));
    }

    /**
     * @return array{id: string, title: string, content: string, tags: list<string>, category: string, priority: string, status: string, confidence: int, created_at: string, updated_at: string, last_verified: string, evidence: string}
     */
    private function buildEntry(string $repo, PullRequest $pr): array
    {
        $data = $pr->data;
        $number = $data->number;
        $title = $data->title;
        $body = $data->body ?? '';
        $state = $data->state;
        $labels = array_values(array_map(
            fn ($label) => $label->name,
            $data->labels ?? []
        ));
        $url = $data->htmlUrl;
        $author = $data->user->login;
        $created = $data->createdAt->format(\DateTimeInterface::ATOM);
        $updated = $data->updatedAt->format(\DateTimeInterface::ATOM);
        $mergedAt = $data->mergedAt !== null ? $data->mergedAt->format(\DateTimeInterface::ATOM) : '';
        $closedAt = $data->closedAt !== null ? $data->closedAt->format(\DateTimeInterface::ATOM) : '';
        $baseRef = $data->base->ref ?? '';
        $headRef = $data->head->ref ?? '';

        $comments = $pr->comments();
        $files = $pr->files();

        $commentBlock = $this->formatComments($comments);
        $fileSummary = $this->formatFiles($files);

        $contentParts = [
            "Repository: {$repo}",
            "PR #{$number} ({$state}) by {$author}",
            "Branch: {$baseRef} <= {$headRef}",
            'Labels: '.($labels === [] ? 'none' : implode(', ', $labels)),
            "Created: {$created}",
            "Updated: {$updated}",
            $mergedAt !== '' ? "Merged: {$mergedAt}" : '',
            $closedAt !== '' ? "Closed: {$closedAt}" : '',
            "URL: {$url}",
            '',
            $body !== '' ? $body : '(no description)',
            $fileSummary,
            $commentBlock,
        ];

        $fingerprint = $this->fingerprint([
            'repo' => $repo,
            'number' => $number,
            'title' => $title,
            'body' => $body,
            'state' => $state,
            'labels' => $labels,
            'files' => $fileSummary,
            'comments' => $commentBlock,
            'updated' => $updated,
        ]);

        $tags = $this->baseTags($repo, 'pr', $state, $labels, $fingerprint);

        $id = hash('md5', "pr_{$repo}_{$number}", false);

        return [
            'id' => $id,
            'title' => $title !== '' ? $title : "PR #{$number}",
            'content' => implode("\n", array_filter($contentParts, fn (string $part): bool => $part !== '')),
            'tags' => $tags,
            'category' => 'architecture',
            'priority' => 'medium',
            'status' => 'validated',
            'confidence' => 70,
            'created_at' => $created,
            'updated_at' => $updated,
            'last_verified' => now()->toIso8601String(),
            'evidence' => $url,
        ];
    }

    /**
     * @param  array<int, Comment>  $comments
     */
    private function formatComments(array $comments): string
    {
        if ($comments === []) {
            return 'Comments: none';
        }

        $lines = ['Comments:'];
        foreach ($comments as $comment) {
            $author = $comment->user?->login ?? $comment->authorAssociation ?? 'unknown';
            $created = $comment->createdAt !== null ? $comment->createdAt->format('Y-m-d H:i:s') : '';
            $body = $this->truncate($comment->body ?? '');

            $lines[] = sprintf('- %s @ %s: %s', $author, $created, $body);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, File>  $files
     */
    private function formatFiles(array $files): string
    {
        if ($files === []) {
            return 'Files: none';
        }

        $lines = ['Files:'];
        foreach ($files as $file) {
            $path = $file->filename ?? '';
            $status = $file->status ?? '';
            $additions = $file->additions ?? 0;
            $deletions = $file->deletions ?? 0;
            $lines[] = sprintf('- %s (%s, +%d/-%d)', $path, $status, $additions, $deletions);
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function resolveRepos(GitContextService $git): array
    {
        $rawRepos = $this->option('repo');
        $repos = [];

        if (is_array($rawRepos)) {
            foreach ($rawRepos as $value) {
                if (is_string($value) && $value !== '') {
                    $repos[] = $value;
                }
            }
        }

        if ($repos !== []) {
            return array_values(array_unique($repos));
        }

        $url = $git->getRepositoryUrl();
        if (! is_string($url)) {
            return [];
        }

        $slug = $this->extractRepoSlug($url);

        return $slug !== null ? [$slug] : [];
    }

    private function resolveSince(): string
    {
        $since = $this->option('since');
        if (is_string($since) && $since !== '') {
            return $since;
        }

        return now()->subDays(7)->toIso8601String();
    }

    private function resolveLimit(): int
    {
        $limit = $this->option('limit');
        if (is_numeric($limit)) {
            return max(1, (int) $limit);
        }

        return 50;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function renderDryRun(array $entries): void
    {
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                $entry['id'],
                $entry['title'],
                implode(',', $entry['tags'] ?? []),
            ];
        }

        table(['id', 'title', 'tags'], $rows === [] ? [['-', '-', '-']] : $rows);
    }

    private function extractRepoSlug(string $url): ?string
    {
        $url = trim($url);

        if (str_starts_with($url, 'git@')) {
            $parts = explode(':', $url, 2);
            if (isset($parts[1])) {
                $path = $parts[1];

                return rtrim(str_replace('.git', '', $path), "\n");
            }
        }

        if (str_contains($url, 'github.com')) {
            $path = parse_url($url, PHP_URL_PATH);
            if (is_string($path)) {
                $trimmed = trim($path, '/');

                return rtrim(str_replace('.git', '', $trimmed), '/');
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array{fetched: int, new: int, updated: int, skipped: int, failed: int}  $summary
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function collectEntry(array &$entries, array $entry, QdrantService $qdrant, string $project, array &$summary): void
    {
        $existing = $qdrant->getById($entry['id'], $project);
        $fingerprint = $this->extractFingerprint($entry['tags'] ?? []);
        $existingFingerprint = $existing !== null ? $this->extractFingerprint($existing['tags'] ?? []) : null;

        if ($existingFingerprint !== null && $fingerprint === $existingFingerprint) {
            $summary['skipped']++;

            return;
        }

        $entries[] = $entry;
        $summary[$existing === null ? 'new' : 'updated']++;
    }

    /**
     * @param  list<string>  $tags
     */
    private function extractFingerprint(array $tags): ?string
    {
        foreach ($tags as $tag) {
            if (str_starts_with($tag, 'fingerprint:')) {
                return substr($tag, strlen('fingerprint:'));
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fingerprint(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<string>  $labels
     * @return list<string>
     */
    private function baseTags(string $repo, string $type, string $state, array $labels, string $fingerprint): array
    {
        $tags = ['github', "repo:{$repo}", "type:{$type}", "state:{$state}", 'fingerprint:'.$fingerprint];

        foreach ($labels as $label) {
            if ($label !== '') {
                $tags[] = 'label:'.$label;
            }
        }

        return array_values(array_unique($tags));
    }

    private function truncate(string $text, int $max = 240): string
    {
        return Str::limit(preg_replace('/\s+/', ' ', $text) ?? '', $max);
    }
}
