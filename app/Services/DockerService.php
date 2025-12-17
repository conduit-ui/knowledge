<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DockerServiceInterface;
use Symfony\Component\Process\Process;

class DockerService implements DockerServiceInterface
{
    private const INSTALL_URLS = [
        'macos' => 'https://docs.docker.com/desktop/install/mac-install/',
        'linux' => 'https://docs.docker.com/engine/install/',
        'windows' => 'https://docs.docker.com/desktop/install/windows-install/',
        'unknown' => 'https://docs.docker.com/get-docker/',
    ];

    public function isInstalled(): bool
    {
        $process = new Process(['docker', '--version']);
        $process->run();

        return $process->isSuccessful();
    }

    public function isRunning(): bool
    {
        $process = new Process(['docker', 'info']);
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful();
    }

    public function getHostOs(): string
    {
        // @codeCoverageIgnoreStart
        return match (PHP_OS_FAMILY) {
            'Darwin' => 'macos',
            'Linux' => 'linux',
            'Windows' => 'windows',
            default => 'unknown',
        };
        // @codeCoverageIgnoreEnd
    }

    public function getInstallUrl(): string
    {
        return self::INSTALL_URLS[$this->getHostOs()];
    }

    public function compose(string $workingDir, array $args): array
    {
        $command = array_merge(['docker', 'compose'], $args);

        $process = new Process($command, $workingDir);
        $process->setTimeout(600); // 10 minutes for builds

        $output = '';
        // @codeCoverageIgnoreStart
        // Callback execution depends on Docker process output availability
        $process->run(function ($type, $buffer) use (&$output): void {
            $output .= $buffer;
        });
        // @codeCoverageIgnoreEnd

        return [
            'success' => $process->isSuccessful(),
            'output' => $output,
            'exitCode' => $process->getExitCode() ?? 1,
        ];
    }

    public function checkEndpoint(string $url, int $timeoutSeconds = 2): bool
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => $timeoutSeconds,
                    'ignore_errors' => true,
                ],
            ]);

            $result = @file_get_contents($url, false, $context);

            return $result !== false;
            // @codeCoverageIgnoreStart
        } catch (\Throwable) {
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    public function getVersion(): ?string
    {
        $process = new Process(['docker', '--version']);
        $process->run();

        // @codeCoverageIgnoreStart
        if (! $process->isSuccessful()) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        // Extract version from "Docker version 24.0.6, build ed223bc"
        $output = trim($process->getOutput());
        if (preg_match('/Docker version ([\d.]+)/', $output, $matches)) {
            return $matches[1];
        }

        // @codeCoverageIgnoreStart
        return $output;
        // @codeCoverageIgnoreEnd
    }
}
