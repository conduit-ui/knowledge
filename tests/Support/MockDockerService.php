<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\DockerServiceInterface;

class MockDockerService implements DockerServiceInterface
{
    public bool $installed = true;

    public bool $running = true;

    public string $hostOs = 'macos';

    public bool $composeSuccess = true;

    public bool $endpointsHealthy = true;

    public string $version = '24.0.0';

    /** @var array<array{args: array<string>, result: array{success: bool, output: string, exitCode: int}}> */
    public array $composeCalls = [];

    public function isInstalled(): bool
    {
        return $this->installed;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function getHostOs(): string
    {
        return $this->hostOs;
    }

    public function getInstallUrl(): string
    {
        return match ($this->hostOs) {
            'macos' => 'https://docs.docker.com/desktop/install/mac-install/',
            'linux' => 'https://docs.docker.com/engine/install/',
            'windows' => 'https://docs.docker.com/desktop/install/windows-install/',
            default => 'https://docs.docker.com/get-docker/',
        };
    }

    public function compose(string $workingDir, array $args): array
    {
        $result = [
            'success' => $this->composeSuccess,
            'output' => $this->composeSuccess ? 'OK' : 'Error',
            'exitCode' => $this->composeSuccess ? 0 : 1,
        ];

        $this->composeCalls[] = [
            'args' => $args,
            'result' => $result,
        ];

        return $result;
    }

    public function checkEndpoint(string $url, int $timeoutSeconds = 2): bool
    {
        return $this->endpointsHealthy;
    }

    public function getVersion(): ?string
    {
        return $this->installed ? $this->version : null;
    }

    public function reset(): void
    {
        $this->installed = true;
        $this->running = true;
        $this->hostOs = 'macos';
        $this->composeSuccess = true;
        $this->endpointsHealthy = true;
        $this->version = '24.0.0';
        $this->composeCalls = [];
    }
}
