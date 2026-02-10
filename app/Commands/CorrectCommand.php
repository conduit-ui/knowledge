<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\CorrectionService;
use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\spin;

class CorrectCommand extends Command
{
    protected $signature = 'correct
                            {id : The ID of the knowledge entry to correct}
                            {--new-value= : The corrected content value}';

    protected $description = 'Correct an entry and propagate changes to conflicting entries';

    public function handle(QdrantService $qdrant, CorrectionService $correction): int
    {
        $idArg = $this->argument('id');
        if (! is_string($idArg) || $idArg === '') {
            error('Invalid or missing ID argument.');

            return self::FAILURE;
        }
        $id = $idArg;

        /** @var string|null $newValue */
        $newValue = is_string($this->option('new-value')) ? $this->option('new-value') : null;
        if ($newValue === null || $newValue === '') {
            error('The --new-value option is required.');

            return self::FAILURE;
        }

        // Verify entry exists
        $entry = spin(
            fn (): ?array => $qdrant->getById($id),
            'Fetching entry...'
        );

        if ($entry === null) {
            error("Entry not found: {$id}");

            return self::FAILURE;
        }

        $this->info("Correcting entry: {$entry['title']}");

        // Execute correction with propagation
        /** @var array{corrected_entry_id: string, superseded_ids: array<string|int>, conflicts_found: int, log_entry_id: string} $result */
        $result = spin(
            fn (): array => $correction->correct($id, $newValue),
            'Applying correction and propagating changes...'
        );

        // Display propagation report
        $this->displayReport($entry, $newValue, $result);

        return self::SUCCESS;
    }

    /**
     * Display the propagation report.
     *
     * @param  array<string, mixed>  $originalEntry
     * @param  array{corrected_entry_id: string, superseded_ids: array<string|int>, conflicts_found: int, log_entry_id: string}  $result
     */
    private function displayReport(array $originalEntry, string $newValue, array $result): void
    {
        $this->info('Correction applied successfully!');
        $this->newLine();

        $this->line("Original Entry: {$originalEntry['id']}");
        $this->line("Original Title: {$originalEntry['title']}");
        $this->line("New Entry ID: {$result['corrected_entry_id']}");
        $this->line('Evidence: user correction');
        $this->line("Conflicts Found: {$result['conflicts_found']}");
        $this->line('Entries Superseded: '.count($result['superseded_ids']));
        $this->line("Log Entry: {$result['log_entry_id']}");

        if (count($result['superseded_ids']) > 0) {
            $this->newLine();
            $supersededText = 'Superseded entries: '.implode(', ', $result['superseded_ids']);
            $this->line($supersededText);
        }

        $this->newLine();
        $this->comment("View corrected entry: ./know show {$result['corrected_entry_id']}");
    }
}
