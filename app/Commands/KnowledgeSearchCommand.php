<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeSearchCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'search
                            {query? : Search term to find in title or content}
                            {--tag= : Filter by tag}
                            {--category= : Filter by category}
                            {--module= : Filter by module}
                            {--priority= : Filter by priority}
                            {--status= : Filter by status}
                            {--limit=20 : Maximum number of results}
                            {--semantic : Use semantic search if available}';

    /**
     * @var string
     */
    protected $description = 'Search knowledge entries by keyword, tag, or category';

    public function handle(QdrantService $qdrant): int
    {
        $query = $this->argument('query');
        $tag = $this->option('tag');
        $category = $this->option('category');
        $module = $this->option('module');
        $priority = $this->option('priority');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');
        $this->option('semantic');

        // Require at least one search parameter for entries
        if ($query === null && $tag === null && $category === null && $module === null && $priority === null && $status === null) {
            $this->error('Please provide at least one search parameter.');

            return self::FAILURE;
        }

        // Build filters for Qdrant search
        $filters = array_filter([
            'tag' => is_string($tag) ? $tag : null,
            'category' => is_string($category) ? $category : null,
            'module' => is_string($module) ? $module : null,
            'priority' => is_string($priority) ? $priority : null,
            'status' => is_string($status) ? $status : null,
        ]);

        // Use Qdrant for semantic search (always)
        $searchQuery = is_string($query) ? $query : '';
        $results = $qdrant->search($searchQuery, $filters, $limit);

        if ($results->isEmpty()) {
            $this->line('No entries found.');

            return self::SUCCESS;
        }

        $this->info("Found {$results->count()} ".str('entry')->plural($results->count()));
        $this->newLine();

        foreach ($results as $entry) {
            $id = $entry['id'] ?? 'unknown';
            $title = $entry['title'] ?? '';
            $category = $entry['category'] ?? 'N/A';
            $priority = $entry['priority'] ?? 'medium';
            $confidence = $entry['confidence'] ?? 0;
            $module = $entry['module'] ?? null;
            $tags = $entry['tags'] ?? [];
            $content = $entry['content'] ?? '';
            $score = $entry['score'] ?? 0.0;

            $this->line("<fg=cyan>[{$id}]</> <fg=green>{$title}</> <fg=yellow>(score: ".number_format($score, 2).')</>');
            $this->line('Category: '.$category." | Priority: {$priority} | Confidence: {$confidence}%");

            if ($module !== null) {
                $this->line("Module: {$module}");
            }

            if (isset($tags) && count($tags) > 0) {
                $this->line('Tags: '.implode(', ', $tags));
            }

            $contentPreview = strlen($content) > 100
                ? substr($content, 0, 100).'...'
                : $content;

            $this->line($contentPreview);
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
