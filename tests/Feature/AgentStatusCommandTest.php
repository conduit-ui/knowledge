<?php

declare(strict_types=1);

use App\Services\AgentHealthService;

describe('AgentStatusCommand', function (): void {
    beforeEach(function (): void {
        $this->healthService = mock(AgentHealthService::class);
        app()->instance(AgentHealthService::class, $this->healthService);
    });

    it('returns exit code 0 when all dependencies are healthy', function (): void {
        $this->healthService->shouldReceive('checkRedis')
            ->once()
            ->andReturn(['ok' => true, 'ping' => 'PONG', 'error' => null]);

        $this->healthService->shouldReceive('checkQdrant')
            ->once()
            ->andReturn(['ok' => true, 'collection' => 'knowledge', 'points_count' => 42, 'error' => null]);

        $this->healthService->shouldReceive('getAgentTimestamps')
            ->once()
            ->andReturn([]);

        $this->artisan('agent:status', ['--json' => true])
            ->assertExitCode(0);
    });

    it('returns exit code 1 when Redis is down', function (): void {
        $this->healthService->shouldReceive('checkRedis')
            ->once()
            ->andReturn(['ok' => false, 'ping' => '', 'error' => 'Cannot connect to localhost:6379']);

        $this->healthService->shouldReceive('checkQdrant')
            ->once()
            ->andReturn(['ok' => true, 'collection' => 'knowledge', 'points_count' => 10, 'error' => null]);

        $this->healthService->shouldNotReceive('getAgentTimestamps');

        $this->artisan('agent:status', ['--json' => true])
            ->assertExitCode(1);
    });

    it('returns exit code 1 when Qdrant is down', function (): void {
        $this->healthService->shouldReceive('checkRedis')
            ->once()
            ->andReturn(['ok' => true, 'ping' => 'PONG', 'error' => null]);

        $this->healthService->shouldReceive('checkQdrant')
            ->once()
            ->andReturn(['ok' => false, 'collection' => 'knowledge', 'points_count' => 0, 'error' => 'HTTP 503']);

        $this->healthService->shouldReceive('getAgentTimestamps')
            ->once()
            ->andReturn([]);

        $this->artisan('agent:status', ['--json' => true])
            ->assertExitCode(1);
    });

    it('returns exit code 1 when both dependencies are down', function (): void {
        $this->healthService->shouldReceive('checkRedis')
            ->once()
            ->andReturn(['ok' => false, 'ping' => '', 'error' => 'Connection refused']);

        $this->healthService->shouldReceive('checkQdrant')
            ->once()
            ->andReturn(['ok' => false, 'collection' => 'knowledge', 'points_count' => 0, 'error' => 'HTTP 503']);

        $this->healthService->shouldNotReceive('getAgentTimestamps');

        $this->artisan('agent:status', ['--json' => true])
            ->assertExitCode(1);
    });

    it('outputs valid JSON with all required fields when healthy', function (): void {
        $this->healthService->shouldReceive('checkRedis')
            ->once()
            ->andReturn(['ok' => true, 'ping' => 'PONG', 'error' => null]);

        $this->healthService->shouldReceive('checkQdrant')
            ->once()
            ->andReturn(['ok' => true, 'collection' => 'knowledge', 'points_count' => 42, 'error' => null]);

        $this->healthService->shouldReceive('getAgentTimestamps')
            ->once()
            ->andReturn([]);

        $this->artisan('agent:status', ['--json' => true])
            ->expectsOutputToContain('"healthy": true')
            ->assertExitCode(0);
    });

    it('outputs valid JSON with error details when degraded', function (): void {
        $this->healthService->shouldReceive('checkRedis')
            ->once()
            ->andReturn(['ok' => false, 'ping' => '', 'error' => 'Cannot connect to localhost:6379']);

        $this->healthService->shouldReceive('checkQdrant')
            ->once()
            ->andReturn(['ok' => false, 'collection' => 'knowledge', 'points_count' => 0, 'error' => 'HTTP 404']);

        $this->healthService->shouldNotReceive('getAgentTimestamps');

        $this->artisan('agent:status', ['--json' => true])
            ->expectsOutputToContain('"healthy": false')
            ->assertExitCode(1);
    });

    it('reports agent timestamps when Redis is available', function (): void {
        $this->healthService->shouldReceive('checkRedis')
            ->once()
            ->andReturn(['ok' => true, 'ping' => 'PONG', 'error' => null]);

        $this->healthService->shouldReceive('checkQdrant')
            ->once()
            ->andReturn(['ok' => true, 'collection' => 'knowledge', 'points_count' => 5, 'error' => null]);

        $this->healthService->shouldReceive('getAgentTimestamps')
            ->once()
            ->andReturn([
                'indexer' => '2026-02-10T12:00:00Z',
                'syncer' => '2026-02-10T11:30:00Z',
            ]);

        $this->artisan('agent:status', ['--json' => true])
            ->expectsOutputToContain('indexer')
            ->assertExitCode(0);
    });

    it('returns empty agents when Redis is down', function (): void {
        $this->healthService->shouldReceive('checkRedis')
            ->once()
            ->andReturn(['ok' => false, 'ping' => '', 'error' => 'Connection refused']);

        $this->healthService->shouldReceive('checkQdrant')
            ->once()
            ->andReturn(['ok' => true, 'collection' => 'knowledge', 'points_count' => 5, 'error' => null]);

        $this->healthService->shouldNotReceive('getAgentTimestamps');

        $this->artisan('agent:status', ['--json' => true])
            ->expectsOutputToContain('"agents": []')
            ->assertExitCode(1);
    });

    it('renders human-readable output without --json flag when healthy', function (): void {
        $this->healthService->shouldReceive('checkRedis')
            ->once()
            ->andReturn(['ok' => true, 'ping' => 'PONG', 'error' => null]);

        $this->healthService->shouldReceive('checkQdrant')
            ->once()
            ->andReturn(['ok' => true, 'collection' => 'knowledge', 'points_count' => 10, 'error' => null]);

        $this->healthService->shouldReceive('getAgentTimestamps')
            ->once()
            ->andReturn([]);

        $this->artisan('agent:status')
            ->expectsOutputToContain('Agent Status:')
            ->expectsOutputToContain('Redis:')
            ->expectsOutputToContain('Qdrant:')
            ->assertExitCode(0);
    });

    it('shows DEGRADED and error details in human-readable output', function (): void {
        $this->healthService->shouldReceive('checkRedis')
            ->once()
            ->andReturn(['ok' => false, 'ping' => '', 'error' => 'Connection refused']);

        $this->healthService->shouldReceive('checkQdrant')
            ->once()
            ->andReturn(['ok' => false, 'collection' => 'knowledge', 'points_count' => 0, 'error' => 'HTTP 404']);

        $this->healthService->shouldNotReceive('getAgentTimestamps');

        $this->artisan('agent:status')
            ->expectsOutputToContain('DEGRADED')
            ->expectsOutputToContain('FAIL')
            ->expectsOutputToContain('Error:')
            ->assertExitCode(1);
    });

    it('shows agent last events in human-readable output', function (): void {
        $this->healthService->shouldReceive('checkRedis')
            ->once()
            ->andReturn(['ok' => true, 'ping' => 'PONG', 'error' => null]);

        $this->healthService->shouldReceive('checkQdrant')
            ->once()
            ->andReturn(['ok' => true, 'collection' => 'knowledge', 'points_count' => 5, 'error' => null]);

        $this->healthService->shouldReceive('getAgentTimestamps')
            ->once()
            ->andReturn(['indexer' => '2026-02-10T12:00:00Z']);

        $this->artisan('agent:status')
            ->expectsOutputToContain('Agent Last Events:')
            ->expectsOutputToContain('indexer: 2026-02-10T12:00:00Z')
            ->assertExitCode(0);
    });

    it('includes Qdrant collection name and points count in JSON', function (): void {
        $this->healthService->shouldReceive('checkRedis')
            ->once()
            ->andReturn(['ok' => true, 'ping' => 'PONG', 'error' => null]);

        $this->healthService->shouldReceive('checkQdrant')
            ->once()
            ->andReturn(['ok' => true, 'collection' => 'test_collection', 'points_count' => 99, 'error' => null]);

        $this->healthService->shouldReceive('getAgentTimestamps')
            ->once()
            ->andReturn([]);

        $this->artisan('agent:status', ['--json' => true])
            ->expectsOutputToContain('test_collection')
            ->assertExitCode(0);
    });
});
