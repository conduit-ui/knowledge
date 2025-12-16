<?php

declare(strict_types=1);

namespace App\Commands\Session;

use App\Services\SessionService;
use LaravelZero\Framework\Commands\Command;

class ListCommand extends Command
{
    protected $signature = 'session:list
                            {--limit=20 : Number of sessions to display}
                            {--active : Show only active sessions}
                            {--project= : Filter by project name}';

    protected $description = 'List recent sessions';

    public function handle(SessionService $service): int
    {
        $limit = (int) $this->option('limit');
        $activeOnly = (bool) $this->option('active');
        $project = $this->option('project');

        if ($activeOnly) {
            $sessions = $service->getActiveSessions();
        } else {
            $sessions = $service->getRecentSessions($limit, is_string($project) ? $project : null);
        }

        if ($sessions->isEmpty()) {
            $this->info('No sessions found.');

            return self::SUCCESS;
        }

        $tableData = $sessions->map(function ($session): array {
            /** @var \App\Models\Session $session */
            $status = $session->ended_at === null ? 'Active' : 'Completed';
            $id = substr($session->id, 0, 8);

            return [
                'id' => $id,
                'project' => $session->project,
                'branch' => $session->branch ?? 'N/A',
                'started' => $session->started_at->format('Y-m-d H:i:s'),
                'status' => $status,
            ];
        })->toArray();

        $this->table(
            ['ID', 'Project', 'Branch', 'Started At', 'Status'],
            $tableData
        );

        return self::SUCCESS;
    }
}
