<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use LaravelZero\Framework\Commands\Command;

class KnowledgeGitEntriesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'git:entries {commit : Git commit hash}';

    /**
     * @var string
     */
    protected $description = 'List knowledge entries from a specific commit - useful for code review and documentation audit';

    public function handle(): int
    {
        /** @var string $commit */
        $commit = $this->argument('commit');

        $entries = Entry::query()->where('commit', $commit)->get();

        if ($entries->isEmpty()) {
            $this->warn("No entries found for commit: {$commit}");

            return self::SUCCESS;
        }

        $this->info("Entries for commit: {$commit}");
        $this->newLine();

        foreach ($entries as $entry) {
            $this->line("ID: {$entry->id}");
            $this->line("Title: {$entry->title}");
            $this->line('Category: '.($entry->category ?? 'N/A'));
            $this->line("Priority: {$entry->priority}");
            $this->line('Branch: '.($entry->branch ?? 'N/A'));
            $this->line('Author: '.($entry->author ?? 'N/A'));
            $this->newLine();
        }

        $this->info("Total entries: {$entries->count()}");

        return self::SUCCESS;
    }
}
