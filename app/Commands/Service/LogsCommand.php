<?php

declare(strict_types=1);

namespace App\Commands\Service;

use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\select;
use function Termwind\render;

class LogsCommand extends Command
{
    protected $signature = 'service:logs
                            {service? : Specific service (qdrant, redis, embeddings, ollama)}
                            {--f|follow : Follow log output}
                            {--tail=50 : Number of lines to show}
                            {--odin : Use Odin (remote) configuration}';

    protected $description = 'View service logs';

    public function handle(): int
    {
        $composeFile = $this->option('odin') === true
            ? 'docker-compose.odin.yml'
            : 'docker-compose.yml';

        $environment = $this->option('odin') === true ? 'Odin (Remote)' : 'Local';

        if (! file_exists(base_path($composeFile))) {
            render(<<<HTML
                <div class="mx-2 my-1">
                    <div class="px-4 py-2 bg-red-900">
                        <div class="text-red-400 font-bold">✗ Configuration Error</div>
                        <div class="text-red-300 mt-1">Docker Compose file not found: {$composeFile}</div>
                    </div>
                </div>
            HTML);

            return self::FAILURE;
        }

        $service = $this->argument('service');

        // If no service specified and not following, offer selection
        if ($service === null && $this->option('follow') !== true) {
            $service = select(
                label: 'Which service logs would you like to view?',
                options: [
                    'all' => 'All Services',
                    'qdrant' => 'Qdrant (Vector Database)',
                    'redis' => 'Redis (Cache)',
                    'embeddings' => 'Embeddings (ML Service)',
                    'ollama' => 'Ollama (LLM Engine)',
                ],
                default: 'all'
            );

            if ($service === 'all') {
                $service = null;
            }
        }

        $serviceDisplay = is_string($service) ? ucfirst($service) : 'All Services';
        $followMode = $this->option('follow') === true ? 'Live' : 'Recent';
        $tailOption = $this->option('tail');
        $tailCount = is_string($tailOption) || is_int($tailOption) ? (string) $tailOption : '50';

        // Display logs banner
        render(<<<HTML
            <div class="mx-2 my-1">
                <div class="px-4 py-2 bg-purple-900">
                    <div class="flex justify-between">
                        <div class="flex">
                            <span class="text-purple-400 mr-3">📋</span>
                            <div>
                                <div class="text-purple-300 font-bold">Service Logs: {$serviceDisplay}</div>
                                <div class="text-gray-400">Environment: {$environment}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-purple-300 font-bold">{$followMode}</div>
                            <div class="text-gray-400">Last {$tailCount} lines</div>
                        </div>
                    </div>
                </div>
            </div>
        HTML);

        if ($this->option('follow') === true) {
            render(<<<'HTML'
                <div class="mx-2 my-1">
                    <div class="px-4 py-1 bg-gray-800">
                        <div class="text-gray-400">
                            <span>Press </span><span class="text-cyan-400">Ctrl+C</span><span> to stop following logs</span>
                        </div>
                    </div>
                </div>
            HTML);
        }

        $this->newLine();

        $args = ['docker', 'compose', '-f', $composeFile, 'logs'];

        if ($this->option('follow') === true) {
            $args[] = '-f';
        }

        $tailOption = $this->option('tail');
        if (is_string($tailOption) || is_int($tailOption)) {
            $args[] = '--tail='.(string) $tailOption;
        }

        if ($service !== null) {
            $args[] = $service;
        }

        $result = Process::forever()
            ->path(base_path())
            ->run($args);

        $exitCode = $result->exitCode();

        // Only show footer if not following (Ctrl+C will interrupt)
        if ($this->option('follow') !== true) {
            $this->newLine();
            render(<<<'HTML'
                <div class="mx-2 my-1">
                    <div class="px-4 py-1 bg-gray-800">
                        <div class="text-gray-400">
                            <span>Tip: Use </span>
                            <span class="text-cyan-400">--follow</span>
                            <span> to stream logs in real-time</span>
                        </div>
                    </div>
                </div>
            HTML);
        }

        return $exitCode;
    }
}
