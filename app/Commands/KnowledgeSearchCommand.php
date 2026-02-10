<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\ResolvesProject;
use App\Services\EntryMetadataService;
use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeSearchCommand extends Command
{
    use ResolvesProject;

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
                            {--semantic : Use semantic search if available}
                            {--include-superseded : Include superseded entries in results}
                            {--project= : Override project namespace}
                            {--global : Search across all projects}';

    /**
     * @var string
     */
    protected $description = 'Search knowledge entries by keyword, tag, or category';

    public function handle(QdrantService $qdrant, EntryMetadataService $metadata): int
    {
        $query = $this->argument('query');
        $tag = $this->option('tag');
        $category = $this->option('category');
        $module = $this->option('module');
        $priority = $this->option('priority');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');
        $this->option('semantic');
        $includeSuperseded = (bool) $this->option('include-superseded');

        // Require at least one search parameter for entries
        if ($query === null && $tag === null && $category === null && $module === null && $priority === null && $status === null) {
            $this->error('Please provide at least one search parameter.');

            return self::FAILURE;
        }

        // Build filters for search
        $filters = array_filter([
            'tag' => is_string($tag) ? $tag : null,
            'category' => is_string($category) ? $category : null,
            'module' => is_string($module) ? $module : null,
            'priority' => is_string($priority) ? $priority : null,
            'status' => is_string($status) ? $status : null,
        ]);

        if ($includeSuperseded) {
            $filters['include_superseded'] = true;
        }

        // Use project-aware search
        $searchQuery = is_string($query) ? $query : '';

        if ($this->isGlobal()) {
            $collections = $qdrant->listCollections();
            $results = collect();

            foreach ($collections as $collection) {
                $projectName = str_replace('knowledge_', '', $collection);
                $projectResults = $qdrant->search($searchQuery, $filters, $limit, $projectName);
                $results = $results->merge($projectResults->map(fn (array $entry): array => array_merge($entry, ['_project' => $projectName])));
            }

            $results = $results->sortByDesc('score')->take($limit)->values();
        } else {
            $project = $this->resolveProject();
            $results = $qdrant->search($searchQuery, $filters, $limit, $project);
        }

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
            $supersededBy = $entry['superseded_by'] ?? null;

            $isStale = $metadata->isStale($entry);
            $effectiveConfidence = $metadata->calculateEffectiveConfidence($entry);
            $confidenceLevel = $metadata->confidenceLevel($effectiveConfidence);

            $projectLabel = isset($entry['_project']) ? " <fg=magenta>[{$entry['_project']}]</>" : '';
            $titleLine = "<fg=cyan>[{$id}]</> <fg=green>{$title}</> <fg=yellow>(score: ".number_format($score, 2).')</>'.$projectLabel;
            if ($supersededBy !== null) {
                $titleLine .= ' <fg=red>[SUPERSEDED]</>';
            }

            $this->line($titleLine);

            if ($isStale) {
                $days = $metadata->daysSinceVerification($entry);
                $this->line("<fg=red>[STALE] Not verified in {$days} days - confidence degraded to {$effectiveConfidence}% ({$confidenceLevel})</>");
            }

            $this->line('Category: '.$category." | Priority: {$priority} | Confidence: {$effectiveConfidence}% ({$confidenceLevel})");

            if ($supersededBy !== null) {
                $this->line("<fg=gray>Superseded by: {$supersededBy}</>");
            }

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
