<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class KnowledgeUpdateCommand extends Command
{
    protected $signature = 'update
                            {id : The ID of the knowledge entry to update}
                            {--title= : New title}
                            {--content= : New content}
                            {--category= : Category (debugging, architecture, testing, deployment, security)}
                            {--tags= : Comma-separated tags (replaces existing)}
                            {--add-tags= : Comma-separated tags to add to existing}
                            {--priority= : Priority level (critical, high, medium, low)}
                            {--confidence= : Confidence level (0-100)}
                            {--status= : Status (draft, validated, deprecated)}
                            {--module= : Module name}
                            {--source= : Source URL or reference}
                            {--evidence= : Supporting evidence or reference}';

    protected $description = 'Update an existing knowledge entry';

    private const VALID_CATEGORIES = ['debugging', 'architecture', 'testing', 'deployment', 'security'];

    private const VALID_PRIORITIES = ['critical', 'high', 'medium', 'low'];

    private const VALID_STATUSES = ['draft', 'validated', 'deprecated'];

    public function handle(QdrantService $qdrant): int
    {
        $idArg = $this->argument('id');
        // @codeCoverageIgnoreStart
        if (! is_string($idArg) || $idArg === '') {
            error('Invalid or missing ID argument');

            return self::FAILURE;
        }
        // @codeCoverageIgnoreEnd
        $id = $idArg;

        // Fetch existing entry
        $entry = spin(
            fn (): ?array => $qdrant->getById($id),
            'Fetching entry...'
        );

        if ($entry === null) {
            error("Entry not found: {$id}");

            return self::FAILURE;
        }

        // Track what's being updated
        $updates = [];

        // Update title if provided
        /** @var string|null $title */
        $title = is_string($this->option('title')) ? $this->option('title') : null;
        if ($title !== null && $title !== '') {
            $entry['title'] = $title;
            $updates[] = 'title';
        }

        // Update content if provided
        /** @var string|null $content */
        $content = is_string($this->option('content')) ? $this->option('content') : null;
        if ($content !== null && $content !== '') {
            $entry['content'] = $content;
            $updates[] = 'content';
        }

        // Update category if provided
        /** @var string|null $category */
        $category = is_string($this->option('category')) ? $this->option('category') : null;
        if ($category !== null) {
            if (! in_array($category, self::VALID_CATEGORIES, true)) {
                error('Invalid category. Valid: '.implode(', ', self::VALID_CATEGORIES));

                return self::FAILURE;
            }
            $entry['category'] = $category;
            $updates[] = 'category';
        }

        // Update priority if provided
        /** @var string|null $priority */
        $priority = is_string($this->option('priority')) ? $this->option('priority') : null;
        if ($priority !== null) {
            if (! in_array($priority, self::VALID_PRIORITIES, true)) {
                error('Invalid priority. Valid: '.implode(', ', self::VALID_PRIORITIES));

                return self::FAILURE;
            }
            $entry['priority'] = $priority;
            $updates[] = 'priority';
        }

        // Update status if provided
        /** @var string|null $status */
        $status = is_string($this->option('status')) ? $this->option('status') : null;
        if ($status !== null) {
            if (! in_array($status, self::VALID_STATUSES, true)) {
                error('Invalid status. Valid: '.implode(', ', self::VALID_STATUSES));

                return self::FAILURE;
            }
            $entry['status'] = $status;
            $updates[] = 'status';
        }

        // Update confidence if provided
        /** @var string|int|null $confidence */
        $confidence = $this->option('confidence');
        if ($confidence !== null) {
            if (! is_numeric($confidence) || (int) $confidence < 0 || (int) $confidence > 100) {
                error('Confidence must be between 0 and 100.');

                return self::FAILURE;
            }
            $entry['confidence'] = (int) $confidence;
            $updates[] = 'confidence';
        }

        // Replace tags if --tags provided
        /** @var string|null $tags */
        $tags = is_string($this->option('tags')) ? $this->option('tags') : null;
        if ($tags !== null) {
            $entry['tags'] = array_map('trim', explode(',', $tags));
            $updates[] = 'tags';
        }

        // Add tags if --add-tags provided
        /** @var string|null $addTags */
        $addTags = is_string($this->option('add-tags')) ? $this->option('add-tags') : null;
        if ($addTags !== null) {
            $existingTags = is_array($entry['tags']) ? $entry['tags'] : [];
            $newTags = array_map('trim', explode(',', $addTags));
            $entry['tags'] = array_values(array_unique(array_merge($existingTags, $newTags)));
            $updates[] = 'tags';
        }

        // @codeCoverageIgnoreStart
        // Update module if provided
        /** @var string|null $module */
        $module = is_string($this->option('module')) ? $this->option('module') : null;
        if ($module !== null) {
            $entry['module'] = $module;
            $updates[] = 'module';
        }

        // Update source if provided
        /** @var string|null $source */
        $source = is_string($this->option('source')) ? $this->option('source') : null;
        if ($source !== null) {
            $entry['source'] = $source;
            $updates[] = 'source';
        }

        // Update evidence if provided
        /** @var string|null $evidence */
        $evidence = is_string($this->option('evidence')) ? $this->option('evidence') : null;
        if ($evidence !== null) {
            $entry['evidence'] = $evidence;
            $updates[] = 'evidence';
        }
        // @codeCoverageIgnoreEnd

        if ($updates === []) {
            error('No updates provided. Use --help to see available options.');

            return self::FAILURE;
        }

        // Update timestamp
        $entry['updated_at'] = now()->toIso8601String();

        // Normalize nullable fields for upsert (remove nulls, keep defined values)
        /** @var array{id: string|int, title: string, content: string, tags?: array<string>, category?: string, module?: string, priority?: string, status?: string, confidence?: int, usage_count?: int, created_at?: string, updated_at?: string} $normalizedEntry */
        $normalizedEntry = array_filter($entry, fn ($value): bool => $value !== null);

        // Save to Qdrant
        $success = spin(
            fn (): bool => $qdrant->upsert($normalizedEntry),
            'Updating knowledge entry...'
        );

        // @codeCoverageIgnoreStart
        if (! $success) {
            error('Failed to update knowledge entry');

            return self::FAILURE;
        }
        // @codeCoverageIgnoreEnd

        info('Knowledge entry updated!');

        table(
            ['Field', 'Value'],
            [
                ['ID', (string) $entry['id']],
                ['Title', $entry['title']],
                ['Updated', implode(', ', $updates)],
                ['Category', $entry['category'] ?? 'N/A'],
                ['Priority', $entry['priority'] ?? 'N/A'],
                ['Confidence', ($entry['confidence'] ?? 0).'%'],
                ['Status', $entry['status'] ?? 'N/A'],
                ['Tags', is_array($entry['tags']) ? implode(', ', $entry['tags']) : 'N/A'],
            ]
        );

        return self::SUCCESS;
    }
}
