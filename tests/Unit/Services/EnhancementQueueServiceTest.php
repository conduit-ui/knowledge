<?php

declare(strict_types=1);

use App\Services\EnhancementQueueService;
use App\Services\KnowledgePathService;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/enhance_queue_test_'.uniqid();
    mkdir($this->tempDir, 0755, true);

    $this->pathService = Mockery::mock(KnowledgePathService::class);
    $this->pathService->shouldReceive('getKnowledgeDirectory')
        ->andReturn($this->tempDir);
});

afterEach(function (): void {
    removeDirectory($this->tempDir);
});

describe('EnhancementQueueService queue operations', function (): void {
    it('queues an entry for enhancement', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        $service->queue([
            'id' => 'test-1',
            'title' => 'Test Entry',
            'content' => 'Test content',
        ]);

        expect($service->pendingCount())->toBe(1);
    });

    it('queues multiple entries', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        $service->queue(['id' => '1', 'title' => 'First', 'content' => 'Content 1']);
        $service->queue(['id' => '2', 'title' => 'Second', 'content' => 'Content 2']);
        $service->queue(['id' => '3', 'title' => 'Third', 'content' => 'Content 3']);

        expect($service->pendingCount())->toBe(3);
    });

    it('dequeues entries in FIFO order', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        $service->queue(['id' => '1', 'title' => 'First', 'content' => 'Content 1']);
        $service->queue(['id' => '2', 'title' => 'Second', 'content' => 'Content 2']);

        $item = $service->dequeue();

        expect($item)->not->toBeNull();
        expect($item['entry_id'])->toBe('1');
        expect($item['title'])->toBe('First');
        expect($service->pendingCount())->toBe(1);
    });

    it('returns null when dequeuing empty queue', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        expect($service->dequeue())->toBeNull();
    });

    it('checks if entry is queued', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        $service->queue(['id' => 'test-1', 'title' => 'Test', 'content' => 'Content']);

        expect($service->isQueued('test-1'))->toBeTrue();
        expect($service->isQueued('test-2'))->toBeFalse();
    });

    it('clears the queue', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        $service->queue(['id' => '1', 'title' => 'Test', 'content' => 'Content']);
        expect($service->pendingCount())->toBe(1);

        $service->clear();
        expect($service->pendingCount())->toBe(0);
    });

    it('handles empty queue file gracefully', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        expect($service->pendingCount())->toBe(0);
    });

    it('handles corrupted queue file gracefully', function (): void {
        file_put_contents($this->tempDir.'/enhance_queue.json', 'not-valid-json');
        $service = new EnhancementQueueService($this->pathService);

        expect($service->pendingCount())->toBe(0);
    });

    it('stores project with queued item', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        $service->queue(['id' => '1', 'title' => 'Test', 'content' => 'Content'], 'myproject');

        $item = $service->dequeue();

        expect($item['project'])->toBe('myproject');
    });

    it('stores queued_at timestamp', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        $service->queue(['id' => '1', 'title' => 'Test', 'content' => 'Content']);

        $item = $service->dequeue();

        expect($item['queued_at'])->not->toBeNull();
    });
});

describe('EnhancementQueueService status', function (): void {
    it('returns default status when no status file exists', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        $status = $service->getStatus();

        expect($status['status'])->toBe('idle');
        expect($status['pending'])->toBe(0);
        expect($status['processed'])->toBe(0);
        expect($status['failed'])->toBe(0);
        expect($status['last_processed'])->toBeNull();
        expect($status['last_error'])->toBeNull();
    });

    it('returns pending status when queue has items', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        $service->queue(['id' => '1', 'title' => 'Test', 'content' => 'Content']);
        $status = $service->getStatus();

        expect($status['status'])->toBe('pending');
        expect($status['pending'])->toBe(1);
    });

    it('handles corrupted status file gracefully', function (): void {
        file_put_contents($this->tempDir.'/enhance_status.json', 'not-valid-json');
        $service = new EnhancementQueueService($this->pathService);

        $status = $service->getStatus();

        expect($status['status'])->toBe('idle');
        expect($status['pending'])->toBe(0);
    });

    it('records successful processing', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        $service->recordSuccess();
        $status = $service->getStatus();

        expect($status['processed'])->toBe(1);
        expect($status['last_processed'])->not->toBeNull();
    });

    it('records failed processing', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        $service->recordFailure('Test error');
        $status = $service->getStatus();

        expect($status['failed'])->toBe(1);
        expect($status['last_error'])->toBe('Test error');
        expect($status['status'])->toBe('error');
    });

    it('increments counters on multiple operations', function (): void {
        $service = new EnhancementQueueService($this->pathService);

        $service->recordSuccess();
        $service->recordSuccess();
        $service->recordFailure('Error 1');

        $status = $service->getStatus();

        expect($status['processed'])->toBe(2);
        expect($status['failed'])->toBe(1);
    });
});
