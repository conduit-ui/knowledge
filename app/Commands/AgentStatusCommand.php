<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\AgentHealthService;
use LaravelZero\Framework\Commands\Command;

class AgentStatusCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'agent:status
        {--json : Output raw JSON only}';

    /**
     * @var string
     */
    protected $description = 'Check health of agent dependencies (Redis, Qdrant) and report status';

    public function handle(AgentHealthService $health): int
    {
        $redisStatus = $health->checkRedis();
        $qdrantStatus = $health->checkQdrant();
        $agents = $redisStatus['ok'] ? $health->getAgentTimestamps() : [];

        $healthy = $redisStatus['ok'] && $qdrantStatus['ok'];

        $result = [
            'healthy' => $healthy,
            'dependencies' => [
                'redis' => $redisStatus,
                'qdrant' => $qdrantStatus,
            ],
            'agents' => $agents,
            'checked_at' => now()->toIso8601String(),
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT));

            return $healthy ? self::SUCCESS : self::FAILURE;
        }

        $this->renderStatus($result);

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array{healthy: bool, dependencies: array{redis: array{ok: bool, ping: string, error: string|null}, qdrant: array{ok: bool, collection: string, points_count: int, error: string|null}}, agents: array<string, string|null>, checked_at: string}  $result
     */
    private function renderStatus(array $result): void
    {
        $overallIcon = $result['healthy'] ? '<fg=green>HEALTHY</>' : '<fg=red>DEGRADED</>';
        $this->newLine();
        $this->line("  Agent Status: {$overallIcon}");
        $this->newLine();

        $redis = $result['dependencies']['redis'];
        $redisIcon = $redis['ok'] ? '<fg=green>OK</>' : '<fg=red>FAIL</>';
        $this->line("  Redis: {$redisIcon}");
        if ($redis['error'] !== null) {
            $this->line("    Error: {$redis['error']}");
        }

        $qdrant = $result['dependencies']['qdrant'];
        $qdrantIcon = $qdrant['ok'] ? '<fg=green>OK</>' : '<fg=red>FAIL</>';
        $this->line("  Qdrant: {$qdrantIcon}");
        $this->line("    Collection: {$qdrant['collection']}");
        $this->line("    Points: {$qdrant['points_count']}");
        if ($qdrant['error'] !== null) {
            $this->line("    Error: {$qdrant['error']}");
        }

        if ($result['agents'] !== []) {
            $this->newLine();
            $this->line('  Agent Last Events:');
            foreach ($result['agents'] as $agent => $timestamp) {
                $this->line("    {$agent}: ".($timestamp ?? 'never'));
            }
        }

        $this->newLine();
    }
}
