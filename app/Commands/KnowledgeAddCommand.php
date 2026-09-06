<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\ResolvesProject;
use App\Exceptions\Qdrant\DuplicateEntryException;
use App\Services\EnhancementQueueService;
use App\Services\GitContextService;
use App\Services\QdrantService;
use App\Services\WriteGateService;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class KnowledgeAddCommand extends Command
{
    use ResolvesProject;

    protected $signature = 'add
                            {title : The title of the knowledge entry}
                            {--content= : The content of the knowledge entry}
                            {--category= : Category (debugging, architecture, testing, deployment, security)}
                            {--tags= : Comma-separated tags}
                            {--module= : Module name}
                            {--priority=medium : Priority level (critical, high, medium, low)}
                            {--confidence=50 : Confidence level (0-100)}
                            {--source= : Source URL or reference}
                            {--ticket= : Related ticket number}
                            {--author= : Author name}
                            {--status=draft : Status (draft, validated, deprecated)}
                            {--evidence= : Supporting evidence or reference for this entry}
                            {--repo= : Repository URL or path}
                            {--branch= : Git branch name}
                            {--commit= : Git commit hash}
                            {--no-git : Skip automatic git context detection}
                            {--force : Skip write gate and duplicate detection}
                            {--skip-enhance : Skip queueing for Ollama enhancement}
                            {--quiet : Suppress stderr messages}
                            {--project= : Override project namespace}
                            {--global : Search across all projects}';

    protected $description = 'Add a new knowledge entry';

    private const VALID_CATEGORIES = ['debugging', 'architecture', 'testing', 'deployment', 'security'];

    private const VALID_PRIORITIES = ['critical', 'high', 'medium', 'low'];

    private const VALID_STATUSES = ['draft', 'validated', 'deprecated'];

    public function handle(GitContextService $gitService, QdrantService $qdrant, WriteGateService $writeGate, EnhancementQueueService $enhancementQueue): int
    {
        /** @var string $title */
        $title = (string) $this->argument('title');
        /** @var string|null $content */
        $content = is_string($this->option('content')) ? $this->option('content') : null;
        /** @var string|null $category */
        $category = is_string($this->option('category')) ? $this->option('category') : null;
        /** @var string|null $tags */
        $tags = is_string($this->option('tags')) ? $this->option('tags') : null;
        /** @var string|null $module */
        $module = is_string($this->option('module')) ? $this->option('module') : null;
        /** @var string $priority */
        $priority = is_string($this->option('priority')) ? $this->option('priority') : 'medium';
        /** @var int|string $confidence */
        $confidence = $this->option('confidence') ?? 50;
        /** @var string|null $source */
        $source = is_string($this->option('source')) ? $this->option('source') : null;
        /** @var string|null $ticket */
        $ticket = is_string($this->option('ticket')) ? $this->option('ticket') : null;
        /** @var string|null $author */
        $author = is_string($this->option('author')) ? $this->option('author') : null;
        /** @var string $status */
        $status = is_string($this->option('status')) ? $this->option('status') : 'draft';
        /** @var string|null $evidence */
        $evidence = is_string($this->option('evidence')) ? $this->option('evidence') : null;
        /** @var string|null $repo */
        $repo = is_string($this->option('repo')) ? $this->option('repo') : null;
        /** @var string|null $branch */
        $branch = is_string($this->option('branch')) ? $this->option('branch') : null;
        /** @var string|null $commit */
        $commit = is_string($this->option('commit')) ? $this->option('commit') : null;
        /** @var bool $noGit */
        $noGit = (bool) $this->option('no-git');
        /** @var bool $force */
        $force = (bool) $this->option('force');
        /** @var bool $skipEnhance */
        $skipEnhance = (bool) $this->option('skip-enhance');
        /** @var bool $quiet */
        $quiet = (bool) $this->option('quiet');

        // Validate required fields
        if ($content === null || $content === '') {
            $this->writeToStderr('The content field is required.', $quiet);

            return self::FAILURE;
        }

        // Validate confidence
        if (! is_numeric($confidence) || $confidence < 0 || $confidence > 100) {
            $this->writeToStderr('The confidence must be between 0 and 100.', $quiet);

            return self::FAILURE;
        }

        // Validate category - unknown category is not an error, just warn
        if ($category !== null && ! in_array($category, self::VALID_CATEGORIES, true)) {
            $this->writeToStderr('Unknown category "'.$category.'". Ignoring category.', $quiet);
            $category = null;
        }

        // Validate priority
        if (! in_array($priority, self::VALID_PRIORITIES, true)) {
            $this->writeToStderr('Invalid priority. Valid: '.implode(', ', self::VALID_PRIORITIES), $quiet);

            return self::FAILURE;
        }

        // Validate status
        if (! in_array($status, self::VALID_STATUSES, true)) {
            $this->writeToStderr('Invalid status. Valid: '.implode(', ', self::VALID_STATUSES), $quiet);

            return self::FAILURE;
        }

        $data = [
            'title' => $title,
            'content' => $content,
            'category' => $category,
            'module' => $module,
            'priority' => $priority,
            'confidence' => (int) $confidence,
            'source' => $source,
            'ticket' => $ticket,
            'status' => $status,
            'evidence' => $evidence,
            'last_verified' => now()->toIso8601String(),
        ];

        if (is_string($tags) && $tags !== '') {
            $data['tags'] = array_map('trim', explode(',', $tags));
        }

        // Auto-populate git context unless --no-git is specified
        if ($noGit !== true && $gitService->isGitRepository()) {
            $gitContext = $gitService->getContext();
            $data['repo'] = $repo ?? $gitContext['repo'];
            $data['branch'] = $branch ?? $gitContext['branch'];
            $data['commit'] = $commit ?? $gitContext['commit'];
            $data['author'] = $author ?? $gitContext['author'];
        } else {
            $data['repo'] = $repo;
            $data['branch'] = $branch;
            $data['commit'] = $commit;
            $data['author'] = $author;
        }

        // Write Gate: evaluate entry quality before persistence
        if (! $force) {
            $gateResult = $writeGate->evaluate($data);
            if (! $gateResult['passed']) {
                $this->writeToStderr('Write gate rejected entry: '.$gateResult['reason'], $quiet);

                return self::FAILURE;
            }
        }

        // Generate unique ID
        $id = Str::uuid()->toString();
        $data['id'] = $id;

        // Store in Qdrant with duplicate detection (unless --force is used)
        $checkDuplicates = ! $force;
        try {
            $success = spin(
                fn (): bool => $qdrant->upsert($data, $this->resolveProject(), $checkDuplicates),
                'Storing knowledge entry...'
            );

            if (! $success) {
                $this->writeToStderr('Failed to create knowledge entry', $quiet);

                return self::FAILURE;
            }
        } catch (DuplicateEntryException $e) {
            return $this->handleDuplicate($e, $data, (int) $confidence, $quiet);
        }

        // Queue for Ollama enhancement unless skipped
        if (! $skipEnhance && (bool) config('search.ollama.enabled', true)) {
            $enhancementQueue->queue($data, $this->resolveProject());
        }

        info('Knowledge entry created!');

        $this->displayEntryTable($id, $title, $category, $priority, (int) $confidence, $data['tags'] ?? null);

        return self::SUCCESS;
    }

    /**
     * Handle a duplicate entry.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleDuplicate(
        DuplicateEntryException $e,
        array $data,
        int $confidence,
        bool $quiet
    ): int {
        $existingId = $e->existingId;

        if ($e->duplicateType === DuplicateEntryException::TYPE_HASH) {
            $this->writeToStderr("Duplicate content detected: This exact content already exists as entry '{$existingId}'", $quiet);

            return self::SUCCESS;
        }

        $percentage = $e->similarityScore !== null ? round($e->similarityScore * 100, 1) : 95;
        $this->writeToStderr("Potential duplicate detected: {$percentage}% similar to existing entry '{$existingId}'", $quiet);

        // Require confirmation when confidence is low (below 70)
        if ($confidence < 70) {
            $this->writeToStderr("Low confidence ({$confidence}%) - please confirm this supersedes the existing entry.", $quiet);
        }

        $shouldSupersede = $this->confirm(
            "Supersede existing entry '{$existingId}' with this new entry?",
            $confidence >= 70
        );

        if (! $shouldSupersede) {
            $this->writeToStderr('Entry not created. Existing knowledge preserved.', $quiet);

            return self::FAILURE;
        }

        // Force-create the new entry (skip duplicate check)
        $success = spin(
            fn (): bool => $qdrant->upsert($data, 'default', false),
            'Storing new knowledge entry...'
        );

        if (! $success) {
            $this->writeToStderr('Failed to create knowledge entry', $quiet);

            return self::FAILURE;
        }

        // Mark the old entry as superseded
        $reason = "Superseded by newer entry with {$percentage}% similarity";
        $marked = $qdrant->markSuperseded($existingId, $data['id'], $reason);

        if (! $marked) {
            $this->writeToStderr('New entry created but failed to mark old entry as superseded.', $quiet);
        }

        info('Knowledge entry created! Previous entry marked as superseded.');

        /** @var string $id */
        $id = $data['id'];
        /** @var string $title */
        $title = $data['title'];
        /** @var string|null $category */
        $category = $data['category'] ?? null;
        /** @var string $priority */
        $priority = $data['priority'] ?? 'medium';

        $this->displayEntryTable($id, $title, $category, $priority, $confidence, $data['tags'] ?? null);

        return self::SUCCESS;
    }

    private function writeToStderr(string $message, bool $quiet): void
    {
        if (! $quiet) {
            fwrite(STDERR, $message.PHP_EOL);
        }
    }

    /**
     * Display the entry summary table.
     *
     * @param  array<string>|null  $tags
     */
    private function displayEntryTable(
        string $id,
        string $title,
        ?string $category,
        string $priority,
        int $confidence,
        ?array $tags
    ): void {
        table(
            ['Field', 'Value'],
            [
                ['ID', $id],
                ['Title', $title],
                ['Category', $category ?? 'N/A'],
                ['Priority', $priority],
                ['Confidence', "{$confidence}%"],
                ['Tags', $tags !== null ? implode(', ', $tags) : 'N/A'],
            ]
        );
    }
}
