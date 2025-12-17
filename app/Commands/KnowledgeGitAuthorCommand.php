<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use LaravelZero\Framework\Commands\Command;

class KnowledgeGitAuthorCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'git:author {name : Author name}';

    /**
     * @var string
     */
    protected $description = 'List knowledge entries by author - knowledge attribution similar to git blame';

    public function handle(): int
    {
        /** @var string $name */
        $name = $this->argument('name');

        $entries = Entry::query()->where('author', $name)->get();

        if ($entries->isEmpty()) {
            $this->warn("No entries found for author: {$name}");

            return self::SUCCESS;
        }

        $this->info("Entries by author: {$name}");
        $this->newLine();

        foreach ($entries as $entry) {
            $this->line("ID: {$entry->id}");
            $this->line("Title: {$entry->title}");
            $this->line('Category: '.($entry->category ?? 'N/A'));
            $this->line("Priority: {$entry->priority}");
            $this->line('Branch: '.($entry->branch ?? 'N/A'));
            $this->line('Commit: '.($entry->commit ?? 'N/A'));
            $this->newLine();
        }

        $this->info("Total entries: {$entries->count()}");

        return self::SUCCESS;
    }
}
