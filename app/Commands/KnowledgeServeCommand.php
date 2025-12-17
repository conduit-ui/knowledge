<?php

declare(strict_types=1);

namespace App\Commands;

use App\Contracts\DockerServiceInterface;
use LaravelZero\Framework\Commands\Command;

class KnowledgeServeCommand extends Command
{
    protected $signature = 'serve
                            {action=start : Action to perform (install, start, stop, status, restart)}
                            {--f|foreground : Run in foreground with logs}';

    protected $description = 'Manage ChromaDB and embedding services for semantic search';

    private const CONFIG_DIR = '.config/knowledge/docker';

    private DockerServiceInterface $docker;

    public function handle(): int
    {
        // Resolve docker service at runtime for better testability
        $this->docker = app(DockerServiceInterface::class);

        $action = $this->argument('action');

        // @codeCoverageIgnoreStart
        // Type narrowing for PHPStan - Laravel's command system ensures string
        if (! is_string($action)) {
            return $this->invalidAction('');
        }
        // @codeCoverageIgnoreEnd

        return match ($action) {
            'install' => $this->install(),
            'start' => $this->start(),
            'stop' => $this->stop(),
            'status' => $this->status(),
            'restart' => $this->restart(),
            default => $this->invalidAction($action),
        };
    }

    private function install(): int
    {
        $this->info('Installing Knowledge ChromaDB Services');
        $this->newLine();

        // Step 1: Check Docker installed
        $dockerInstalled = false;
        $this->task('Checking Docker installation', function () use (&$dockerInstalled) {
            $dockerInstalled = $this->docker->isInstalled();

            return $dockerInstalled;
        });

        if (! $dockerInstalled) {
            $this->newLine();
            $this->showDockerInstallInstructions();

            return self::FAILURE;
        }

        // Step 2: Check Docker running
        $dockerRunning = false;
        $this->task('Checking Docker daemon', function () use (&$dockerRunning) {
            $dockerRunning = $this->docker->isRunning();

            return $dockerRunning;
        });

        if (! $dockerRunning) {
            $this->newLine();
            $this->error('Docker is installed but not running.');
            $this->line('Please start Docker Desktop and try again.');

            return self::FAILURE;
        }

        // Step 3: Setup config directory
        $configPath = $this->getConfigPath();
        $this->task('Setting up configuration directory', function () use ($configPath) {
            return $this->setupConfigDirectory($configPath);
        });

        // Step 4: Copy Docker files
        $this->task('Installing Docker configuration', function () use ($configPath) {
            return $this->copyDockerFiles($configPath);
        });

        // Step 5: Build images
        $buildSuccess = false;
        $this->task('Building embedding server image (this may take a few minutes)', function () use ($configPath, &$buildSuccess) {
            $result = $this->docker->compose($configPath, ['build']);
            $buildSuccess = $result['success'];

            return $buildSuccess;
        });

        if (! $buildSuccess) {
            $this->newLine();
            $this->error('Failed to build Docker images.');
            $this->line('Check Docker logs for details: docker compose logs');

            return self::FAILURE;
        }

        // Step 6: Start services
        $startSuccess = false;
        $this->task('Starting services', function () use ($configPath, &$startSuccess) {
            $result = $this->docker->compose($configPath, ['up', '-d']);
            $startSuccess = $result['success'];

            return $startSuccess;
        });

        if (! $startSuccess) {
            $this->newLine();
            $this->error('Failed to start services.');

            return self::FAILURE;
        }

        // Step 7: Wait for services
        $servicesReady = false;
        $isTesting = getenv('KNOWLEDGE_TESTING') !== false || app()->environment('testing') === true;
        $maxRetries = $isTesting ? 1 : 30;
        $sleepSeconds = $isTesting ? 0 : 2;

        $this->task('Waiting for services to be ready', function () use (&$servicesReady, $maxRetries, $sleepSeconds) {
            for ($i = 0; $i < $maxRetries; $i++) {
                if ($this->docker->checkEndpoint('http://localhost:8001/health')) {
                    $servicesReady = true;

                    return true;
                }
                // @codeCoverageIgnoreStart
                if ($sleepSeconds > 0) {
                    sleep($sleepSeconds);
                }
                // @codeCoverageIgnoreEnd
            }

            return false;
        });

        $this->newLine();

        if ($servicesReady) {
            $this->info('Installation complete!');
        } else {
            $this->warn('Services started but may still be initializing.');
            $this->line('Check status with: ./know knowledge:serve status');
        }

        $this->showPostInstallInfo();

        return self::SUCCESS;
    }

    private function start(): int
    {
        $configPath = $this->getConfigPath();

        if (! $this->hasConfig($configPath)) {
            $this->error('Services not installed. Run: ./know knowledge:serve install');

            return self::FAILURE;
        }

        if ($this->option('foreground') === true) {
            $this->info('Starting services in foreground (Ctrl+C to stop)...');
            $result = $this->docker->compose($configPath, ['up']);

            return $result['success'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Starting ChromaDB services...');

        $success = false;
        $this->task('Starting containers', function () use ($configPath, &$success) {
            $result = $this->docker->compose($configPath, ['up', '-d']);
            $success = $result['success'];

            return $success;
        });

        if ($success) {
            $this->newLine();
            $this->showEndpoints();
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    private function stop(): int
    {
        $configPath = $this->getConfigPath();

        if (! $this->hasConfig($configPath)) {
            $this->warn('No services configured.');

            return self::SUCCESS;
        }

        $this->info('Stopping ChromaDB services...');

        $success = false;
        $this->task('Stopping containers', function () use ($configPath, &$success) {
            $result = $this->docker->compose($configPath, ['down']);
            $success = $result['success'];

            return $success;
        });

        if ($success) {
            $this->newLine();
            $this->info('Services stopped. Data preserved in Docker volumes.');
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    private function status(): int
    {
        $configPath = $this->getConfigPath();

        $this->info('ChromaDB Service Status');
        $this->newLine();

        // Check installation
        if (! $this->hasConfig($configPath)) {
            $this->line('<fg=yellow>○</> Not installed');
            $this->line('  Run: ./know knowledge:serve install');

            return self::SUCCESS;
        }

        $this->line('<fg=green>✓</> Installed at: '.$configPath);
        $this->newLine();

        // Check Docker
        $this->line('<fg=cyan>Docker:</>');
        if (! $this->docker->isInstalled()) {
            $this->line('  <fg=red>✗</> Not installed');

            return self::SUCCESS;
        }

        $version = $this->docker->getVersion();
        $this->line("  <fg=green>✓</> Installed (v{$version})");

        if (! $this->docker->isRunning()) {
            $this->line('  <fg=red>✗</> Not running');

            return self::SUCCESS;
        }
        $this->line('  <fg=green>✓</> Running');
        $this->newLine();

        // Check services
        $this->line('<fg=cyan>Services:</>');

        $chromaOk = $this->docker->checkEndpoint('http://localhost:8000/api/v2/tenants');
        $embeddingOk = $this->docker->checkEndpoint('http://localhost:8001/health');

        $this->line(sprintf(
            '  ChromaDB:   %s http://localhost:8000',
            $chromaOk ? '<fg=green>✓</>' : '<fg=red>✗</>'
        ));
        $this->line(sprintf(
            '  Embeddings: %s http://localhost:8001',
            $embeddingOk ? '<fg=green>✓</>' : '<fg=red>✗</>'
        ));

        if (! $chromaOk || ! $embeddingOk) {
            $this->newLine();
            $this->line('Start services with: ./know knowledge:serve start');
        }

        return self::SUCCESS;
    }

    private function restart(): int
    {
        $configPath = $this->getConfigPath();

        if (! $this->hasConfig($configPath)) {
            $this->error('Services not installed. Run: ./know knowledge:serve install');

            return self::FAILURE;
        }

        $this->info('Restarting ChromaDB services...');

        $success = false;
        $this->task('Restarting containers', function () use ($configPath, &$success) {
            $result = $this->docker->compose($configPath, ['restart']);
            $success = $result['success'];

            return $success;
        });

        if ($success) {
            $this->newLine();
            $this->showEndpoints();
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    private function invalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->line('Valid actions: install, start, stop, status, restart');

        return self::FAILURE;
    }

    private function getConfigPath(): string
    {
        // Allow override for testing
        $overridePath = getenv('KNOWLEDGE_DOCKER_CONFIG_PATH');
        if ($overridePath !== false && $overridePath !== '') {
            return $overridePath;
        }

        // @codeCoverageIgnoreStart
        return getenv('HOME').'/'.self::CONFIG_DIR;
        // @codeCoverageIgnoreEnd
    }

    private function hasConfig(string $path): bool
    {
        return file_exists($path.'/docker-compose.yml');
    }

    private function getSourcePath(): string
    {
        // Works for both dev and phar
        return dirname(__DIR__, 2);
    }

    private function setupConfigDirectory(string $configPath): bool
    {
        if (! is_dir($configPath)) {
            mkdir($configPath, 0755, true);
        }

        $embeddingDir = $configPath.'/embedding-server';
        if (! is_dir($embeddingDir)) {
            mkdir($embeddingDir, 0755, true);
        }

        return is_dir($configPath) && is_dir($embeddingDir);
    }

    private function copyDockerFiles(string $configPath): bool
    {
        $sourcePath = $this->getSourcePath();

        $files = [
            'docker-compose.yml' => 'docker-compose.yml',
            'docker/embedding-server/Dockerfile' => 'embedding-server/Dockerfile',
            'docker/embedding-server/server.py' => 'embedding-server/server.py',
        ];

        foreach ($files as $source => $dest) {
            $sourceFile = $sourcePath.'/'.$source;
            $destFile = $configPath.'/'.$dest;

            if (file_exists($sourceFile)) {
                // Special handling for docker-compose.yml to fix path
                if ($source === 'docker-compose.yml') {
                    $content = file_get_contents($sourceFile);
                    if ($content === false) {
                        // @codeCoverageIgnoreStart
                        return false;
                        // @codeCoverageIgnoreEnd
                    }
                    // Rewrite the context path for installed location
                    $content = str_replace(
                        'context: ./docker/embedding-server',
                        'context: ./embedding-server',
                        $content
                    );
                    file_put_contents($destFile, $content);
                } else {
                    copy($sourceFile, $destFile);
                }
            } else {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
        }

        return true;
    }

    private function showDockerInstallInstructions(): void
    {
        $os = $this->docker->getHostOs();
        $url = $this->docker->getInstallUrl();

        $this->error('Docker is not installed.');
        $this->newLine();

        $this->line('<fg=cyan>Installation Instructions:</>');

        // @codeCoverageIgnoreStart
        // OS-specific instructions - only one branch executes per platform
        switch ($os) {
            case 'macos':
                $this->line('  1. Download Docker Desktop for Mac');
                $this->line('  2. Open the .dmg and drag to Applications');
                $this->line('  3. Launch Docker Desktop');
                break;
            case 'linux':
                $this->line('  1. Follow the official installation guide for your distro');
                $this->line('  2. Add your user to the docker group: sudo usermod -aG docker $USER');
                $this->line('  3. Log out and back in, then start Docker');
                break;
            case 'windows':
                $this->line('  1. Download Docker Desktop for Windows');
                $this->line('  2. Run the installer and follow prompts');
                $this->line('  3. Launch Docker Desktop');
                break;
            default:
                $this->line('  Follow the official Docker installation guide');
        }
        // @codeCoverageIgnoreEnd

        $this->newLine();
        $this->line("<fg=cyan>Download:</> {$url}");
        $this->newLine();
        $this->line('After installing, run: ./know knowledge:serve install');
    }

    private function showEndpoints(): void
    {
        $this->line('<fg=cyan>Endpoints:</>');
        $this->line('  ChromaDB:   http://localhost:8000');
        $this->line('  Embeddings: http://localhost:8001');
    }

    private function showPostInstallInfo(): void
    {
        $this->newLine();
        $this->showEndpoints();

        $this->newLine();
        $this->line('<fg=cyan>Auto-start on reboot:</>');
        $this->line('  1. Enable "Start Docker Desktop when you sign in" in Docker settings');
        $this->line('  2. Services auto-restart when Docker starts');

        $this->newLine();
        $this->line('<fg=cyan>Data persistence:</>');
        $this->line('  Config: ~/.config/knowledge/docker/');
        $this->line('  Data:   Docker volume (knowledge_chromadb_data)');
        $this->line('  <fg=green>✓</> Survives reboots and upgrades');
        $this->line('  <fg=yellow>!</> Lost only with: docker compose down -v');

        $this->newLine();
        $this->line('<fg=cyan>Enable semantic search:</>');
        $this->line('  ./know knowledge:config set chromadb.enabled true');

        $this->newLine();
        $this->line('<fg=cyan>Commands:</>');
        $this->line('  ./know knowledge:serve status   - Check service status');
        $this->line('  ./know knowledge:serve stop     - Stop services');
        $this->line('  ./know knowledge:serve start    - Start services');
        $this->line('  ./know knowledge:index          - Index entries for search');
    }
}
