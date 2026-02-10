<?php

declare(strict_types=1);

namespace App\Contracts;

interface HealthCheckInterface
{
    /**
     * Check if a service is healthy.
     *
     * @return array{name: string, healthy: bool, endpoint: string, type: string}
     */
    public function check(string $service): array;

    /**
     * Check all services and return their health status.
     *
     * @return array<int, array{name: string, healthy: bool, endpoint: string, type: string}>
     */
    public function checkAll(): array;

    /**
     * Get list of available services.
     *
     * @return array<string>
     */
    public function getServices(): array;
}
