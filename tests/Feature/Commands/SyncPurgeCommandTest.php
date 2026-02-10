<?php

declare(strict_types=1);

use App\Services\DeletionTracker;
use App\Services\QdrantService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

beforeEach(function (): void {
    $this->qdrantMock = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->qdrantMock);

    $this->trackerMock = Mockery::mock(DeletionTracker::class);
    $this->app->instance(DeletionTracker::class, $this->trackerMock);

    config(['services.prefrontal.token' => 'test-token']);
    config(['services.prefrontal.url' => 'http://test-api.local']);
});

describe('SyncPurgeCommand configuration validation', function (): void {
    it('fails when API token is not set', function (): void {
        config(['services.prefrontal.token' => '']);

        $this->artisan('sync:purge')
            ->assertFailed()
            ->expectsOutput('PREFRONTAL_API_TOKEN environment variable is not set.');
    });

    it('fails when API URL is not set', function (): void {
        config(['services.prefrontal.url' => '']);

        $this->artisan('sync:purge')
            ->assertFailed()
            ->expectsOutput('PREFRONTAL_API_URL environment variable is not set.');
    });
});

describe('SyncPurgeCommand --tracked-only', function (): void {
    it('reports no tracked deletions when tracker is empty', function (): void {
        $this->trackerMock->shouldReceive('all')
            ->andReturn([]);

        $this->artisan('sync:purge', ['--tracked-only' => true])
            ->assertSuccessful()
            ->expectsOutput('No tracked deletions to purge.');
    });

    it('shows dry run info for tracked deletions', function (): void {
        $this->trackerMock->shouldReceive('all')
            ->andReturn(['uid-1' => '2025-01-01T00:00:00+00:00']);

        $this->artisan('sync:purge', ['--tracked-only' => true, '--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('[DRY RUN] Would purge 1 tracked entries from cloud');
    });

    it('purges tracked deletions from cloud', function (): void {
        $trackedUniqueId = hash('sha256', 'deleted-1-Deleted Entry');

        $this->trackerMock->shouldReceive('all')
            ->andReturn([$trackedUniqueId => '2025-01-01T00:00:00+00:00']);

        $this->trackerMock->shouldReceive('removeMany')
            ->once()
            ->with([$trackedUniqueId]);

        $cloudEntries = [
            'data' => [
                [
                    'id' => 42,
                    'unique_id' => $trackedUniqueId,
                    'title' => 'Deleted Entry',
                ],
            ],
        ];

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode($cloudEntries)),
            new Response(204),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync:purge', ['--tracked-only' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Purged 1 entries from cloud');
    });

    it('removes tracked entries not found in cloud', function (): void {
        $trackedUniqueId = hash('sha256', 'gone-1-Gone Entry');

        $this->trackerMock->shouldReceive('all')
            ->andReturn([$trackedUniqueId => '2025-01-01T00:00:00+00:00']);

        $this->trackerMock->shouldReceive('removeMany')
            ->once()
            ->with([$trackedUniqueId]);

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['data' => []])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync:purge', ['--tracked-only' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Purged 0 entries from cloud');
    });

    it('handles invalid API response for tracked-only purge', function (): void {
        $this->trackerMock->shouldReceive('all')
            ->andReturn(['uid-1' => '2025-01-01T00:00:00+00:00']);

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['invalid' => 'response'])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync:purge', ['--tracked-only' => true])
            ->assertFailed()
            ->expectsOutput('Invalid response from cloud API.');
    });
});

describe('SyncPurgeCommand orphan purge', function (): void {
    it('reports no orphaned entries when everything is in sync', function (): void {
        $localEntries = collect([
            [
                'id' => 'local-1',
                'title' => 'Entry 1',
                'content' => 'Content 1',
                'category' => 'testing',
                'tags' => [],
                'module' => null,
                'priority' => 'medium',
                'status' => 'draft',
                'confidence' => 50,
            ],
        ]);

        $this->qdrantMock->shouldReceive('search')
            ->with('', [], 10000)
            ->andReturn($localEntries);

        $this->trackerMock->shouldReceive('getDeletedIds')
            ->andReturn([]);

        $cloudEntries = [
            'data' => [
                [
                    'id' => 1,
                    'unique_id' => hash('sha256', 'local-1-Entry 1'),
                    'title' => 'Entry 1',
                ],
            ],
        ];

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode($cloudEntries)),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync:purge')
            ->assertSuccessful()
            ->expectsOutput('No orphaned cloud entries found. Everything is in sync.');
    });

    it('shows dry run info for orphaned entries', function (): void {
        $localEntries = collect([
            [
                'id' => 'local-1',
                'title' => 'Entry 1',
                'content' => 'Content 1',
                'category' => 'testing',
                'tags' => [],
                'module' => null,
                'priority' => 'medium',
                'status' => 'draft',
                'confidence' => 50,
            ],
        ]);

        $this->qdrantMock->shouldReceive('search')
            ->with('', [], 10000)
            ->andReturn($localEntries);

        $this->trackerMock->shouldReceive('getDeletedIds')
            ->andReturn([]);

        $cloudEntries = [
            'data' => [
                [
                    'id' => 1,
                    'unique_id' => hash('sha256', 'local-1-Entry 1'),
                    'title' => 'Entry 1',
                ],
                [
                    'id' => 2,
                    'unique_id' => hash('sha256', 'orphan-Orphan Entry'),
                    'title' => 'Orphan Entry',
                ],
            ],
        ];

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode($cloudEntries)),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync:purge', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('[DRY RUN] Would delete the following cloud entries');
    });

    it('purges orphaned cloud entries', function (): void {
        $localEntries = collect([
            [
                'id' => 'local-1',
                'title' => 'Entry 1',
                'content' => 'Content 1',
                'category' => 'testing',
                'tags' => [],
                'module' => null,
                'priority' => 'medium',
                'status' => 'draft',
                'confidence' => 50,
            ],
        ]);

        $orphanUniqueId = hash('sha256', 'orphan-Orphan Entry');

        $this->qdrantMock->shouldReceive('search')
            ->with('', [], 10000)
            ->andReturn($localEntries);

        $this->trackerMock->shouldReceive('getDeletedIds')
            ->andReturn([]);

        $this->trackerMock->shouldReceive('removeMany')
            ->once()
            ->with([$orphanUniqueId]);

        $cloudEntries = [
            'data' => [
                [
                    'id' => 1,
                    'unique_id' => hash('sha256', 'local-1-Entry 1'),
                    'title' => 'Entry 1',
                ],
                [
                    'id' => 2,
                    'unique_id' => $orphanUniqueId,
                    'title' => 'Orphan Entry',
                ],
            ],
        ];

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode($cloudEntries)),
            new Response(204),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync:purge')
            ->assertSuccessful()
            ->expectsOutputToContain('Purged 1 orphaned entries from cloud');
    });

    it('handles no cloud entries found', function (): void {
        $this->qdrantMock->shouldReceive('search')
            ->with('', [], 10000)
            ->andReturn(collect());

        $this->trackerMock->shouldReceive('getDeletedIds')
            ->andReturn([]);

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['data' => []])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync:purge')
            ->assertSuccessful()
            ->expectsOutput('No cloud entries found.');
    });

    it('handles invalid API response for orphan purge', function (): void {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['invalid' => 'response'])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync:purge')
            ->assertFailed()
            ->expectsOutput('Invalid response from cloud API.');
    });

    it('includes tracked deletions in orphan purge', function (): void {
        $localEntries = collect([
            [
                'id' => 'local-1',
                'title' => 'Entry 1',
                'content' => 'Content 1',
                'category' => 'testing',
                'tags' => [],
                'module' => null,
                'priority' => 'medium',
                'status' => 'draft',
                'confidence' => 50,
            ],
        ]);

        $trackedUniqueId = hash('sha256', 'local-1-Entry 1');

        $this->qdrantMock->shouldReceive('search')
            ->with('', [], 10000)
            ->andReturn($localEntries);

        // This entry exists locally but is tracked for deletion
        $this->trackerMock->shouldReceive('getDeletedIds')
            ->andReturn([$trackedUniqueId]);

        $this->trackerMock->shouldReceive('removeMany')
            ->once()
            ->with([$trackedUniqueId]);

        $cloudEntries = [
            'data' => [
                [
                    'id' => 1,
                    'unique_id' => $trackedUniqueId,
                    'title' => 'Entry 1',
                ],
            ],
        ];

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode($cloudEntries)),
            new Response(204),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync:purge')
            ->assertSuccessful()
            ->expectsOutputToContain('Purged 1 orphaned entries from cloud');
    });
});
