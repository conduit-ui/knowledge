<?php

declare(strict_types=1);

use App\Services\EnhancementQueueService;
use App\Services\OllamaService;
use App\Services\QdrantService;

beforeEach(function (): void {
    $this->ollamaService = mock(OllamaService::class);
    $this->qdrantService = mock(QdrantService::class);
    $this->queueService = mock(EnhancementQueueService::class);

    app()->instance(OllamaService::class, $this->ollamaService);
    app()->instance(QdrantService::class, $this->qdrantService);
    app()->instance(EnhancementQueueService::class, $this->queueService);
});

describe('enhance:worker command', function (): void {
    it('shows status when --status flag is used', function (): void {
        $this->queueService->shouldReceive('getStatus')
            ->once()
            ->andReturn([
                'status' => 'idle',
                'pending' => 0,
                'processed' => 5,
                'failed' => 1,
                'last_processed' => '2025-06-01T12:00:00+00:00',
                'last_error' => null,
            ]);

        $this->artisan('enhance:worker', ['--status' => true])
            ->assertSuccessful();
    });

    it('exits gracefully when Ollama is unavailable', function (): void {
        $this->ollamaService->shouldReceive('isAvailable')->once()->andReturn(false);

        $this->artisan('enhance:worker')
            ->assertSuccessful();
    });

    it('exits successfully when queue is empty', function (): void {
        $this->ollamaService->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->queueService->shouldReceive('pendingCount')->once()->andReturn(0);

        $this->artisan('enhance:worker')
            ->assertSuccessful();
    });

    it('processes one item with --once flag', function (): void {
        $this->ollamaService->shouldReceive('isAvailable')->once()->andReturn(true);

        $this->queueService->shouldReceive('dequeue')
            ->once()
            ->andReturn([
                'entry_id' => 'test-id',
                'title' => 'Test Entry',
                'content' => 'Test content',
                'category' => null,
                'tags' => [],
                'project' => 'default',
                'queued_at' => '2025-06-01T12:00:00+00:00',
            ]);

        $this->ollamaService->shouldReceive('enhance')
            ->once()
            ->andReturn([
                'tags' => ['php', 'testing'],
                'category' => 'testing',
                'concepts' => ['unit testing'],
                'summary' => 'A test entry.',
            ]);

        $this->qdrantService->shouldReceive('updateFields')
            ->once()
            ->with('test-id', Mockery::on(fn ($fields): bool => $fields['enhanced'] === true
                && isset($fields['enhanced_at'])
                && $fields['tags'] === ['php', 'testing']
                && $fields['category'] === 'testing'
                && $fields['concepts'] === ['unit testing']
                && $fields['summary'] === 'A test entry.'), 'default')
            ->andReturn(true);

        $this->queueService->shouldReceive('recordSuccess')->once();

        $this->artisan('enhance:worker', ['--once' => true])
            ->assertSuccessful();
    });

    it('handles empty queue with --once flag', function (): void {
        $this->ollamaService->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->queueService->shouldReceive('dequeue')->once()->andReturn(null);

        $this->artisan('enhance:worker', ['--once' => true])
            ->assertSuccessful();
    });

    it('records failure on empty enhancement response', function (): void {
        $this->ollamaService->shouldReceive('isAvailable')->once()->andReturn(true);

        $this->queueService->shouldReceive('dequeue')
            ->once()
            ->andReturn([
                'entry_id' => 'test-id',
                'title' => 'Test Entry',
                'content' => 'Test content',
                'category' => null,
                'tags' => [],
                'project' => 'default',
                'queued_at' => '2025-06-01T12:00:00+00:00',
            ]);

        $this->ollamaService->shouldReceive('enhance')
            ->once()
            ->andReturn([
                'tags' => [],
                'category' => null,
                'concepts' => [],
                'summary' => '',
            ]);

        $this->queueService->shouldReceive('recordFailure')->once();

        $this->artisan('enhance:worker', ['--once' => true])
            ->assertFailed();
    });

    it('records failure on Qdrant update failure', function (): void {
        $this->ollamaService->shouldReceive('isAvailable')->once()->andReturn(true);

        $this->queueService->shouldReceive('dequeue')
            ->once()
            ->andReturn([
                'entry_id' => 'test-id',
                'title' => 'Test Entry',
                'content' => 'Test content',
                'category' => null,
                'tags' => [],
                'project' => 'default',
                'queued_at' => '2025-06-01T12:00:00+00:00',
            ]);

        $this->ollamaService->shouldReceive('enhance')
            ->once()
            ->andReturn([
                'tags' => ['php'],
                'category' => 'testing',
                'concepts' => ['concept'],
                'summary' => 'A summary.',
            ]);

        $this->qdrantService->shouldReceive('updateFields')
            ->once()
            ->andReturn(false);

        $this->queueService->shouldReceive('recordFailure')->once();

        $this->artisan('enhance:worker', ['--once' => true])
            ->assertFailed();
    });

    it('processes all items in queue', function (): void {
        $this->ollamaService->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->queueService->shouldReceive('pendingCount')->once()->andReturn(2);

        $item1 = [
            'entry_id' => 'id-1',
            'title' => 'Entry 1',
            'content' => 'Content 1',
            'category' => null,
            'tags' => ['existing'],
            'project' => 'default',
            'queued_at' => '2025-06-01T12:00:00+00:00',
        ];

        $item2 = [
            'entry_id' => 'id-2',
            'title' => 'Entry 2',
            'content' => 'Content 2',
            'category' => 'debugging',
            'tags' => [],
            'project' => 'default',
            'queued_at' => '2025-06-01T12:00:00+00:00',
        ];

        $this->queueService->shouldReceive('dequeue')
            ->times(3)
            ->andReturn($item1, $item2, null);

        $this->ollamaService->shouldReceive('enhance')
            ->twice()
            ->andReturn([
                'tags' => ['ai-tag'],
                'category' => 'architecture',
                'concepts' => ['concept'],
                'summary' => 'A summary.',
            ]);

        $this->qdrantService->shouldReceive('updateFields')
            ->twice()
            ->andReturn(true);

        $this->queueService->shouldReceive('recordSuccess')->twice();

        $this->artisan('enhance:worker')
            ->assertSuccessful();
    });

    it('preserves existing tags when merging', function (): void {
        $this->ollamaService->shouldReceive('isAvailable')->once()->andReturn(true);

        $this->queueService->shouldReceive('dequeue')
            ->once()
            ->andReturn([
                'entry_id' => 'test-id',
                'title' => 'Test Entry',
                'content' => 'Test content',
                'category' => null,
                'tags' => ['existing-tag'],
                'project' => 'default',
                'queued_at' => '2025-06-01T12:00:00+00:00',
            ]);

        $this->ollamaService->shouldReceive('enhance')
            ->once()
            ->andReturn([
                'tags' => ['new-tag', 'existing-tag'],
                'category' => 'testing',
                'concepts' => [],
                'summary' => 'Summary.',
            ]);

        $this->qdrantService->shouldReceive('updateFields')
            ->once()
            ->with('test-id', Mockery::on(fn ($fields): bool => $fields['tags'] === ['existing-tag', 'new-tag']), 'default')
            ->andReturn(true);

        $this->queueService->shouldReceive('recordSuccess')->once();

        $this->artisan('enhance:worker', ['--once' => true])
            ->assertSuccessful();
    });

    it('does not override existing category', function (): void {
        $this->ollamaService->shouldReceive('isAvailable')->once()->andReturn(true);

        $this->queueService->shouldReceive('dequeue')
            ->once()
            ->andReturn([
                'entry_id' => 'test-id',
                'title' => 'Test Entry',
                'content' => 'Test content',
                'category' => 'debugging',
                'tags' => [],
                'project' => 'default',
                'queued_at' => '2025-06-01T12:00:00+00:00',
            ]);

        $this->ollamaService->shouldReceive('enhance')
            ->once()
            ->andReturn([
                'tags' => ['tag'],
                'category' => 'testing',
                'concepts' => [],
                'summary' => 'Summary.',
            ]);

        $this->qdrantService->shouldReceive('updateFields')
            ->once()
            ->with('test-id', Mockery::on(fn ($fields): bool => ! isset($fields['category'])), 'default')
            ->andReturn(true);

        $this->queueService->shouldReceive('recordSuccess')->once();

        $this->artisan('enhance:worker', ['--once' => true])
            ->assertSuccessful();
    });
});
