<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\ResolvesProject;
use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class KnowledgeListCommand extends Command
{
    use ResolvesProject;

    /**
     * @var string
     */
    protected $signature = 'entries
                            {--category= : Filter by category}
                            {--priority= : Filter by priority}
                            {--status= : Filter by status}
                            {--module= : Filter by module}
                            {--limit=20 : Maximum number of entries to display}
                            {--offset= : Skip N entries (use point ID for pagination)}
                            {--project= : Override project namespace}
                            {--global : Search across all projects}';

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
        $offset = $this->option('offset');

        // Build filters for Qdrant
        $filters = array_filter([
            'category' => is_string($category) ? $category : null,
            'priority' => is_string($priority) ? $priority : null,
            'status' => is_string($status) ? $status : null,
            'module' => is_string($module) ? $module : null,
        ]);

        // Parse offset - can be integer ID or null
        $parsedOffset = is_string($offset) && $offset !== '' ? (int) $offset : null;

        // Use scroll to get entries (no vector search needed)
        $results = spin(
            fn (): \Illuminate\Support\Collection => $qdrant->scroll($filters, $limit, $this->resolveProject(), $parsedOffset),
            'Fetching entries...'
        );

        if ($results->isEmpty()) {
            $this->line('No entries found.');

            return self::SUCCESS;
        }

        info("Found {$results->count()} ".str('entry')->plural($results->count()));

        // Build table data
        $rows = $results->map(function (array $entry): array {
            $tags = isset($entry['tags']) && $entry['tags'] !== []
                ? implode(', ', array_slice($entry['tags'], 0, 3)).(count($entry['tags']) > 3 ? '...' : '')
                : '-';

            return [
                substr((string) $entry['id'], 0, 8).'...',
                substr($entry['title'], 0, 40).(strlen($entry['title']) > 40 ? '...' : ''),
                $entry['category'] ?? '-',
                $entry['priority'] ?? '-',
                $entry['confidence'].'%',
                $tags,
            ];
        })->toArray();

        table(
            ['ID', 'Title', 'Category', 'Priority', 'Confidence', 'Tags'],
            $rows
        );

        return self::SUCCESS;
    }
}
