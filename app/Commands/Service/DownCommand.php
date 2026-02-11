<?php

declare(strict_types=1);

namespace App\Commands\Service;

use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Termwind\render;

class DownCommand extends Command
{
    protected $signature = 'service:down
                            {--volumes : Remove volumes}
                            {--odin : Use Odin (remote) configuration}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Stop knowledge services';

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

        // @codeCoverageIgnoreStart
        // Show warning if removing volumes
        if ($this->option('volumes') === true && $this->option('force') !== true) {
            render(<<<'HTML'
                <div class="mx-2 my-1">
                    <div class="px-4 py-2 bg-yellow-900">
                        <div>
                            <span class="text-yellow-400 mr-3">⚠</span>
                            <span class="text-yellow-300 font-bold">Warning: Volume Removal</span>
                        </div>
                        <div class="text-gray-300 mt-1 ml-6">This will permanently delete all data stored in volumes</div>
                    </div>
                </div>
            HTML);

            $confirmed = confirm(
                label: 'Are you sure you want to remove volumes?',
                default: false,
                hint: 'This action cannot be undone'
            );

            if (! $confirmed) {
                render(<<<'HTML'
                    <div class="mx-2 my-1">
                        <div class="px-4 py-2 bg-gray-800">
                            <div class="text-gray-400">Operation cancelled</div>
                        </div>
                    </div>
                HTML);

                return self::SUCCESS;
            }
        }
        // @codeCoverageIgnoreEnd

        // Display shutdown banner
        render(<<<HTML
            <div class="mx-2 my-1">
                <div class="px-4 py-2 bg-orange-900">
                    <div>
                        <span class="text-orange-400 mr-3">■</span>
                        <span class="text-orange-300 font-bold">Stopping Knowledge Services</span>
                    </div>
                    <div class="text-gray-400 ml-6">Environment: {$environment}</div>
                </div>
            </div>
        HTML);

        $args = ['docker', 'compose', '-f', $composeFile, 'down'];

        if ($this->option('volumes') === true) {
            $args[] = '-v';
        }

        $result = Process::forever()
            ->path(base_path())
            ->run($args);

        $exitCode = $result->exitCode();

        if ($exitCode === 0) {
            $volumeText = $this->option('volumes') === true ? ' and volumes removed' : '';

            render(<<<HTML
                <div class="mx-2 my-1">
                    <div class="px-4 py-2 bg-green-900">
                        <div>
                            <span class="text-green-400 mr-3">✓</span>
                            <span class="text-green-300 font-bold">Services Stopped Successfully</span>
                        </div>
                        <div class="text-gray-400 mt-1 ml-6">All containers have been stopped{$volumeText}</div>
                    </div>
                </div>
            HTML);

            if ($this->option('volumes') !== true) {
                render(<<<'HTML'
                    <div class="mx-2 my-1">
                        <div class="px-4 py-2 bg-gray-800">
                            <div class="text-gray-400">
                                <span>Tip: Use </span>
                                <span class="text-cyan-400">know service:down --volumes</span>
                                <span> to remove data volumes</span>
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
                        <span class="text-red-300 font-bold">Failed to Stop Services</span>
                    </div>
                    <div class="text-gray-400 mt-1 ml-6">Check the error output above for details</div>
                </div>
            </div>
        HTML);

        return self::FAILURE;
    }
}
