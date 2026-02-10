<?php

declare(strict_types=1);

use App\Services\OdinSyncService;

beforeEach(function (): void {
    $this->odinMock = Mockery::mock(OdinSyncService::class);
    $this->app->instance(OdinSyncService::class, $this->odinMock);
});

describe('ProjectsCommand', function (): void {
    it('shows local info when --local flag is used', function (): void {
        $this->artisan('projects', ['--local' => true])
            ->assertSuccessful()
            ->expectsOutput('Local project listing requires Qdrant collection enumeration.');
    });

    it('fails when odin sync is disabled', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(false);

        $this->artisan('projects')
            ->assertFailed()
            ->expectsOutput('Odin sync is disabled. Set ODIN_SYNC_ENABLED=true to enable.');
    });

    it('warns when Odin is unreachable', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('isAvailable')->once()->andReturn(false);

        $this->artisan('projects')
            ->assertSuccessful()
            ->expectsOutput('Odin server is not reachable. Cannot list remote projects.');
    });

    it('shows empty projects message', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->odinMock->shouldReceive('listProjects')->once()->andReturn([]);

        $this->artisan('projects')
            ->assertSuccessful()
            ->expectsOutput('No projects found on Odin server.');
    });

    it('displays projects table', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->odinMock->shouldReceive('listProjects')->once()->andReturn([
            ['name' => 'project-alpha', 'entry_count' => 42, 'last_synced' => '2025-06-01T12:00:00+00:00'],
            ['name' => 'project-beta', 'entry_count' => 15, 'last_synced' => null],
        ]);

        $this->artisan('projects')
            ->assertSuccessful();
    });
});
