<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\ResolvesProject;
use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeArchiveCommand extends Command
{
    use ResolvesProject;

    /**
     * @var string
     */
    protected $signature = 'archive
                            {id : The ID of the entry to archive}
                            {--restore : Restore an archived entry}
                            {--project= : Override project namespace}
                            {--global : Search across all projects}';

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

        $project = $this->resolveProject();
        $entry = $qdrant->getById((int) $id, $project);

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
        if ($entry['status'] === 'deprecated') {
            $this->warn("Entry #{$entry['id']} is already archived.");

            return self::SUCCESS;
        }

        $oldStatus = $entry['status'];

        $qdrant->updateFields((int) $entry['id'], [
            'status' => 'deprecated',
            'confidence' => 0,
        ], $this->resolveProject());

        $this->info("Entry #{$entry['id']} has been archived.");
        $this->newLine();
        $this->line("Title: {$entry['title']}");
        $this->line("Status: {$oldStatus} -> deprecated");
        $this->newLine();
        $this->comment('Restore with: knowledge:archive '.$entry['id'].' --restore');

        return self::SUCCESS;
    }

    /**
     * Restore an archived entry.
     *
     * @param  array<string, mixed>  $entry
     */
    private function restoreEntry(QdrantService $qdrant, array $entry): int
    {
        if ($entry['status'] !== 'deprecated') {
            $this->warn("Entry #{$entry['id']} is not archived (status: {$entry['status']}).");

            return self::SUCCESS;
        }

        $qdrant->updateFields((int) $entry['id'], [
            'status' => 'draft',
            'confidence' => 50,
        ], $this->resolveProject());

        $this->info("Entry #{$entry['id']} has been restored.");
        $this->newLine();
        $this->line("Title: {$entry['title']}");
        $this->line('Status: deprecated -> draft');
        $this->line('Confidence: 50%');
        $this->newLine();
        $this->comment('Validate with: knowledge:validate '.$entry['id']);

        return self::SUCCESS;
    }
}
