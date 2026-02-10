<?php

declare(strict_types=1);

namespace App\Commands;

use App\Enums\SearchTier;
use App\Services\EntryMetadataService;
use App\Services\TieredSearchService;
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
                            {--semantic : Use semantic search if available}
                            {--include-superseded : Include superseded entries in results}
                            {--tier= : Force searching a specific tier (working, recent, structured, archive)}';

    /**
     * @var string
     */
    protected $description = 'Search knowledge entries by keyword, tag, or category';

    public function handle(TieredSearchService $tieredSearch, EntryMetadataService $metadata): int
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
        $tierOption = $this->option('tier');

        // Require at least one search parameter for entries
        if ($query === null && $tag === null && $category === null && $module === null && $priority === null && $status === null) {
            $this->error('Please provide at least one search parameter.');

            return self::FAILURE;
        }

        // Validate tier option
        $forceTier = null;
        if (is_string($tierOption) && $tierOption !== '') {
            $forceTier = SearchTier::tryFrom($tierOption);
            if ($forceTier === null) {
                $validTiers = implode(', ', array_map(fn (SearchTier $t): string => $t->value, SearchTier::cases()));
                $this->error("Invalid tier '{$tierOption}'. Valid tiers: {$validTiers}");

                return self::FAILURE;
            }
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

        // Use tiered search
        $searchQuery = is_string($query) ? $query : '';
        $results = $tieredSearch->search($searchQuery, $filters, $limit, $forceTier);

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
            $tierLabel = $entry['tier_label'] ?? null;
            $tieredScore = $entry['tiered_score'] ?? null;

            $isStale = $metadata->isStale($entry);
            $effectiveConfidence = $metadata->calculateEffectiveConfidence($entry);
            $confidenceLevel = $metadata->confidenceLevel($effectiveConfidence);

            $scoreDisplay = 'score: '.number_format($score, 2);
            if ($tieredScore !== null) {
                $scoreDisplay .= ' | ranked: '.number_format((float) $tieredScore, 2);
            }

            $tierDisplay = '';
            if (is_string($tierLabel)) {
                $tierDisplay = " <fg=magenta>[{$tierLabel}]</>";
            }

            $titleLine = "<fg=cyan>[{$id}]</> <fg=green>{$title}</> <fg=yellow>({$scoreDisplay})</>{$tierDisplay}";
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
