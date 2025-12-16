<?php

declare(strict_types=1);

namespace App\Commands\Session;

use App\Services\SessionService;
use LaravelZero\Framework\Commands\Command;

class ShowCommand extends Command
{
    protected $signature = 'session:show {id : The session ID to display}';

    protected $description = 'Display full details of a session';

    public function handle(SessionService $service): int
    {
        $id = $this->argument('id');

        if (! is_string($id)) {
            $this->error('The ID must be a valid string.');

            return self::FAILURE;
        }

        $session = $service->getSessionWithObservations($id);

        if ($session === null) {
            $this->error('Session not found.');

            return self::FAILURE;
        }

        $this->info("ID: {$session->id}");
        $this->info("Project: {$session->project}");
        $this->info('Branch: '.($session->branch ?? 'N/A'));
        $this->newLine();

        $this->line("Started At: {$session->started_at->format('Y-m-d H:i:s')}");

        if ($session->ended_at !== null) {
            $this->line("Ended At: {$session->ended_at->format('Y-m-d H:i:s')}");

            $duration = $session->ended_at->diffForHumans($session->started_at, true);
            $this->line("Duration: {$duration}");
        } else {
            $this->line('Status: Active');
        }

        $this->newLine();

        if ($session->summary !== null) {
            $this->line("Summary: {$session->summary}");
            $this->newLine();
        }

        $observationsCount = $session->observations->count();
        $this->line("Observations: {$observationsCount}");

        if ($observationsCount > 0) {
            $observationsByType = $session->observations->groupBy('type')->map->count();

            foreach ($observationsByType as $type => $count) {
                $this->line("  - {$type}: {$count}");
            }
        }

        $this->newLine();
        $this->line("Created: {$session->created_at->format('Y-m-d H:i:s')}");
        $this->line("Updated: {$session->updated_at->format('Y-m-d H:i:s')}");

        return self::SUCCESS;
    }
}
