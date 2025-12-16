<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use App\Services\ConfidenceService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeValidateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'knowledge:validate {id : The ID of the entry to validate}';

    /**
     * @var string
     */
    protected $description = 'Mark an entry as validated and boost its confidence';

    public function __construct(
        private readonly ConfidenceService $confidenceService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $id = $this->argument('id');

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

        $oldConfidence = $entry->confidence;
        $oldStatus = $entry->status;

        $this->confidenceService->validateEntry($entry);

        $entry->refresh();
        $newConfidence = $entry->confidence;

        $this->info("Entry #{$entry->id} validated successfully!");
        $this->newLine();

        $this->line("Title: {$entry->title}");
        $this->line("Status: {$oldStatus} -> validated");
        $this->line("Confidence: {$oldConfidence}% -> {$newConfidence}%");

        if ($entry->validation_date !== null) {
            $this->line("Validation Date: {$entry->validation_date->format('Y-m-d H:i:s')}");
        }

        $this->newLine();
        $this->comment('The entry has been marked as validated and its confidence has been updated.');

        return self::SUCCESS;
    }
}
