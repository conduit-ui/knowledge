<?php

declare(strict_types=1);

namespace App\Contracts;

interface DockerServiceInterface
{
    /**
     * Check if Docker is installed on the system.
     */
    public function isInstalled(): bool;

    /**
     * Check if Docker daemon is running.
     */
    public function isRunning(): bool;

    /**
     * Get the host operating system.
     *
     * @return string 'macos'|'linux'|'windows'|'unknown'
     */
    public function getHostOs(): string;

    /**
     * Get Docker installation instructions URL for the host OS.
     */
    public function getInstallUrl(): string;

    /**
     * Run docker compose command.
     *
     * @param  array<string>  $args
     * @return array{success: bool, output: string, exitCode: int}
     */
    public function compose(string $workingDir, array $args): array;

    /**
     * Check if a service endpoint is healthy.
     */
    public function checkEndpoint(string $url, int $timeoutSeconds = 2): bool;

    /**
     * Get Docker version info.
     */
    public function getVersion(): ?string;
}
