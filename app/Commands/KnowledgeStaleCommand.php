<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Services\ConfidenceService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeStaleCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'knowledge:stale';

    /**
     * @var string
     */
    protected $description = 'List entries needing review (stale or high confidence but old)';

    public function __construct(
        private readonly ConfidenceService $confidenceService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $staleEntries = $this->confidenceService->getStaleEntries();

        if ($staleEntries->isEmpty()) {
            $this->info('No stale entries found. Your knowledge base is up to date!');

            return self::SUCCESS;
        }

        $this->warn("Found {$staleEntries->count()} stale entries needing review:");
        $this->newLine();

        foreach ($staleEntries as $entry) {
            $this->displayEntry($entry);
            $this->newLine();
        }

        $this->comment('Suggestion: Review these entries and run "knowledge:validate <id>" to mark them as current.');
        $this->comment('Consider updating or deprecating entries that are no longer relevant.');

        return self::SUCCESS;
    }

    private function displayEntry(Entry $entry): void
    {
        $this->line("<options=bold>ID: {$entry->id}</>");
        $this->line("Title: {$entry->title}");
        $this->line("Status: {$entry->status}");
        $this->line("Confidence: {$entry->confidence}%");

        if ($entry->category !== null) {
            $this->line("Category: {$entry->category}");
        }

        // Display usage information
        if ($entry->last_used !== null) {
            $daysAgo = $entry->last_used->diffInDays(now());
            $this->line("Last used: {$daysAgo} days ago");
            $this->line("Usage count: {$entry->usage_count}");
        } else {
            $createdDaysAgo = $entry->created_at->diffInDays(now());
            $this->line('Never used');
            $this->line("Created: {$createdDaysAgo} days ago");
        }

        // Display reason for being stale
        $reason = $this->determineStaleReason($entry);
        $this->line("<fg=yellow>Reason: {$reason}</>");

        $this->line("Validate with: ./know knowledge:validate {$entry->id}");
    }

    private function determineStaleReason(Entry $entry): string
    {
        $ninetyDaysAgo = now()->subDays(90);
        $oneEightyDaysAgo = now()->subDays(180);

        if ($entry->last_used !== null && $entry->last_used <= $ninetyDaysAgo) {
            return 'Not used in 90+ days - needs re-validation';
        }

        if ($entry->last_used === null && $entry->created_at <= $ninetyDaysAgo) {
            return 'Never used and created 90+ days ago';
        }

        if ($entry->confidence >= 70 && $entry->created_at <= $oneEightyDaysAgo && $entry->status !== 'validated') {
            return 'High confidence but old and unvalidated - suggest validation';
        }

        return 'Needs review'; // @codeCoverageIgnore
    }
}
