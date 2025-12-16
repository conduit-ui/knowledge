<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
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
                            {--status= : Filter by status}';

    /**
     * @var string
     */
    protected $description = 'Search knowledge entries by keyword, tag, or category';

    public function handle(): int
    {
        $query = $this->argument('query');
        $tag = $this->option('tag');
        $category = $this->option('category');
        $module = $this->option('module');
        $priority = $this->option('priority');
        $status = $this->option('status');

        // Require at least one search parameter
        if (! $query && ! $tag && ! $category && ! $module && ! $priority && ! $status) {
            $this->error('Please provide at least one search parameter.');

            return self::FAILURE;
        }

        $results = Entry::query()
            ->when($query, function (Builder $q, string $search): void {
                $q->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->when($tag, function (Builder $q, string $tagValue): void {
                $q->whereJsonContains('tags', $tagValue);
            })
            ->when($category, function (Builder $q, string $categoryValue): void {
                $q->where('category', $categoryValue);
            })
            ->when($module, function (Builder $q, string $moduleValue): void {
                $q->where('module', $moduleValue);
            })
            ->when($priority, function (Builder $q, string $priorityValue): void {
                $q->where('priority', $priorityValue);
            })
            ->when($status, function (Builder $q, string $statusValue): void {
                $q->where('status', $statusValue);
            })
            ->orderBy('confidence', 'desc')
            ->orderBy('usage_count', 'desc')
            ->get();

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
