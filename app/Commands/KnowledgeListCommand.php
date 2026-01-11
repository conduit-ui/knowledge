<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeListCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'entries
                            {--category= : Filter by category}
                            {--priority= : Filter by priority}
                            {--status= : Filter by status}
                            {--module= : Filter by module}
                            {--limit=20 : Maximum number of entries to display}';

    /**
     * @var string
     */
    protected $description = 'List knowledge entries with filtering and pagination';

    public function handle(QdrantService $qdrant): int
    {
        $category = $this->option('category');
        $priority = $this->option('priority');
        $status = $this->option('status');
        $module = $this->option('module');
        $limit = (int) $this->option('limit');

        // Build filters for Qdrant
        $filters = array_filter([
            'category' => is_string($category) ? $category : null,
            'priority' => is_string($priority) ? $priority : null,
            'status' => is_string($status) ? $status : null,
            'module' => is_string($module) ? $module : null,
        ]);

        // Search with empty query to get all entries matching filters
        $results = $qdrant->search('', $filters, $limit);

        if ($results->isEmpty()) {
            $this->line('No entries found.');

            return self::SUCCESS;
        }

        $this->info("Found {$results->count()} ".str('entry')->plural($results->count()));
        $this->newLine();

        foreach ($results as $entry) {
            $this->line("<fg=cyan>[{$entry['id']}]</> <fg=green>{$entry['title']}</>");

            $details = [];
            $details[] = 'Category: '.($entry['category'] ?? 'N/A');
            $details[] = "Priority: {$entry['priority']}";
            $details[] = "Confidence: {$entry['confidence']}%";
            $details[] = "Status: {$entry['status']}";

            if ($entry['module'] !== null) {
                $details[] = "Module: {$entry['module']}";
            }

            $this->line(implode(' | ', $details));

            if (isset($entry['tags']) && count($entry['tags']) > 0) {
                $this->line('Tags: '.implode(', ', $entry['tags']));
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
