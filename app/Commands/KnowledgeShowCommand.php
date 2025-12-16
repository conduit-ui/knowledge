<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use LaravelZero\Framework\Commands\Command;

class KnowledgeShowCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'knowledge:show
                            {id : The ID of the knowledge entry to display}';

    /**
     * @var string
     */
    protected $description = 'Display full details of a knowledge entry';

    public function handle(): int
    {
        $id = $this->argument('id');

        // Validate ID is numeric
        if (! is_numeric($id)) {
            $this->error('The ID must be a valid number.');

            return self::FAILURE;
        }

        $entry = Entry::find((int) $id);

        if (! $entry) {
            $this->line('Entry not found.');

            return self::FAILURE;
        }

        // Increment usage count
        $entry->incrementUsage();

        // Display entry details
        $this->info("ID: {$entry->id}");
        $this->info("Title: {$entry->title}");
        $this->newLine();

        $this->line("Content: {$entry->content}");
        $this->newLine();

        $this->line('Category: '.($entry->category ?? 'N/A'));

        if ($entry->module) {
            $this->line("Module: {$entry->module}");
        }

        $this->line("Priority: {$entry->priority}");
        $this->line("Confidence: {$entry->confidence}%");
        $this->line("Status: {$entry->status}");
        $this->newLine();

        if ($entry->tags) {
            $this->line('Tags: '.implode(', ', $entry->tags));
        }

        if ($entry->source) {
            $this->line("Source: {$entry->source}");
        }

        if ($entry->ticket) {
            $this->line("Ticket: {$entry->ticket}");
        }

        if ($entry->author) {
            $this->line("Author: {$entry->author}");
        }

        if ($entry->files) {
            $this->line('Files: '.implode(', ', $entry->files));
        }

        if ($entry->repo) {
            $this->line("Repo: {$entry->repo}");
        }

        if ($entry->branch) {
            $this->line("Branch: {$entry->branch}");
        }

        if ($entry->commit) {
            $this->line("Commit: {$entry->commit}");
        }

        $this->newLine();
        $this->line("Usage Count: {$entry->usage_count}");

        if ($entry->last_used) {
            $this->line("Last Used: {$entry->last_used->format('Y-m-d H:i:s')}");
        }

        if ($entry->validation_date) {
            $this->line("Validation Date: {$entry->validation_date->format('Y-m-d H:i:s')}");
        }

        $this->line("Created: {$entry->created_at->format('Y-m-d H:i:s')}");
        $this->line("Updated: {$entry->updated_at->format('Y-m-d H:i:s')}");

        return self::SUCCESS;
    }
}
