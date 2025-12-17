<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use LaravelZero\Framework\Commands\Command;

class KnowledgeArchiveCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'archive
                            {id : The ID of the entry to archive}
                            {--restore : Restore an archived entry}';

    /**
     * @var string
     */
    protected $description = 'Archive an entry (soft delete) or restore an archived entry';

    public function handle(): int
    {
        $id = $this->argument('id');

        /** @var bool $restore */
        $restore = (bool) $this->option('restore');

        if (! is_numeric($id)) {
            $this->error('Entry ID must be a number.');

            return self::FAILURE;
        }

        /** @var Entry|null $entry */
        $entry = Entry::query()->find((int) $id);

        if ($entry === null) {
            $this->error("Entry not found with ID: {$id}");

            return self::FAILURE;
        }

        if ($restore) {
            return $this->restoreEntry($entry);
        }

        return $this->archiveEntry($entry);
    }

    /**
     * Archive an entry.
     */
    private function archiveEntry(Entry $entry): int
    {
        if ($entry->status === 'deprecated') {
            $this->warn("Entry #{$entry->id} is already archived.");

            return self::SUCCESS;
        }

        $oldStatus = $entry->status;

        $entry->update([
            'status' => 'deprecated',
            'confidence' => 0,
        ]);

        $this->info("Entry #{$entry->id} has been archived.");
        $this->newLine();
        $this->line("Title: {$entry->title}");
        $this->line("Status: {$oldStatus} -> deprecated");
        $this->newLine();
        $this->comment('Restore with: knowledge:archive '.$entry->id.' --restore');

        return self::SUCCESS;
    }

    /**
     * Restore an archived entry.
     */
    private function restoreEntry(Entry $entry): int
    {
        if ($entry->status !== 'deprecated') {
            $this->warn("Entry #{$entry->id} is not archived (status: {$entry->status}).");

            return self::SUCCESS;
        }

        $entry->update([
            'status' => 'draft',
            'confidence' => 50,
        ]);

        $this->info("Entry #{$entry->id} has been restored.");
        $this->newLine();
        $this->line("Title: {$entry->title}");
        $this->line('Status: deprecated -> draft');
        $this->line('Confidence: 50%');
        $this->newLine();
        $this->comment('Validate with: knowledge:validate '.$entry->id);

        return self::SUCCESS;
    }
}
