<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\DailyLogService;
use App\Services\GitContextService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

class StageCommand extends Command
{
    protected $signature = 'stage
                            {title : The title of the knowledge entry}
                            {--content= : The content of the knowledge entry}
                            {--section=Notes : Section (Decisions, Corrections, Commitments, Notes)}
                            {--category= : Category (debugging, architecture, testing, deployment, security)}
                            {--tags= : Comma-separated tags}
                            {--priority=medium : Priority level (critical, high, medium, low)}
                            {--confidence=50 : Confidence level (0-100)}
                            {--source= : Source URL or reference}
                            {--ticket= : Related ticket number}
                            {--author= : Author name}
                            {--no-git : Skip automatic git context detection}';

    protected $description = 'Stage a knowledge entry in the daily log for review before permanent storage';

    private const VALID_SECTIONS = ['Decisions', 'Corrections', 'Commitments', 'Notes'];

    private const VALID_CATEGORIES = ['debugging', 'architecture', 'testing', 'deployment', 'security'];

    private const VALID_PRIORITIES = ['critical', 'high', 'medium', 'low'];

    public function handle(DailyLogService $dailyLog, GitContextService $gitService): int
    {
        $titleArg = $this->argument('title');
        /** @var string $title */
        $title = is_string($titleArg) ? $titleArg : '';
        /** @var string|null $content */
        $content = is_string($this->option('content')) ? $this->option('content') : null;
        /** @var string $section */
        $section = is_string($this->option('section')) ? $this->option('section') : 'Notes';
        /** @var string|null $category */
        $category = is_string($this->option('category')) ? $this->option('category') : null;
        /** @var string|null $tags */
        $tags = is_string($this->option('tags')) ? $this->option('tags') : null;
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
        /** @var bool $noGit */
        $noGit = (bool) $this->option('no-git');

        if ($content === null || $content === '') {
            error('The content field is required.');

            return self::FAILURE;
        }

        if (! is_numeric($confidence) || $confidence < 0 || $confidence > 100) {
            error('The confidence must be between 0 and 100.');

            return self::FAILURE;
        }

        if (! in_array($section, self::VALID_SECTIONS, true)) {
            error('Invalid section. Valid: '.implode(', ', self::VALID_SECTIONS));

            return self::FAILURE;
        }

        if ($category !== null && ! in_array($category, self::VALID_CATEGORIES, true)) {
            error('Invalid category. Valid: '.implode(', ', self::VALID_CATEGORIES));

            return self::FAILURE;
        }

        if (! in_array($priority, self::VALID_PRIORITIES, true)) {
            error('Invalid priority. Valid: '.implode(', ', self::VALID_PRIORITIES));

            return self::FAILURE;
        }

        // Auto-detect author from git if not specified
        if ($author === null && ! $noGit && $gitService->isGitRepository()) {
            $gitContext = $gitService->getContext();
            $author = $gitContext['author'] ?? null;
        }

        $entry = [
            'title' => $title,
            'content' => $content,
            'section' => $section,
            'category' => $category,
            'priority' => $priority,
            'confidence' => (int) $confidence,
            'source' => $source,
            'ticket' => $ticket,
            'author' => $author,
        ];

        if (is_string($tags) && $tags !== '') {
            $entry['tags'] = array_map('trim', explode(',', $tags));
        }

        $id = $dailyLog->stage($entry);

        info('Entry staged in daily log!');

        table(
            ['Field', 'Value'],
            [
                ['ID', $id],
                ['Title', $title],
                ['Section', $section],
                ['Category', $category ?? 'N/A'],
                ['Priority', $priority],
                ['Confidence', "{$confidence}%"],
            ]
        );

        return self::SUCCESS;
    }
}
