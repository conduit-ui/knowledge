<?php

declare(strict_types=1);

use App\Services\KnowledgePathService;
use App\Services\OdinSyncService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/odin_sync_test_'.uniqid();
    mkdir($this->tempDir, 0755, true);

    $this->pathService = Mockery::mock(KnowledgePathService::class);
    $this->pathService->shouldReceive('getKnowledgeDirectory')
        ->andReturn($this->tempDir);

    config(['services.odin.enabled' => true]);
    config(['services.odin.url' => 'http://test-odin.local']);
    config(['services.odin.token' => 'test-token']);
    config(['services.odin.timeout' => 5]);
    config(['services.odin.batch_size' => 50]);
});

afterEach(function (): void {
    removeDirectory($this->tempDir);
});

describe('OdinSyncService configuration', function (): void {
    it('reports enabled when config is true', function (): void {
        $service = new OdinSyncService($this->pathService);

        expect($service->isEnabled())->toBeTrue();
    });

    it('reports disabled when config is false', function (): void {
        config(['services.odin.enabled' => false]);
        $service = new OdinSyncService($this->pathService);

        expect($service->isEnabled())->toBeFalse();
    });
});

describe('OdinSyncService queue operations', function (): void {
    it('queues an entry for sync', function (): void {
        $service = new OdinSyncService($this->pathService);

        $entry = [
            'id' => 'test-1',
            'title' => 'Test Entry',
            'content' => 'Test content',
        ];

        $service->queueForSync($entry, 'upsert', 'myproject');

        expect($service->getPendingCount())->toBe(1);
    });

    it('does not queue when disabled', function (): void {
        config(['services.odin.enabled' => false]);
        $service = new OdinSyncService($this->pathService);

        $service->queueForSync(['id' => 'test-1', 'title' => 'Test', 'content' => 'Content']);

        expect($service->getPendingCount())->toBe(0);
    });

    it('queues multiple entries', function (): void {
        $service = new OdinSyncService($this->pathService);

        $service->queueForSync(['id' => '1', 'title' => 'First', 'content' => 'Content 1']);
        $service->queueForSync(['id' => '2', 'title' => 'Second', 'content' => 'Content 2']);
        $service->queueForSync(['id' => '3', 'title' => 'Third', 'content' => 'Content 3'], 'delete');

        expect($service->getPendingCount())->toBe(3);
    });

    it('clears the queue', function (): void {
        $service = new OdinSyncService($this->pathService);

        $service->queueForSync(['id' => '1', 'title' => 'Test', 'content' => 'Content']);
        expect($service->getPendingCount())->toBe(1);

        $service->clearQueue();
        expect($service->getPendingCount())->toBe(0);
    });

    it('handles empty queue file gracefully', function (): void {
        $service = new OdinSyncService($this->pathService);

        expect($service->getPendingCount())->toBe(0);
    });

    it('handles corrupted queue file gracefully', function (): void {
        file_put_contents($this->tempDir.'/sync_queue.json', 'not-valid-json');
        $service = new OdinSyncService($this->pathService);

        expect($service->getPendingCount())->toBe(0);
    });
});

describe('OdinSyncService processQueue', function (): void {
    it('processes empty queue', function (): void {
        $service = new OdinSyncService($this->pathService);

        $result = $service->processQueue();

        expect($result)->toBe(['synced' => 0, 'failed' => 0, 'remaining' => 0]);
    });

    it('returns remaining when no token is set', function (): void {
        config(['services.odin.token' => '']);
        $service = new OdinSyncService($this->pathService);

        $service->queueForSync(['id' => '1', 'title' => 'Test', 'content' => 'Content']);
        $result = $service->processQueue();

        expect($result['remaining'])->toBe(1);
        expect($result['synced'])->toBe(0);
    });

    it('processes upsert queue items successfully', function (): void {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['summary' => ['created' => 1, 'updated' => 0, 'failed' => 0]])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new OdinSyncService($this->pathService);

        $service->queueForSync(['id' => '1', 'title' => 'Test Entry', 'content' => 'Content']);
        $result = $service->processQueue();

        expect($result['synced'])->toBe(1);
        expect($result['failed'])->toBe(0);
        expect($result['remaining'])->toBe(0);

        app()->forgetInstance(Client::class);
    });

    it('handles push failure and retains items in queue', function (): void {
        $mockHandler = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new OdinSyncService($this->pathService);

        $service->queueForSync(['id' => '1', 'title' => 'Test Entry', 'content' => 'Content']);
        $result = $service->processQueue();

        expect($result['synced'])->toBe(0);
        expect($result['failed'])->toBe(1);
        expect($result['remaining'])->toBe(1);

        app()->forgetInstance(Client::class);
    });

    it('processes delete queue items successfully', function (): void {
        $mockHandler = new MockHandler([
            new Response(204),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new OdinSyncService($this->pathService);

        $service->queueForSync(['id' => '1', 'title' => 'Test Entry', 'content' => 'Content'], 'delete');
        $result = $service->processQueue();

        expect($result['synced'])->toBe(1);
        expect($result['failed'])->toBe(0);
        expect($result['remaining'])->toBe(0);

        app()->forgetInstance(Client::class);
    });

    it('handles delete failure', function (): void {
        $mockHandler = new MockHandler([
            new Response(500, [], 'Server Error'),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new OdinSyncService($this->pathService);

        $service->queueForSync(['id' => '1', 'title' => 'Test', 'content' => 'Content'], 'delete');
        $result = $service->processQueue();

        expect($result['synced'])->toBe(0);
        expect($result['failed'])->toBe(1);
        expect($result['remaining'])->toBe(1);

        app()->forgetInstance(Client::class);
    });
});

describe('OdinSyncService conflict resolution', function (): void {
    it('picks local when local is newer', function (): void {
        $service = new OdinSyncService($this->pathService);

        $local = ['title' => 'Local', 'updated_at' => '2025-06-01T12:00:00+00:00'];
        $remote = ['title' => 'Remote', 'updated_at' => '2025-05-01T12:00:00+00:00'];

        $winner = $service->resolveConflict($local, $remote);

        expect($winner)->toBe($local);
    });

    it('picks remote when remote is newer', function (): void {
        $service = new OdinSyncService($this->pathService);

        $local = ['title' => 'Local', 'updated_at' => '2025-05-01T12:00:00+00:00'];
        $remote = ['title' => 'Remote', 'updated_at' => '2025-06-01T12:00:00+00:00'];

        $winner = $service->resolveConflict($local, $remote);

        expect($winner)->toBe($remote);
    });

    it('picks local when timestamps are equal', function (): void {
        $service = new OdinSyncService($this->pathService);

        $local = ['title' => 'Local', 'updated_at' => '2025-06-01T12:00:00+00:00'];
        $remote = ['title' => 'Remote', 'updated_at' => '2025-06-01T12:00:00+00:00'];

        $winner = $service->resolveConflict($local, $remote);

        expect($winner)->toBe($local);
    });

    it('picks local when both timestamps are empty', function (): void {
        $service = new OdinSyncService($this->pathService);

        $local = ['title' => 'Local'];
        $remote = ['title' => 'Remote'];

        $winner = $service->resolveConflict($local, $remote);

        expect($winner)->toBe($local);
    });

    it('picks remote when local timestamp is empty', function (): void {
        $service = new OdinSyncService($this->pathService);

        $local = ['title' => 'Local'];
        $remote = ['title' => 'Remote', 'updated_at' => '2025-06-01T12:00:00+00:00'];

        $winner = $service->resolveConflict($local, $remote);

        expect($winner)->toBe($remote);
    });

    it('picks local when remote timestamp is empty', function (): void {
        $service = new OdinSyncService($this->pathService);

        $local = ['title' => 'Local', 'updated_at' => '2025-06-01T12:00:00+00:00'];
        $remote = ['title' => 'Remote'];

        $winner = $service->resolveConflict($local, $remote);

        expect($winner)->toBe($local);
    });
});

describe('OdinSyncService status', function (): void {
    it('returns default status when no status file exists', function (): void {
        $service = new OdinSyncService($this->pathService);

        $status = $service->getStatus();

        expect($status['status'])->toBe('idle');
        expect($status['pending'])->toBe(0);
        expect($status['last_synced'])->toBeNull();
        expect($status['last_error'])->toBeNull();
    });

    it('returns pending status when queue has items', function (): void {
        $service = new OdinSyncService($this->pathService);

        $service->queueForSync(['id' => '1', 'title' => 'Test', 'content' => 'Content']);
        $status = $service->getStatus();

        expect($status['status'])->toBe('pending');
        expect($status['pending'])->toBe(1);
    });

    it('returns pending when status file says idle but queue has items', function (): void {
        // Write a status file with 'idle' status
        file_put_contents($this->tempDir.'/sync_status.json', json_encode([
            'status' => 'idle',
            'pending' => 0,
            'last_synced' => '2025-06-01T12:00:00+00:00',
            'last_error' => null,
        ]));

        $service = new OdinSyncService($this->pathService);

        // Add items to queue - this makes the queue non-empty
        $service->queueForSync(['id' => '1', 'title' => 'Test', 'content' => 'Content']);

        $status = $service->getStatus();

        // Status should be upgraded from 'idle' to 'pending' because queue has items
        expect($status['status'])->toBe('pending');
        expect($status['pending'])->toBe(1);
    });

    it('handles corrupted status file gracefully', function (): void {
        file_put_contents($this->tempDir.'/sync_status.json', 'not-valid-json');
        $service = new OdinSyncService($this->pathService);

        $status = $service->getStatus();

        expect($status['status'])->toBe('idle');
    });

    it('updates status after successful sync', function (): void {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['summary' => ['created' => 1]])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new OdinSyncService($this->pathService);
        $service->queueForSync(['id' => '1', 'title' => 'Test', 'content' => 'Content']);
        $service->processQueue();

        $status = $service->getStatus();
        expect($status['status'])->toBe('synced');
        expect($status['last_synced'])->not->toBeNull();

        app()->forgetInstance(Client::class);
    });
});

describe('OdinSyncService connectivity', function (): void {
    it('reports unavailable when URL is empty', function (): void {
        config(['services.odin.url' => '']);
        $service = new OdinSyncService($this->pathService);

        expect($service->isAvailable())->toBeFalse();
    });

    it('reports unavailable when token is empty', function (): void {
        config(['services.odin.token' => '']);
        $service = new OdinSyncService($this->pathService);

        expect($service->isAvailable())->toBeFalse();
    });

    it('reports available on successful health check', function (): void {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['data' => []])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new OdinSyncService($this->pathService);

        expect($service->isAvailable())->toBeTrue();

        app()->forgetInstance(Client::class);
    });

    it('reports unavailable on failed health check', function (): void {
        $mockHandler = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new OdinSyncService($this->pathService);

        expect($service->isAvailable())->toBeFalse();

        app()->forgetInstance(Client::class);
    });
});

describe('OdinSyncService pull', function (): void {
    it('returns empty when token is missing', function (): void {
        config(['services.odin.token' => '']);
        $service = new OdinSyncService($this->pathService);

        expect($service->pullFromOdin())->toBe([]);
    });

    it('pulls entries from Odin', function (): void {
        $entries = [
            'data' => [
                ['title' => 'Entry 1', 'content' => 'Content 1'],
                ['title' => 'Entry 2', 'content' => 'Content 2'],
            ],
        ];

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode($entries)),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new OdinSyncService($this->pathService);
        $result = $service->pullFromOdin('myproject');

        expect($result)->toHaveCount(2);
        expect($result[0]['title'])->toBe('Entry 1');

        app()->forgetInstance(Client::class);
    });

    it('returns empty on invalid response', function (): void {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['invalid' => 'response'])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new OdinSyncService($this->pathService);
        $result = $service->pullFromOdin();

        expect($result)->toBe([]);

        app()->forgetInstance(Client::class);
    });

    it('returns empty on HTTP error', function (): void {
        $mockHandler = new MockHandler([
            new Response(500, [], 'Error'),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new OdinSyncService($this->pathService);
        $result = $service->pullFromOdin();

        expect($result)->toBe([]);

        app()->forgetInstance(Client::class);
    });
});

describe('OdinSyncService listProjects', function (): void {
    it('returns empty when token is missing', function (): void {
        config(['services.odin.token' => '']);
        $service = new OdinSyncService($this->pathService);

        expect($service->listProjects())->toBe([]);
    });

    it('returns projects list from Odin', function (): void {
        $projects = [
            'data' => [
                ['name' => 'project-a', 'entry_count' => 10, 'last_synced' => '2025-06-01'],
                ['name' => 'project-b', 'entry_count' => 5, 'last_synced' => null],
            ],
        ];

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode($projects)),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new OdinSyncService($this->pathService);
        $result = $service->listProjects();

        expect($result)->toHaveCount(2);
        expect($result[0]['name'])->toBe('project-a');

        app()->forgetInstance(Client::class);
    });

    it('returns empty on server error', function (): void {
        $mockHandler = new MockHandler([
            new Response(500, [], 'Error'),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new OdinSyncService($this->pathService);
        $result = $service->listProjects();

        expect($result)->toBe([]);

        app()->forgetInstance(Client::class);
    });

    it('returns empty on invalid response', function (): void {
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['invalid' => 'data'])),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);
        app()->instance(Client::class, $mockClient);

        $service = new OdinSyncService($this->pathService);
        $result = $service->listProjects();

        expect($result)->toBe([]);

        app()->forgetInstance(Client::class);
    });
});
