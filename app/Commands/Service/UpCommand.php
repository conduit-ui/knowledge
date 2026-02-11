<?php

declare(strict_types=1);

namespace App\Commands\Service;

use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

use function Termwind\render;

class UpCommand extends Command
{
    protected $signature = 'service:up
                            {--d|detach : Run in detached mode}
                            {--remote : Use remote configuration}';

    protected $description = 'Start knowledge services (Qdrant, Redis, Embeddings)';

    public function handle(): int
    {
        $composeFile = $this->option('remote') === true
            ? 'docker-compose.remote.yml'
            : 'docker-compose.yml';

        $environment = $this->option('remote') === true ? 'Remote' : 'Local';

        if (! file_exists(base_path($composeFile))) {
            render(<<<HTML
                <div class="mx-2 my-1">
                    <div class="px-4 py-2 bg-red-900">
                        <div class="text-red-400 font-bold">✗ Configuration Error</div>
                        <div class="text-red-300 mt-1">Docker Compose file not found: {$composeFile}</div>
                        <div class="text-gray-400 mt-2">
                            <span>Run </span><span class="text-cyan-400">know service:init</span><span> to initialize</span>
                        </div>
                    </div>
                </div>
            HTML);

            return self::FAILURE;
        }

        // Display startup banner
        render(<<<HTML
            <div class="mx-2 my-1">
                <div class="px-4 py-2 bg-blue-900">
                    <div>
                        <span class="text-blue-400 mr-3">▶</span>
                        <span class="text-blue-300 font-bold">Starting Knowledge Services</span>
                    </div>
                    <div class="text-gray-400 ml-6">Environment: {$environment}</div>
                </div>
            </div>
        HTML);

        $args = ['docker', 'compose', '-f', $composeFile, 'up'];

        if ($this->option('detach') === true) {
            $args[] = '-d';
        }

        $result = Process::forever()
            ->path(base_path())
            ->run($args);

        $exitCode = $result->exitCode();

        if ($exitCode === 0) {
            if ($this->option('detach') === true) {
                render(<<<'HTML'
                    <div class="mx-2 my-1">
                        <div class="px-4 py-2 bg-green-900">
                            <div>
                                <span class="text-green-400 mr-3">✓</span>
                                <span class="text-green-300 font-bold">Services Started Successfully</span>
                            </div>
                            <div class="text-gray-400 mt-1 ml-6">All containers are running in detached mode</div>
                        </div>
                    </div>
                HTML);

                render(<<<'HTML'
                    <div class="mx-2 my-1">
                        <div class="px-4 py-2 bg-gray-800">
                            <div class="text-gray-400 font-bold mb-2">NEXT STEPS</div>
                            <div class="mb-1">
                                <span class="text-cyan-400 mr-2">→</span>
                                <span class="text-gray-300">Check status: </span>
                                <span class="text-cyan-400 ml-1">know service:status</span>
                            </div>
                            <div class="mb-1">
                                <span class="text-cyan-400 mr-2">→</span>
                                <span class="text-gray-300">View logs: </span>
                                <span class="text-cyan-400 ml-1">know service:logs</span>
                            </div>
                            <div>
                                <span class="text-cyan-400 mr-2">→</span>
                                <span class="text-gray-300">Stop services: </span>
                                <span class="text-cyan-400 ml-1">know service:down</span>
                            </div>
                        </div>
                    </div>
                HTML);
            }

            return self::SUCCESS;
        }

        render(<<<'HTML'
            <div class="mx-2 my-1">
                <div class="px-4 py-2 bg-red-900">
                    <div>
                        <span class="text-red-400 mr-3">✗</span>
                        <span class="text-red-300 font-bold">Failed to Start Services</span>
                    </div>
                    <div class="text-gray-400 mt-1 ml-6">Check the error output above for details</div>
                </div>
            </div>
        HTML);

        return self::FAILURE;
    }
}
