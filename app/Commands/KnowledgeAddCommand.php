<?php

declare(strict_types=1);

namespace App\Commands;

use App\Exceptions\Qdrant\DuplicateEntryException;
use App\Services\GitContextService;
use App\Services\QdrantService;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class KnowledgeAddCommand extends Command
{
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
                            {--repo= : Repository URL or path}
                            {--branch= : Git branch name}
                            {--commit= : Git commit hash}
                            {--no-git : Skip automatic git context detection}
                            {--force : Skip duplicate detection and force creation}';

    protected $description = 'Add a new knowledge entry';

    private const VALID_CATEGORIES = ['debugging', 'architecture', 'testing', 'deployment', 'security'];

    private const VALID_PRIORITIES = ['critical', 'high', 'medium', 'low'];

    private const VALID_STATUSES = ['draft', 'validated', 'deprecated'];

    public function handle(GitContextService $gitService, QdrantService $qdrant): int
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

        // Validate required fields
        if ($content === null || $content === '') {
            error('The content field is required.');

            return self::FAILURE;
        }

        // Validate confidence
        if (! is_numeric($confidence) || $confidence < 0 || $confidence > 100) {
            error('The confidence must be between 0 and 100.');

            return self::FAILURE;
        }

        // Validate category
        if ($category !== null && ! in_array($category, self::VALID_CATEGORIES, true)) {
            error('Invalid category. Valid: '.implode(', ', self::VALID_CATEGORIES));

            return self::FAILURE;
        }

        // Validate priority
        if (! in_array($priority, self::VALID_PRIORITIES, true)) {
            error('Invalid priority. Valid: '.implode(', ', self::VALID_PRIORITIES));

            return self::FAILURE;
        }

        // Validate status
        if (! in_array($status, self::VALID_STATUSES, true)) {
            error('Invalid status. Valid: '.implode(', ', self::VALID_STATUSES));

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

        // Generate unique ID
        $id = Str::uuid()->toString();
        $data['id'] = $id;

        // Store in Qdrant with duplicate detection (unless --force is used)
        $checkDuplicates = ! $force;
        try {
            $success = spin(
                fn (): bool => $qdrant->upsert($data, 'default', $checkDuplicates),
                'Storing knowledge entry...'
            );

            if (! $success) {
                error('Failed to create knowledge entry');

                return self::FAILURE;
            }
        } catch (DuplicateEntryException $e) {
            if ($e->duplicateType === DuplicateEntryException::TYPE_HASH) {
                error("Duplicate content detected: This exact content already exists as entry '{$e->existingId}'");
            } else {
                $percentage = $e->similarityScore !== null ? round($e->similarityScore * 100, 1) : 95;
                warning("Potential duplicate detected: {$percentage}% similar to existing entry '{$e->existingId}'");
                error('Entry not created. Use --force to override duplicate detection.');
            }

            return self::FAILURE;
        }

        info('Knowledge entry created!');

        table(
            ['Field', 'Value'],
            [
                ['ID', $id],
                ['Title', $title],
                ['Category', $category ?? 'N/A'],
                ['Priority', $priority],
                ['Confidence', "{$confidence}%"],
                ['Tags', isset($data['tags']) ? implode(', ', $data['tags']) : 'N/A'],
            ]
        );

        return self::SUCCESS;
    }
}
