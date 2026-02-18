<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\ResolvesProject;
use App\Services\SupersedeService;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;

class SupersedeCommand extends Command
{
    use ResolvesProject;

    protected $signature = 'supersede {old_id : ID of the entry to deprecate} {new_id : ID of the superseding entry} {reason? : Reason for supersession}';

    protected $description = 'Mark an entry as superseded by a newer one';

    public function handle(SupersedeService $supersede): int
    {
        $oldId = $this->argument('old_id');
        $newId = $this->argument('new_id');
        $reason = $this->argument('reason');
        $project = $this->resolveProject();

        try {
            $result = $supersede->supersede($oldId, $newId, $reason, $project);
        } catch (\Exception $e) {
            error("Supersession failed: " . $e->getMessage());

            return self::FAILURE;
        }

        info('Supersession successful!');

        table(
            ['Field', 'Old Entry', 'New Entry'],
            [
                ['Title', $result['old']['title'], $result['new']['title']],
                ['Status', $result['old']['status'], $result['new']['status']],
                ['Reason', $result['reason'], ''],
            ]
        );

        note("Old entry {$oldId} now deprecated, points to {$newId}.");

        return self::SUCCESS;
    }
}
