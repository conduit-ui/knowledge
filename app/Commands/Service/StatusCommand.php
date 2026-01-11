<?php

declare(strict_types=1);

namespace App\Commands\Service;

use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\spin;
use function Termwind\render;

class StatusCommand extends Command
{
    protected $signature = 'service:status
                            {--odin : Use Odin (remote) configuration}';

    protected $description = 'Check service health status';

    public function handle(): int
    {
        $composeFile = $this->option('odin') === true
            ? 'docker-compose.odin.yml'
            : 'docker-compose.yml';

        $environment = $this->option('odin') === true ? 'Odin (Remote)' : 'Local';

        // Perform health checks with spinner
        $healthData = spin(
            fn () => $this->performHealthChecks(),
            'Checking service health...'
        );

        // Get container status
        $containerStatus = $this->getContainerStatus($composeFile);

        // Render beautiful dashboard
        $this->renderDashboard($environment, $healthData, $containerStatus);

        return self::SUCCESS;
    }

    private function performHealthChecks(): array
    {
        return [
            [
                'name' => 'Qdrant',
                'healthy' => $this->checkQdrant(),
                'endpoint' => config('search.qdrant.host', 'localhost').':'.config('search.qdrant.port', 6333),
                'type' => 'Vector Database',
            ],
            [
                'name' => 'Redis',
                'healthy' => $this->checkRedis(),
                'endpoint' => config('database.redis.default.host', '127.0.0.1').':'.config('database.redis.default.port', 6380),
                'type' => 'Cache',
            ],
            [
                'name' => 'Embeddings',
                'healthy' => $this->checkEmbeddings(),
                'endpoint' => config('search.qdrant.embedding_server', 'http://localhost:8001'),
                'type' => 'ML Service',
            ],
            [
                'name' => 'Ollama',
                'healthy' => $this->checkOllama(),
                'endpoint' => config('search.ollama.host', 'localhost').':'.config('search.ollama.port', 11434),
                'type' => 'LLM Engine',
            ],
        ];
    }

    private function getContainerStatus(string $composeFile): array
    {
        $process = new Process([
            'docker', 'compose', '-f', $composeFile, 'ps', '--format', 'json',
        ], base_path());

        $process->run();

        if ($process->getExitCode() !== 0) {
            return [];
        }

        $output = $process->getOutput();
        if ($output === '') {
            return [];
        }

        $containers = [];
        foreach (explode("\n", trim($output)) as $line) {
            if ($line === '') {
                continue;
            }
            $data = json_decode($line, true);
            if (is_array($data) && count($data) > 0) {
                $containers[] = $data;
            }
        }

        return $containers;
    }

    private function renderDashboard(string $environment, array $healthData, array $containers): void
    {
        $allHealthy = collect($healthData)->every(fn ($service) => $service['healthy']);
        $healthyCount = collect($healthData)->filter(fn ($service) => $service['healthy'])->count();
        $totalCount = count($healthData);

        $statusColor = $allHealthy ? 'green' : ($healthyCount > 0 ? 'yellow' : 'red');
        $statusText = $allHealthy ? 'All Systems Operational' : ($healthyCount > 0 ? 'Partial Outage' : 'Major Outage');
        $statusIcon = $allHealthy ? '●' : ($healthyCount > 0 ? '●' : '●');

        render(<<<HTML
            <div class="mx-2 my-1">
                <div class="px-4 py-2 bg-gray-800">
                    <div class="flex justify-between">
                        <div>
                            <span class="text-gray-400 font-bold">KNOWLEDGE SERVICE STATUS</span>
                            <span class="ml-2 text-gray-500">·</span>
                            <span class="ml-2 text-gray-400">{$environment}</span>
                        </div>
                        <div class="flex">
                            <span class="text-{$statusColor} mr-2">{$statusIcon}</span>
                            <span class="text-{$statusColor} font-bold">{$statusText}</span>
                            <span class="ml-3 text-gray-500">{$healthyCount}/{$totalCount}</span>
                        </div>
                    </div>
                </div>
            </div>
        HTML);

        // Service Health Cards
        render(<<<'HTML'
            <div class="mx-2 my-1">
                <div class="px-2 py-1">
                    <span class="text-gray-400 font-bold">SERVICE HEALTH</span>
                </div>
            </div>
        HTML);

        foreach ($healthData as $service) {
            $color = $service['healthy'] ? 'green' : 'red';
            $icon = $service['healthy'] ? '✓' : '✗';
            $status = $service['healthy'] ? 'Healthy' : 'Unhealthy';

            $name = $service['name'];
            $type = $service['type'];
            $endpoint = $service['endpoint'];

            render(<<<HTML
                <div class="mx-2">
                    <div class="px-4 py-2 bg-gray-900 mb-1">
                        <div class="flex justify-between ">
                            <div class="flex ">
                                <span class="text-{$color} mr-3">{$icon}</span>
                                <div>
                                    <div class="text-white font-bold">{$name}</div>
                                    <div class="text-gray-500">{$type}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-{$color} font-bold">{$status}</div>
                                <div class="text-gray-500">{$endpoint}</div>
                            </div>
                        </div>
                    </div>
                </div>
            HTML);
        }

        // Container Status
        if (count($containers) > 0) {
            render(<<<'HTML'
                <div class="mx-2 my-1 mt-2">
                    <div class="px-2 py-1">
                        <span class="text-gray-400 font-bold">CONTAINERS</span>
                    </div>
                </div>
            HTML);

            foreach ($containers as $container) {
                $state = $container['State'] ?? 'unknown';
                $name = $container['Service'] ?? $container['Name'] ?? 'unknown';

                $stateColor = match ($state) {
                    'running' => 'green',
                    'exited' => 'red',
                    'paused' => 'yellow',
                    default => 'gray',
                };

                $stateIcon = match ($state) {
                    'running' => '▶',
                    'exited' => '■',
                    'paused' => '❙❙',
                    default => '?',
                };

                render(<<<HTML
                    <div class="mx-2">
                        <div class="px-4 py-1 bg-gray-900 mb-1">
                            <div class="flex justify-between ">
                                <div class="flex ">
                                    <span class="text-{$stateColor} mr-3">{$stateIcon}</span>
                                    <span class="text-white">{$name}</span>
                                </div>
                                <span class="text-{$stateColor} uppercase">{$state}</span>
                            </div>
                        </div>
                    </div>
                HTML);
            }
        }

        // Footer
        render(<<<'HTML'
            <div class="mx-2 my-1 mt-1">
                <div class="px-2 text-gray-500">
                    <span>Run </span><span class="text-cyan-400">know service:logs</span><span> to view service logs</span>
                </div>
            </div>
        HTML);
    }

    private function checkQdrant(): bool
    {
        $host = config('search.qdrant.host', 'localhost');
        $port = config('search.qdrant.port', 6333);

        return @file_get_contents("http://{$host}:{$port}/healthz") !== false;
    }

    private function checkRedis(): bool
    {
        if (! extension_loaded('redis')) {
            return false;
        }

        try {
            $redis = new \Redis;
            $host = config('database.redis.default.host', '127.0.0.1');
            $port = (int) config('database.redis.default.port', 6380);

            return $redis->connect($host, $port, 1);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkEmbeddings(): bool
    {
        $server = config('search.qdrant.embedding_server', 'http://localhost:8001');

        return @file_get_contents("{$server}/health") !== false;
    }

    private function checkOllama(): bool
    {
        $host = config('search.ollama.host', 'localhost');
        $port = config('search.ollama.port', 11434);

        return @file_get_contents("http://{$host}:{$port}/api/tags") !== false;
    }
}
