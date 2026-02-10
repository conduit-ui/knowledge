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

    // Set up config for prefrontal API
    config(['services.prefrontal.token' => 'test-token']);
    config(['services.prefrontal.url' => 'http://test-api.local']);
});

describe('SyncCommand --delete option', function (): void {
    it('fails when --delete is used without --push or --full-sync', function (): void {
        $this->artisan('sync', ['--delete' => true])
            ->assertFailed()
            ->expectsOutput('The --delete option requires --push or --full-sync to be specified.');
    });

    it('deletes orphaned cloud entries when --delete is used with --push', function (): void {
        // Local entries
        $localEntries = collect([
            [
                'id' => 'local-1',
                'title' => 'Local Entry 1',
                'content' => 'Content 1',
                'category' => 'testing',
                'tags' => [],
                'module' => null,
                'priority' => 'medium',
                'status' => 'draft',
                'confidence' => 50,
            ],
        ]);

        // Cloud entries - one matches local, one is orphaned
        $cloudEntries = [
            'data' => [
                [
                    'id' => 1,
                    'unique_id' => hash('sha256', 'local-1-Local Entry 1'),
                    'title' => 'Local Entry 1',
                    'content' => 'Content 1',
                ],
                [
                    'id' => 2,
                    'unique_id' => hash('sha256', 'orphan-id-Orphaned Entry'),
                    'title' => 'Orphaned Entry',
                    'content' => 'This entry does not exist locally',
                ],
            ],
        ];

        // Mock Qdrant to return local entries
        $this->qdrantMock->shouldReceive('search')
            ->with('', [], 10000)
            ->andReturn($localEntries);

        // Mock tracker - no tracked deletions
        $this->trackerMock->shouldReceive('all')
            ->andReturn([]);

        // Create mock HTTP responses
        // processTrackedDeletions returns early when tracker is empty (no HTTP call)
        $mockHandler = new MockHandler([
            // Push response
            new Response(200, [], json_encode(['summary' => ['created' => 0, 'updated' => 1, 'failed' => 0]])),
            // Get cloud entries for delete comparison
            new Response(200, [], json_encode($cloudEntries)),
            // Delete orphaned entry response
            new Response(204),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync', ['--push' => true, '--delete' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Pushing local entries to cloud')
            ->expectsOutputToContain('Processing tracked deletions')
            ->expectsOutputToContain('1 orphaned cloud entries to delete');
    });

    it('processes tracked deletions when --delete is used with --push', function (): void {
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

        $trackedUniqueId = hash('sha256', 'deleted-1-Deleted Entry');

        $this->qdrantMock->shouldReceive('search')
            ->with('', [], 10000)
            ->andReturn($localEntries);

        $this->trackerMock->shouldReceive('all')
            ->andReturn([$trackedUniqueId => '2025-01-01T00:00:00+00:00']);

        $this->trackerMock->shouldReceive('removeMany')
            ->once()
            ->with([$trackedUniqueId]);

        $cloudEntries = [
            'data' => [
                [
                    'id' => 99,
                    'unique_id' => $trackedUniqueId,
                    'title' => 'Deleted Entry',
                ],
                [
                    'id' => 1,
                    'unique_id' => hash('sha256', 'local-1-Entry 1'),
                    'title' => 'Entry 1',
                ],
            ],
        ];

        $mockHandler = new MockHandler([
            // Push response
            new Response(200, [], json_encode(['summary' => ['created' => 0, 'updated' => 1, 'failed' => 0]])),
            // Get cloud entries for tracked deletions
            new Response(200, [], json_encode($cloudEntries)),
            // Delete tracked entry
            new Response(204),
            // Get cloud entries for orphan check (after tracked deletion removed)
            new Response(200, [], json_encode(['data' => [
                ['id' => 1, 'unique_id' => hash('sha256', 'local-1-Entry 1'), 'title' => 'Entry 1'],
            ]])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync', ['--push' => true, '--delete' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('1 tracked deletions to propagate');
    });

    it('handles case when no cloud entries exist', function (): void {
        // Local entries
        $localEntries = collect([
            [
                'id' => 'local-1',
                'title' => 'Local Entry 1',
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

        $this->trackerMock->shouldReceive('all')
            ->andReturn([]);

        // Cloud has no entries with unique_id/id
        $cloudEntries = [
            'data' => [],
        ];

        // processTrackedDeletions returns early when tracker is empty (no HTTP call)
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['summary' => ['created' => 1, 'updated' => 0, 'failed' => 0]])),
            new Response(200, [], json_encode($cloudEntries)),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync', ['--push' => true, '--delete' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No cloud entries to process');
    });

    it('handles case when all cloud entries exist locally', function (): void {
        // Local entries
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

        $this->trackerMock->shouldReceive('all')
            ->andReturn([]);

        // Cloud entry matches local
        $cloudEntries = [
            'data' => [
                [
                    'id' => 1,
                    'unique_id' => hash('sha256', 'local-1-Entry 1'),
                    'title' => 'Entry 1',
                    'content' => 'Content 1',
                ],
            ],
        ];

        // processTrackedDeletions returns early when tracker is empty (no HTTP call)
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['summary' => ['created' => 0, 'updated' => 1, 'failed' => 0]])),
            new Response(200, [], json_encode($cloudEntries)),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync', ['--push' => true, '--delete' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('No orphaned cloud entries to delete');
    });

    it('handles invalid cloud API response for delete', function (): void {
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

        $this->trackerMock->shouldReceive('all')
            ->andReturn([]);

        // processTrackedDeletions returns early when tracker is empty (no HTTP call)
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['summary' => ['created' => 0, 'updated' => 1, 'failed' => 0]])),
            new Response(200, [], json_encode(['invalid' => 'response'])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync', ['--push' => true, '--delete' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Invalid response from cloud API');
    });
});

describe('SyncCommand configuration validation', function (): void {
    it('fails when API token is not set', function (): void {
        config(['services.prefrontal.token' => '']);

        $this->artisan('sync')
            ->assertFailed()
            ->expectsOutput('PREFRONTAL_API_TOKEN environment variable is not set.');
    });

    it('fails when API URL is not set', function (): void {
        config(['services.prefrontal.url' => '']);

        $this->artisan('sync')
            ->assertFailed()
            ->expectsOutput('PREFRONTAL_API_URL environment variable is not set.');
    });
});

describe('SyncCommand --push option', function (): void {
    it('pushes local entries to cloud', function (): void {
        $localEntries = collect([
            [
                'id' => 'local-1',
                'title' => 'Entry 1',
                'content' => 'Content 1',
                'category' => 'testing',
                'tags' => ['tag1'],
                'module' => 'TestModule',
                'priority' => 'high',
                'status' => 'validated',
                'confidence' => 90,
            ],
        ]);

        $this->qdrantMock->shouldReceive('search')
            ->with('', [], 10000)
            ->andReturn($localEntries);

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['summary' => ['created' => 1, 'updated' => 0, 'failed' => 0]])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync', ['--push' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Pushing local entries to cloud')
            ->expectsOutputToContain('Push Summary');
    });

    it('handles empty local entries', function (): void {
        $this->qdrantMock->shouldReceive('search')
            ->with('', [], 10000)
            ->andReturn(collect());

        $this->artisan('sync', ['--push' => true])
            ->assertSuccessful()
            ->expectsOutput('No local entries to push.');
    });
});

describe('SyncCommand --pull option', function (): void {
    it('pulls entries from cloud', function (): void {
        $cloudEntries = [
            'data' => [
                [
                    'unique_id' => 'cloud-unique-1',
                    'title' => 'Cloud Entry 1',
                    'content' => 'Cloud content',
                    'category' => 'architecture',
                    'tags' => [],
                    'module' => null,
                    'priority' => 'medium',
                    'confidence' => 75,
                    'status' => 'draft',
                ],
            ],
        ];

        $this->qdrantMock->shouldReceive('search')
            ->with('Cloud Entry 1', [], 1)
            ->andReturn(collect());

        $this->qdrantMock->shouldReceive('upsert')
            ->once()
            ->andReturn(true);

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode($cloudEntries)),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync', ['--pull' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Pulling entries from cloud')
            ->expectsOutputToContain('Pull Summary');
    });

    it('handles invalid cloud response on pull', function (): void {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['invalid' => 'response'])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync', ['--pull' => true])
            ->assertSuccessful()
            ->expectsOutput('Invalid response from cloud API.');
    });

    it('skips entries without unique_id', function (): void {
        $cloudEntries = [
            'data' => [
                [
                    'title' => 'Entry without unique_id',
                    'content' => 'Content',
                ],
            ],
        ];

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode($cloudEntries)),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync', ['--pull' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Pull Summary');
    });
});

describe('SyncCommand two-way sync', function (): void {
    it('performs pull then push when no options specified', function (): void {
        // Cloud entries for pull
        $cloudEntries = [
            'data' => [
                [
                    'unique_id' => 'cloud-1',
                    'title' => 'Cloud Entry',
                    'content' => 'Content',
                    'category' => 'testing',
                    'tags' => [],
                    'module' => null,
                    'priority' => 'medium',
                    'confidence' => 50,
                    'status' => 'draft',
                ],
            ],
        ];

        // Local entries for push
        $localEntries = collect([
            [
                'id' => 'local-1',
                'title' => 'Local Entry',
                'content' => 'Content',
                'category' => 'testing',
                'tags' => [],
                'module' => null,
                'priority' => 'medium',
                'status' => 'draft',
                'confidence' => 50,
            ],
        ]);

        $this->qdrantMock->shouldReceive('search')
            ->with('Cloud Entry', [], 1)
            ->andReturn(collect());

        $this->qdrantMock->shouldReceive('upsert')
            ->once()
            ->andReturn(true);

        $this->qdrantMock->shouldReceive('search')
            ->with('', [], 10000)
            ->andReturn($localEntries);

        $mockHandler = new MockHandler([
            // Pull response
            new Response(200, [], json_encode($cloudEntries)),
            // Push response
            new Response(200, [], json_encode(['summary' => ['created' => 1, 'updated' => 0, 'failed' => 0]])),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync')
            ->assertSuccessful()
            ->expectsOutputToContain('Starting two-way sync')
            ->expectsOutputToContain('Sync Summary');
    });
});

describe('SyncCommand --full-sync option', function (): void {
    it('performs push then deletes orphans and tracked deletions', function (): void {
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

        // No tracked deletions
        $this->trackerMock->shouldReceive('all')
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
                    'unique_id' => hash('sha256', 'orphan-Orphan'),
                    'title' => 'Orphan',
                ],
            ],
        ];

        $mockHandler = new MockHandler([
            // Push response
            new Response(200, [], json_encode(['summary' => ['created' => 0, 'updated' => 1, 'failed' => 0]])),
            // Get cloud entries for tracked deletions (empty tracker -> fetch cloud)
            new Response(200, [], json_encode(['data' => []])),
            // Get cloud entries for orphan check
            new Response(200, [], json_encode($cloudEntries)),
            // Delete orphan
            new Response(204),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync', ['--full-sync' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Starting full sync')
            ->expectsOutputToContain('Pushing local entries to cloud')
            ->expectsOutputToContain('Comparing local vs cloud to find orphans');
    });

    it('handles tracked deletions not found in cloud', function (): void {
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

        // Tracked deletion for an entry not in cloud
        $trackedUniqueId = hash('sha256', 'gone-1-Gone Entry');
        $this->trackerMock->shouldReceive('all')
            ->andReturn([$trackedUniqueId => '2025-01-01T00:00:00+00:00']);

        $this->trackerMock->shouldReceive('removeMany')
            ->once()
            ->with([$trackedUniqueId]);

        $cloudEntriesForTracker = [
            'data' => [
                [
                    'id' => 1,
                    'unique_id' => hash('sha256', 'local-1-Entry 1'),
                    'title' => 'Entry 1',
                ],
            ],
        ];

        $cloudEntriesForOrphans = [
            'data' => [
                [
                    'id' => 1,
                    'unique_id' => hash('sha256', 'local-1-Entry 1'),
                    'title' => 'Entry 1',
                ],
            ],
        ];

        $mockHandler = new MockHandler([
            // Push response
            new Response(200, [], json_encode(['summary' => ['created' => 0, 'updated' => 1, 'failed' => 0]])),
            // Get cloud entries for tracked deletions
            new Response(200, [], json_encode($cloudEntriesForTracker)),
            // Get cloud entries for orphan check
            new Response(200, [], json_encode($cloudEntriesForOrphans)),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync', ['--full-sync' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('1 tracked deletions to propagate')
            ->expectsOutputToContain('No orphaned cloud entries to delete');
    });

    it('handles invalid API response during tracked deletion processing', function (): void {
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

        $trackedUniqueId = hash('sha256', 'deleted-1-Deleted Entry');
        $this->trackerMock->shouldReceive('all')
            ->andReturn([$trackedUniqueId => '2025-01-01T00:00:00+00:00']);

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
            // Push response
            new Response(200, [], json_encode(['summary' => ['created' => 0, 'updated' => 1, 'failed' => 0]])),
            // Invalid response for tracked deletions
            new Response(200, [], json_encode(['invalid' => 'response'])),
            // Get cloud entries for orphan check
            new Response(200, [], json_encode($cloudEntries)),
        ]);

        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->app->instance(Client::class, $mockClient);

        $this->artisan('sync', ['--full-sync' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Invalid response from cloud API');
    });
});
