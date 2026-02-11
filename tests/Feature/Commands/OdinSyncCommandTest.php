<?php

declare(strict_types=1);

use App\Services\OdinSyncService;
use App\Services\QdrantService;

beforeEach(function (): void {
    $this->odinMock = Mockery::mock(OdinSyncService::class);
    $this->qdrantMock = Mockery::mock(QdrantService::class);
    $this->app->instance(OdinSyncService::class, $this->odinMock);
    $this->app->instance(QdrantService::class, $this->qdrantMock);
});

describe('OdinSyncCommand basic', function (): void {
    it('fails when odin sync is disabled', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(false);

        $this->artisan('sync:odin')
            ->assertFailed()
            ->expectsOutput('Odin sync is disabled. Set ODIN_SYNC_ENABLED=true to enable.');
    });

    it('shows status only with --status flag', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('getStatus')->once()->andReturn([
            'status' => 'synced',
            'pending' => 0,
            'last_synced' => '2025-06-01T12:00:00+00:00',
            'last_error' => null,
        ]);

        config(['services.odin.url' => 'http://odin.local']);

        $this->artisan('sync:odin', ['--status' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Odin Sync Status');
    });

    it('clears queue with --clear flag', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('clearQueue')->once();

        $this->artisan('sync:odin', ['--clear' => true])
            ->assertSuccessful()
            ->expectsOutput('Sync queue cleared.');
    });
});

describe('OdinSyncCommand connectivity', function (): void {
    it('warns when Odin is unreachable', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('isAvailable')->once()->andReturn(false);
        $this->odinMock->shouldReceive('getStatus')->once()->andReturn([
            'status' => 'pending',
            'pending' => 3,
            'last_synced' => null,
            'last_error' => null,
        ]);

        $this->artisan('sync:odin')
            ->assertSuccessful()
            ->expectsOutputToContain('Odin server is not reachable')
            ->expectsOutputToContain('Pending operations: 3');
    });
});

describe('OdinSyncCommand push', function (): void {
    it('pushes queued items when --push is specified', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->odinMock->shouldReceive('processQueue')->once()->andReturn([
            'synced' => 5,
            'failed' => 0,
            'remaining' => 0,
        ]);

        $this->artisan('sync:odin', ['--push' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Odin Sync Summary');
    });
});

describe('OdinSyncCommand pull', function (): void {
    it('pulls and merges entries when --pull is specified', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->odinMock->shouldReceive('pullFromOdin')
            ->once()
            ->with('default')
            ->andReturn([
                [
                    'title' => 'Remote Entry',
                    'content' => 'Remote content',
                    'updated_at' => '2025-06-01T12:00:00+00:00',
                    'category' => null,
                    'tags' => [],
                    'module' => null,
                    'priority' => 'medium',
                    'confidence' => 50,
                    'status' => 'draft',
                    'usage_count' => 0,
                ],
            ]);

        // No local match found
        $this->qdrantMock->shouldReceive('search')
            ->once()
            ->with('Remote Entry', [], 1, 'default')
            ->andReturn(collect());

        // Upsert the new entry
        $this->qdrantMock->shouldReceive('upsert')
            ->once()
            ->andReturn(true);

        $this->odinMock->shouldNotReceive('resolveConflict');

        $this->artisan('sync:odin', ['--pull' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Odin Sync Summary');
    });

    it('resolves conflicts with last-write-wins on pull', function (): void {
        $localEntry = [
            'id' => 'local-1',
            'title' => 'Shared Entry',
            'content' => 'Local content',
            'updated_at' => '2025-05-01T12:00:00+00:00',
        ];

        $remoteEntry = [
            'title' => 'Shared Entry',
            'content' => 'Remote content (newer)',
            'updated_at' => '2025-06-01T12:00:00+00:00',
            'category' => null,
            'tags' => [],
            'module' => null,
            'priority' => 'medium',
            'confidence' => 50,
            'status' => 'draft',
            'usage_count' => 0,
        ];

        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->odinMock->shouldReceive('pullFromOdin')
            ->once()
            ->with('default')
            ->andReturn([$remoteEntry]);

        $this->qdrantMock->shouldReceive('search')
            ->once()
            ->with('Shared Entry', [], 1, 'default')
            ->andReturn(collect([$localEntry]));

        // Remote is newer, should win
        $this->odinMock->shouldReceive('resolveConflict')
            ->once()
            ->with($localEntry, $remoteEntry)
            ->andReturn($remoteEntry);

        $this->qdrantMock->shouldReceive('upsert')
            ->once()
            ->andReturn(true);

        $this->artisan('sync:odin', ['--pull' => true])
            ->assertSuccessful();
    });

    it('skips entries with empty title on pull', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->odinMock->shouldReceive('pullFromOdin')
            ->once()
            ->andReturn([
                ['title' => '', 'content' => 'No title'],
            ]);

        $this->qdrantMock->shouldNotReceive('search');
        $this->qdrantMock->shouldNotReceive('upsert');

        $this->artisan('sync:odin', ['--pull' => true])
            ->assertSuccessful();
    });

    it('skips update when local wins conflict', function (): void {
        $localEntry = [
            'id' => 'local-1',
            'title' => 'Shared Entry',
            'content' => 'Local content (newer)',
            'updated_at' => '2025-06-01T12:00:00+00:00',
        ];

        $remoteEntry = [
            'title' => 'Shared Entry',
            'content' => 'Remote content (older)',
            'updated_at' => '2025-05-01T12:00:00+00:00',
        ];

        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->odinMock->shouldReceive('pullFromOdin')
            ->once()
            ->andReturn([$remoteEntry]);

        $this->qdrantMock->shouldReceive('search')
            ->once()
            ->andReturn(collect([$localEntry]));

        // Local wins
        $this->odinMock->shouldReceive('resolveConflict')
            ->once()
            ->andReturn($localEntry);

        // Should NOT upsert since local wins
        $this->qdrantMock->shouldNotReceive('upsert');

        $this->artisan('sync:odin', ['--pull' => true])
            ->assertSuccessful();
    });
});

describe('OdinSyncCommand pull with optional fields', function (): void {
    it('includes category and module from remote entry when set', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->odinMock->shouldReceive('pullFromOdin')
            ->once()
            ->with('default')
            ->andReturn([
                [
                    'title' => 'Entry With Category And Module',
                    'content' => 'Full metadata entry',
                    'updated_at' => '2025-06-01T12:00:00+00:00',
                    'category' => 'architecture',
                    'tags' => ['design'],
                    'module' => 'core-api',
                    'priority' => 'high',
                    'confidence' => 85,
                    'status' => 'validated',
                    'usage_count' => 3,
                ],
            ]);

        // No local match found
        $this->qdrantMock->shouldReceive('search')
            ->once()
            ->with('Entry With Category And Module', [], 1, 'default')
            ->andReturn(collect());

        // Verify upsert receives category and module
        $this->qdrantMock->shouldReceive('upsert')
            ->once()
            ->with(Mockery::on(fn ($data): bool => ($data['category'] ?? null) === 'architecture'
                && ($data['module'] ?? null) === 'core-api'), Mockery::any())
            ->andReturn(true);

        $this->artisan('sync:odin', ['--pull' => true])
            ->assertSuccessful();
    });

    it('omits category and module when not set in remote entry', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->odinMock->shouldReceive('pullFromOdin')
            ->once()
            ->with('default')
            ->andReturn([
                [
                    'title' => 'Entry Without Category Or Module',
                    'content' => 'Minimal entry',
                    'updated_at' => '2025-06-01T12:00:00+00:00',
                    'tags' => [],
                    'priority' => 'medium',
                    'confidence' => 50,
                    'status' => 'draft',
                    'usage_count' => 0,
                ],
            ]);

        $this->qdrantMock->shouldReceive('search')
            ->once()
            ->andReturn(collect());

        // Verify upsert does NOT include category or module keys
        $this->qdrantMock->shouldReceive('upsert')
            ->once()
            ->with(Mockery::on(fn ($data): bool => ! array_key_exists('category', $data)
                && ! array_key_exists('module', $data)), Mockery::any())
            ->andReturn(true);

        $this->artisan('sync:odin', ['--pull' => true])
            ->assertSuccessful();
    });
});

describe('OdinSyncCommand status display', function (): void {
    it('uses red color for error sync status', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('getStatus')->once()->andReturn([
            'status' => 'error',
            'pending' => 0,
            'last_synced' => null,
            'last_error' => 'Connection refused',
        ]);

        config(['services.odin.url' => 'http://odin.local']);

        $this->artisan('sync:odin', ['--status' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Odin Sync Status');
    });

    it('uses yellow color for pending sync status', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('getStatus')->once()->andReturn([
            'status' => 'pending',
            'pending' => 3,
            'last_synced' => null,
            'last_error' => null,
        ]);

        config(['services.odin.url' => 'http://odin.local']);

        $this->artisan('sync:odin', ['--status' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Odin Sync Status');
    });

    it('uses gray color for unknown sync status', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('getStatus')->once()->andReturn([
            'status' => 'unknown-status',
            'pending' => 0,
            'last_synced' => null,
            'last_error' => null,
        ]);

        config(['services.odin.url' => 'http://odin.local']);

        $this->artisan('sync:odin', ['--status' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Odin Sync Status');
    });
});

describe('OdinSyncCommand default two-way sync', function (): void {
    it('performs push then pull with no flags', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->odinMock->shouldReceive('processQueue')->once()->andReturn([
            'synced' => 2,
            'failed' => 0,
            'remaining' => 0,
        ]);
        $this->odinMock->shouldReceive('pullFromOdin')
            ->once()
            ->with('default')
            ->andReturn([]);

        $this->artisan('sync:odin')
            ->assertSuccessful()
            ->expectsOutputToContain('Odin Sync Summary');
    });

    it('accepts custom project option', function (): void {
        $this->odinMock->shouldReceive('isEnabled')->once()->andReturn(true);
        $this->odinMock->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->odinMock->shouldReceive('processQueue')->once()->andReturn([
            'synced' => 0,
            'failed' => 0,
            'remaining' => 0,
        ]);
        $this->odinMock->shouldReceive('pullFromOdin')
            ->once()
            ->with('custom-project')
            ->andReturn([]);

        $this->artisan('sync:odin', ['--project' => 'custom-project'])
            ->assertSuccessful();
    });
});
