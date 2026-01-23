<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\QdrantService;
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

    public function handle(QdrantService $qdrant): int
    {
        $id = $this->argument('id');

        /** @var bool $restore */
        $restore = (bool) $this->option('restore');

        if (! is_numeric($id)) {
            $this->error('Entry ID must be a number.');

            return self::FAILURE;
        }

        $entry = $qdrant->getById((int) $id);

        if ($entry === null) {
            $this->error("Entry not found with ID: {$id}");

            return self::FAILURE;
        }

        if ($restore) {
            return $this->restoreEntry($qdrant, $entry);
        }

        return $this->archiveEntry($qdrant, $entry);
    }

    /**
     * Archive an entry.
     *
     * @param  array<string, mixed>  $entry
     */
    private function archiveEntry(QdrantService $qdrant, array $entry): int
    {
        $entryId = is_scalar($entry['id']) ? (int) $entry['id'] : 0;
        $entryTitle = is_scalar($entry['title']) ? (string) $entry['title'] : '';
        $entryStatus = is_scalar($entry['status']) ? (string) $entry['status'] : '';

        if ($entryStatus === 'deprecated') {
            $this->warn("Entry #{$entryId} is already archived.");

            return self::SUCCESS;
        }

        $oldStatus = $entryStatus;

        $qdrant->updateFields($entryId, [
            'status' => 'deprecated',
            'confidence' => 0,
        ]);

        $this->info("Entry #{$entryId} has been archived.");
        $this->newLine();
        $this->line("Title: {$entryTitle}");
        $this->line("Status: {$oldStatus} -> deprecated");
        $this->newLine();
        $this->comment('Restore with: knowledge:archive '.$entryId.' --restore');

        return self::SUCCESS;
    }

    /**
     * Restore an archived entry.
     *
     * @param  array<string, mixed>  $entry
     */
    private function restoreEntry(QdrantService $qdrant, array $entry): int
    {
        $entryId = is_scalar($entry['id']) ? (int) $entry['id'] : 0;
        $entryTitle = is_scalar($entry['title']) ? (string) $entry['title'] : '';
        $entryStatus = is_scalar($entry['status']) ? (string) $entry['status'] : '';

        if ($entryStatus !== 'deprecated') {
            $this->warn("Entry #{$entryId} is not archived (status: {$entryStatus}).");

            return self::SUCCESS;
        }

        $qdrant->updateFields($entryId, [
            'status' => 'draft',
            'confidence' => 50,
        ]);

        $this->info("Entry #{$entryId} has been restored.");
        $this->newLine();
        $this->line("Title: {$entryTitle}");
        $this->line('Status: deprecated -> draft');
        $this->line('Confidence: 50%');
        $this->newLine();
        $this->comment('Validate with: knowledge:validate '.$entryId);

        return self::SUCCESS;
    }
}
