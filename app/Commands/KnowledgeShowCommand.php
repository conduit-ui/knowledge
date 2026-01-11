<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeShowCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'show
                            {id : The ID of the knowledge entry to display}';

    /**
     * @var string
     */
    protected $description = 'Display full details of a knowledge entry';

    public function handle(QdrantService $qdrant): int
    {
        $id = $this->argument('id');

        // Convert to integer if numeric, otherwise keep as string (for UUID support)
        if (is_numeric($id)) {
            $id = (int) $id;
        }

        $entry = $qdrant->getById($id);

        if (! $entry) {
            $this->line('Entry not found.');

            return self::FAILURE;
        }

        // Increment usage count
        $qdrant->incrementUsage($id);

        // Display entry details
        $this->info("ID: {$entry['id']}");
        $this->info("Title: {$entry['title']}");
        $this->newLine();

        $this->line("Content: {$entry['content']}");
        $this->newLine();

        $this->line('Category: '.($entry['category'] ?? 'N/A'));

        if ($entry['module']) {
            $this->line("Module: {$entry['module']}");
        }

        $this->line("Priority: {$entry['priority']}");
        $this->line("Confidence: {$entry['confidence']}%");
        $this->line("Status: {$entry['status']}");
        $this->newLine();

        if (! empty($entry['tags'])) {
            $this->line('Tags: '.implode(', ', $entry['tags']));
        }

        $this->newLine();
        $this->line("Usage Count: {$entry['usage_count']}");

        $this->line("Created: {$entry['created_at']}");
        $this->line("Updated: {$entry['updated_at']}");

        return self::SUCCESS;
    }
}
