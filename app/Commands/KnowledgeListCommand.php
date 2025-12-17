<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Builder;
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
                            {--min-confidence= : Minimum confidence level (0-100)}
                            {--limit=20 : Maximum number of entries to display}';

    /**
     * @var string
     */
    protected $description = 'List knowledge entries with filtering and pagination';

    public function handle(): int
    {
        $category = $this->option('category');
        $priority = $this->option('priority');
        $status = $this->option('status');
        $module = $this->option('module');
        $minConfidence = $this->option('min-confidence');
        $limit = (int) $this->option('limit');

        $query = Entry::query()
            ->when($category, function (Builder $q, mixed $categoryValue): void {
                if (is_string($categoryValue)) {
                    $q->where('category', $categoryValue);
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
            ->when($module, function (Builder $q, mixed $moduleValue): void {
                if (is_string($moduleValue)) {
                    $q->where('module', $moduleValue);
                }
            })
            ->when($minConfidence, function (Builder $q, mixed $minConfidenceValue): void {
                if (is_string($minConfidenceValue) || is_int($minConfidenceValue)) {
                    $q->where('confidence', '>=', (int) $minConfidenceValue);
                }
            })
            ->orderBy('confidence', 'desc')
            ->orderBy('usage_count', 'desc');

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->line('No entries found.');

            return self::SUCCESS;
        }

        $entries = $query->limit($limit)->get();

        $this->info("Showing {$entries->count()} of {$totalCount} ".str('entry')->plural($totalCount));
        $this->newLine();

        foreach ($entries as $entry) {
            $this->line("<fg=cyan>[{$entry->id}]</> <fg=green>{$entry->title}</>");

            $details = [];
            $details[] = 'Category: '.($entry->category ?? 'N/A');
            $details[] = "Priority: {$entry->priority}";
            $details[] = "Confidence: {$entry->confidence}%";
            $details[] = "Status: {$entry->status}";

            if ($entry->module) {
                $details[] = "Module: {$entry->module}";
            }

            $this->line(implode(' | ', $details));

            if ($entry->tags) {
                $this->line('Tags: '.implode(', ', $entry->tags));
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
