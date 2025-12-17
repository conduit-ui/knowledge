<?php

declare(strict_types=1);

use App\Commands\KnowledgeServeCommand;
use App\Contracts\DockerServiceInterface;
use Tests\Support\MockDockerService;

function cleanupTestConfig(string $path): void
{
    if (is_dir($path)) {
        @array_map('unlink', glob($path.'/embedding-server/*') ?: []);
        @array_map('unlink', glob($path.'/*') ?: []);
        @rmdir($path.'/embedding-server');
        @rmdir($path);
    }
}

function setupTestConfig(string $path): void
{
    if (! is_dir($path.'/embedding-server')) {
        mkdir($path.'/embedding-server', 0755, true);
    }
    file_put_contents($path.'/docker-compose.yml', 'version: "3"');
}

describe('KnowledgeServeCommand', function () {
    beforeEach(function () {
        // Use temp directory for tests
        $this->configPath = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        putenv('KNOWLEDGE_DOCKER_CONFIG_PATH='.$this->configPath);
        putenv('KNOWLEDGE_TESTING=1');

        // Clean any existing
        cleanupTestConfig($this->configPath);

        // Create mock - must be fresh for each test
        $this->mockDocker = new MockDockerService;

        // Forget any existing bindings to ensure fresh resolution
        $this->app->forgetInstance(DockerServiceInterface::class);
        $this->app->forgetInstance(KnowledgeServeCommand::class);

        // Bind mock as the implementation
        $this->app->bind(DockerServiceInterface::class, fn () => $this->mockDocker);
    });

    afterEach(function () {
        cleanupTestConfig($this->configPath);
        putenv('KNOWLEDGE_DOCKER_CONFIG_PATH');
        putenv('KNOWLEDGE_TESTING');
    });

    describe('command registration', function () {
        it('is registered with correct signature', function () {
            setupTestConfig($this->configPath);
            $command = $this->app->make(KnowledgeServeCommand::class);
            expect($command->getName())->toBe('knowledge:serve');
        });

        it('has correct description', function () {
            $command = $this->app->make(KnowledgeServeCommand::class);
            expect($command->getDescription())->toBe('Manage ChromaDB and embedding services for semantic search');
        });

        it('defaults to start action', function () {
            $command = $this->app->make(KnowledgeServeCommand::class);
            $definition = $command->getDefinition();
            expect($definition->getArgument('action')->getDefault())->toBe('start');
        });

        it('has foreground option', function () {
            $command = $this->app->make(KnowledgeServeCommand::class);
            $definition = $command->getDefinition();
            expect($definition->hasOption('foreground'))->toBeTrue();
        });
    });

    describe('invalid action', function () {
        it('shows error for invalid action', function () {
            $this->artisan('knowledge:serve', ['action' => 'invalid'])
                ->assertFailed()
                ->expectsOutputToContain('Invalid action: invalid');
        });

        it('shows valid actions list', function () {
            $this->artisan('knowledge:serve', ['action' => 'unknown'])
                ->assertFailed()
                ->expectsOutputToContain('Valid actions: install, start, stop, status, restart');
        });
    });

    describe('install action', function () {
        it('checks Docker installation', function () {
            $this->mockDocker->installed = false;

            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertFailed()
                ->expectsOutputToContain('Docker is not installed');
        });

        it('shows install instructions for macOS', function () {
            $this->mockDocker->installed = false;
            $this->mockDocker->hostOs = 'macos';

            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertFailed()
                ->expectsOutputToContain('Download Docker Desktop for Mac')
                ->expectsOutputToContain('docs.docker.com/desktop/install/mac-install');
        });

        it('shows install instructions for Linux', function () {
            $this->mockDocker->installed = false;
            $this->mockDocker->hostOs = 'linux';

            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertFailed()
                ->expectsOutputToContain('sudo usermod -aG docker')
                ->expectsOutputToContain('docs.docker.com/engine/install');
        });

        it('shows install instructions for Windows', function () {
            $this->mockDocker->installed = false;
            $this->mockDocker->hostOs = 'windows';

            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertFailed()
                ->expectsOutputToContain('Docker Desktop for Windows')
                ->expectsOutputToContain('docs.docker.com/desktop/install/windows-install');
        });

        it('shows install instructions for unknown OS', function () {
            $this->mockDocker->installed = false;
            $this->mockDocker->hostOs = 'unknown';

            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertFailed()
                ->expectsOutputToContain('Follow the official Docker installation guide');
        });

        it('checks Docker is running', function () {
            $this->mockDocker->running = false;

            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertFailed()
                ->expectsOutputToContain('Docker is installed but not running');
        });

        it('fails when build fails', function () {
            $this->mockDocker->composeSuccess = false;

            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertFailed()
                ->expectsOutputToContain('Failed to build Docker images');
        });

        it('shows success message on completion', function () {
            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertSuccessful()
                ->expectsOutputToContain('Installation complete');
        });

        it('shows endpoints after install', function () {
            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertSuccessful()
                ->expectsOutputToContain('http://localhost:8000')
                ->expectsOutputToContain('http://localhost:8001');
        });

        it('shows auto-start info', function () {
            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertSuccessful()
                ->expectsOutputToContain('Auto-start on reboot')
                ->expectsOutputToContain('Start Docker Desktop when you sign in');
        });

        it('shows data persistence info', function () {
            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertSuccessful()
                ->expectsOutputToContain('Data persistence')
                ->expectsOutputToContain('Docker volume')
                ->expectsOutputToContain('Survives reboots');
        });

        it('calls docker compose build', function () {
            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertSuccessful();

            $buildCall = collect($this->mockDocker->composeCalls)
                ->first(fn ($call) => in_array('build', $call['args']));

            expect($buildCall)->not->toBeNull();
        });

        it('calls docker compose up', function () {
            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertSuccessful();

            $upCall = collect($this->mockDocker->composeCalls)
                ->first(fn ($call) => in_array('up', $call['args']));

            expect($upCall)->not->toBeNull();
            expect($upCall['args'])->toContain('-d');
        });

        it('warns when services not ready', function () {
            $this->mockDocker->endpointsHealthy = false;

            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertSuccessful()
                ->expectsOutputToContain('may still be initializing');
        });

        it('fails when start fails after build', function () {
            // Create a mock that succeeds for build but fails for up
            $callCount = 0;
            $mock = $this->mockDocker;
            $originalCompose = fn () => ['success' => true, 'output' => 'OK', 'exitCode' => 0];

            $this->app->bind(DockerServiceInterface::class, function () use ($mock, &$callCount) {
                return new class($mock, $callCount) implements DockerServiceInterface
                {
                    private int $callCount = 0;

                    public function __construct(
                        private MockDockerService $mock,
                        private int &$externalCount
                    ) {
                        $this->callCount = &$externalCount;
                    }

                    public function isInstalled(): bool
                    {
                        return $this->mock->isInstalled();
                    }

                    public function isRunning(): bool
                    {
                        return $this->mock->isRunning();
                    }

                    public function getHostOs(): string
                    {
                        return $this->mock->getHostOs();
                    }

                    public function getInstallUrl(): string
                    {
                        return $this->mock->getInstallUrl();
                    }

                    public function compose(string $workingDir, array $args): array
                    {
                        $this->callCount++;
                        // First call (build) succeeds, second call (up) fails
                        if ($this->callCount > 1) {
                            return ['success' => false, 'output' => 'Error', 'exitCode' => 1];
                        }

                        return ['success' => true, 'output' => 'OK', 'exitCode' => 0];
                    }

                    public function checkEndpoint(string $url, int $timeoutSeconds = 2): bool
                    {
                        return $this->mock->checkEndpoint($url, $timeoutSeconds);
                    }

                    public function getVersion(): ?string
                    {
                        return $this->mock->getVersion();
                    }
                };
            });

            $this->artisan('knowledge:serve', ['action' => 'install'])
                ->assertFailed()
                ->expectsOutputToContain('Failed to start services');
        });
    });

    describe('start action', function () {
        beforeEach(function () {
            // Setup minimal config
            mkdir($this->configPath.'/embedding-server', 0755, true);
            file_put_contents($this->configPath.'/docker-compose.yml', 'version: "3"');
        });

        it('fails if not installed', function () {
            @unlink($this->configPath.'/docker-compose.yml');

            $this->artisan('knowledge:serve', ['action' => 'start'])
                ->assertFailed()
                ->expectsOutputToContain('Services not installed');
        });

        it('starts services in detached mode', function () {
            $this->artisan('knowledge:serve', ['action' => 'start'])
                ->assertSuccessful();

            $upCall = collect($this->mockDocker->composeCalls)
                ->first(fn ($call) => in_array('up', $call['args']));

            expect($upCall)->not->toBeNull();
            expect($upCall['args'])->toContain('-d');
        });

        it('shows endpoints after start', function () {
            $this->artisan('knowledge:serve', ['action' => 'start'])
                ->assertSuccessful()
                ->expectsOutputToContain('http://localhost:8000');
        });

        it('handles start failure', function () {
            $this->mockDocker->composeSuccess = false;

            $this->artisan('knowledge:serve', ['action' => 'start'])
                ->assertFailed();
        });

        it('supports foreground mode', function () {
            $this->artisan('knowledge:serve', ['action' => 'start', '--foreground' => true])
                ->assertSuccessful()
                ->expectsOutputToContain('Starting services in foreground');

            $upCall = collect($this->mockDocker->composeCalls)
                ->first(fn ($call) => in_array('up', $call['args']));

            expect($upCall)->not->toBeNull();
            expect($upCall['args'])->not->toContain('-d');
        });

        it('handles foreground mode failure', function () {
            $this->mockDocker->composeSuccess = false;

            $this->artisan('knowledge:serve', ['action' => 'start', '--foreground' => true])
                ->assertFailed();
        });
    });

    describe('stop action', function () {
        beforeEach(function () {
            mkdir($this->configPath.'/embedding-server', 0755, true);
            file_put_contents($this->configPath.'/docker-compose.yml', 'version: "3"');
        });

        it('succeeds even if not configured', function () {
            @unlink($this->configPath.'/docker-compose.yml');

            $this->artisan('knowledge:serve', ['action' => 'stop'])
                ->assertSuccessful()
                ->expectsOutputToContain('No services configured');
        });

        it('calls docker compose down', function () {
            $this->artisan('knowledge:serve', ['action' => 'stop'])
                ->assertSuccessful();

            $downCall = collect($this->mockDocker->composeCalls)
                ->first(fn ($call) => in_array('down', $call['args']));

            expect($downCall)->not->toBeNull();
        });

        it('shows data preserved message', function () {
            $this->artisan('knowledge:serve', ['action' => 'stop'])
                ->assertSuccessful()
                ->expectsOutputToContain('Data preserved');
        });
    });

    describe('status action', function () {
        it('shows not installed when no config', function () {
            $this->artisan('knowledge:serve', ['action' => 'status'])
                ->assertSuccessful()
                ->expectsOutputToContain('Not installed')
                ->expectsOutputToContain('knowledge:serve install');
        });

        it('shows Docker not installed', function () {
            mkdir($this->configPath.'/embedding-server', 0755, true);
            file_put_contents($this->configPath.'/docker-compose.yml', 'version: "3"');
            $this->mockDocker->installed = false;

            $this->artisan('knowledge:serve', ['action' => 'status'])
                ->assertSuccessful()
                ->expectsOutputToContain('Not installed');
        });

        it('shows Docker version when installed', function () {
            mkdir($this->configPath.'/embedding-server', 0755, true);
            file_put_contents($this->configPath.'/docker-compose.yml', 'version: "3"');

            $this->artisan('knowledge:serve', ['action' => 'status'])
                ->assertSuccessful()
                ->expectsOutputToContain('v24.0.0');
        });

        it('shows Docker not running', function () {
            mkdir($this->configPath.'/embedding-server', 0755, true);
            file_put_contents($this->configPath.'/docker-compose.yml', 'version: "3"');
            $this->mockDocker->running = false;

            $this->artisan('knowledge:serve', ['action' => 'status'])
                ->assertSuccessful()
                ->expectsOutputToContain('Not running');
        });

        it('shows service status', function () {
            mkdir($this->configPath.'/embedding-server', 0755, true);
            file_put_contents($this->configPath.'/docker-compose.yml', 'version: "3"');

            $this->artisan('knowledge:serve', ['action' => 'status'])
                ->assertSuccessful()
                ->expectsOutputToContain('ChromaDB:')
                ->expectsOutputToContain('Embeddings:');
        });

        it('suggests start when services not running', function () {
            mkdir($this->configPath.'/embedding-server', 0755, true);
            file_put_contents($this->configPath.'/docker-compose.yml', 'version: "3"');
            $this->mockDocker->endpointsHealthy = false;

            $this->artisan('knowledge:serve', ['action' => 'status'])
                ->assertSuccessful()
                ->expectsOutputToContain('knowledge:serve start');
        });
    });

    describe('restart action', function () {
        beforeEach(function () {
            mkdir($this->configPath.'/embedding-server', 0755, true);
            file_put_contents($this->configPath.'/docker-compose.yml', 'version: "3"');
        });

        it('fails if not installed', function () {
            @unlink($this->configPath.'/docker-compose.yml');

            $this->artisan('knowledge:serve', ['action' => 'restart'])
                ->assertFailed()
                ->expectsOutputToContain('Services not installed');
        });

        it('calls docker compose restart', function () {
            $this->artisan('knowledge:serve', ['action' => 'restart'])
                ->assertSuccessful();

            $restartCall = collect($this->mockDocker->composeCalls)
                ->first(fn ($call) => in_array('restart', $call['args']));

            expect($restartCall)->not->toBeNull();
        });

        it('shows endpoints after restart', function () {
            $this->artisan('knowledge:serve', ['action' => 'restart'])
                ->assertSuccessful()
                ->expectsOutputToContain('http://localhost:8000');
        });
    });
});
