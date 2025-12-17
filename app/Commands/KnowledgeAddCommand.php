<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Services\GitContextService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeAddCommand extends Command
{
    /**
     * @var string
     */
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
                            {--no-git : Skip automatic git context detection}';

    /**
     * @var string
     */
    protected $description = 'Add a new knowledge entry';

    private const VALID_CATEGORIES = ['debugging', 'architecture', 'testing', 'deployment', 'security'];

    private const VALID_PRIORITIES = ['critical', 'high', 'medium', 'low'];

    private const VALID_STATUSES = ['draft', 'validated', 'deprecated'];

    public function handle(GitContextService $gitService): int
    {
        $title = $this->argument('title');
        $content = $this->option('content');
        $category = $this->option('category');
        $tags = $this->option('tags');
        $module = $this->option('module');
        $priority = $this->option('priority');
        $confidence = $this->option('confidence');
        $source = $this->option('source');
        $ticket = $this->option('ticket');
        $author = $this->option('author');
        $status = $this->option('status');
        $repo = $this->option('repo');
        $branch = $this->option('branch');
        $commit = $this->option('commit');
        $noGit = $this->option('no-git');

        // Validate required fields
        if ($content === null || $content === '') {
            $this->error('The content field is required.');

            return self::FAILURE;
        }

        // Validate confidence
        if (! is_numeric($confidence) || $confidence < 0 || $confidence > 100) {
            $this->error('The confidence must be between 0 and 100.');

            return self::FAILURE;
        }

        // Validate category
        if ($category !== null && ! in_array($category, self::VALID_CATEGORIES, true)) {
            $this->error('The selected category is invalid. Valid options: '.implode(', ', self::VALID_CATEGORIES));

            return self::FAILURE;
        }

        // Validate priority
        if (! in_array($priority, self::VALID_PRIORITIES, true)) {
            $this->error('The selected priority is invalid. Valid options: '.implode(', ', self::VALID_PRIORITIES));

            return self::FAILURE;
        }

        // Validate status
        if (! in_array($status, self::VALID_STATUSES, true)) {
            $this->error('The selected status is invalid. Valid options: '.implode(', ', self::VALID_STATUSES));

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

            // Only use auto-detected values if not manually provided
            $data['repo'] = $repo ?? $gitContext['repo'];
            $data['branch'] = $branch ?? $gitContext['branch'];
            $data['commit'] = $commit ?? $gitContext['commit'];
            $data['author'] = $author ?? $gitContext['author'];
        } else {
            // Use manually provided values or null
            $data['repo'] = $repo;
            $data['branch'] = $branch;
            $data['commit'] = $commit;
            $data['author'] = $author;
        }

        $entry = Entry::create($data);

        $this->info("Knowledge entry created successfully with ID: {$entry->id}");
        $this->line("Title: {$entry->title}");
        $this->line('Category: '.($entry->category ?? 'N/A'));
        $this->line("Priority: {$entry->priority}");
        $this->line("Confidence: {$entry->confidence}%");

        if ($entry->tags) {
            $this->line('Tags: '.implode(', ', $entry->tags));
        }

        return self::SUCCESS;
    }
}
