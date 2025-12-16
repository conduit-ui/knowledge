<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Services\SemanticSearchService;
use Illuminate\Database\Eloquent\Builder;
use LaravelZero\Framework\Commands\Command;

class KnowledgeSearchCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'knowledge:search
                            {query? : Search term to find in title or content}
                            {--tag= : Filter by tag}
                            {--category= : Filter by category}
                            {--module= : Filter by module}
                            {--priority= : Filter by priority}
                            {--status= : Filter by status}
                            {--semantic : Use semantic search if available}';

    /**
     * @var string
     */
    protected $description = 'Search knowledge entries by keyword, tag, or category';

    public function handle(SemanticSearchService $searchService): int
    {
        $query = $this->argument('query');
        $tag = $this->option('tag');
        $category = $this->option('category');
        $module = $this->option('module');
        $priority = $this->option('priority');
        $status = $this->option('status');
        $useSemantic = $this->option('semantic');

        // Require at least one search parameter
        if ($query === null && $tag === null && $category === null && $module === null && $priority === null && $status === null) {
            $this->error('Please provide at least one search parameter.');

            return self::FAILURE;
        }

        // Use semantic search if requested and query is provided
        if ($useSemantic && is_string($query)) {
            $filters = array_filter([
                'tag' => is_string($tag) ? $tag : null,
                'category' => is_string($category) ? $category : null,
                'module' => is_string($module) ? $module : null,
                'priority' => is_string($priority) ? $priority : null,
                'status' => is_string($status) ? $status : null,
            ]);

            $results = $searchService->search($query, $filters);
        } else {
            // Fallback to traditional keyword search
            $results = Entry::query()
                ->when($query, function (Builder $q, mixed $search): void {
                    if (is_string($search)) {
                        $q->where(function (Builder $query) use ($search): void {
                            $query->where('title', 'like', "%{$search}%")
                                ->orWhere('content', 'like', "%{$search}%");
                        });
                    }
                })
                ->when($tag, function (Builder $q, mixed $tagValue): void {
                    if (is_string($tagValue)) {
                        $q->whereJsonContains('tags', $tagValue);
                    }
                })
                ->when($category, function (Builder $q, mixed $categoryValue): void {
                    if (is_string($categoryValue)) {
                        $q->where('category', $categoryValue);
                    }
                })
                ->when($module, function (Builder $q, mixed $moduleValue): void {
                    if (is_string($moduleValue)) {
                        $q->where('module', $moduleValue);
                    }
                })
                ->when($priority, function (Builder $q, mixed $priorityValue): void {
                    if (is_string($priorityValue)) {
                        $q->where('priority', $priorityValue);
                    }
                })
                ->when($status, function (Builder $q, mixed $statusValue): void {
                    if (is_string($statusValue)) {
                        $q->where('status', $statusValue);
                    }
                })
                ->orderBy('confidence', 'desc')
                ->orderBy('usage_count', 'desc')
                ->get();
        }

        if ($results->isEmpty()) {
            $this->line('No entries found.');

            return self::SUCCESS;
        }

        $this->info("Found {$results->count()} ".str('entry')->plural($results->count()));
        $this->newLine();

        foreach ($results as $entry) {
            $this->line("<fg=cyan>[{$entry->id}]</> <fg=green>{$entry->title}</>");
            $this->line('Category: '.($entry->category ?? 'N/A')." | Priority: {$entry->priority} | Confidence: {$entry->confidence}%");

            if ($entry->module) {
                $this->line("Module: {$entry->module}");
            }

            if ($entry->tags) {
                $this->line('Tags: '.implode(', ', $entry->tags));
            }

            $contentPreview = strlen($entry->content) > 100
                ? substr($entry->content, 0, 100).'...'
                : $entry->content;

            $this->line($contentPreview);
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
