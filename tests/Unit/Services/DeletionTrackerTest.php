<?php

declare(strict_types=1);

use App\Services\DeletionTracker;
use App\Services\KnowledgePathService;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);

    $pathService = Mockery::mock(KnowledgePathService::class);
    $pathService->shouldReceive('getKnowledgeDirectory')
        ->andReturn($this->tempDir);

    $this->tracker = new DeletionTracker($pathService);
});

afterEach(function (): void {
    $file = $this->tempDir.'/deletions.json';
    if (file_exists($file)) {
        unlink($file);
    }
    if (is_dir($this->tempDir)) {
        rmdir($this->tempDir);
    }
});

describe('DeletionTracker', function (): void {
    it('starts with no tracked deletions', function (): void {
        expect($this->tracker->all())->toBe([])
            ->and($this->tracker->count())->toBe(0)
            ->and($this->tracker->getDeletedIds())->toBe([]);
    });

    it('tracks a single deletion', function (): void {
        $this->tracker->track('unique-id-1', '2025-01-01T00:00:00+00:00');

        expect($this->tracker->count())->toBe(1)
            ->and($this->tracker->isTracked('unique-id-1'))->toBeTrue()
            ->and($this->tracker->isTracked('nonexistent'))->toBeFalse()
            ->and($this->tracker->all())->toBe(['unique-id-1' => '2025-01-01T00:00:00+00:00']);
    });

    it('tracks multiple deletions at once', function (): void {
        $this->tracker->trackMany(['id-1', 'id-2', 'id-3'], '2025-06-01T12:00:00+00:00');

        expect($this->tracker->count())->toBe(3)
            ->and($this->tracker->getDeletedIds())->toBe(['id-1', 'id-2', 'id-3'])
            ->and($this->tracker->isTracked('id-1'))->toBeTrue()
            ->and($this->tracker->isTracked('id-2'))->toBeTrue()
            ->and($this->tracker->isTracked('id-3'))->toBeTrue();
    });

    it('removes a single tracked deletion', function (): void {
        $this->tracker->trackMany(['id-1', 'id-2']);
        $this->tracker->remove('id-1');

        expect($this->tracker->count())->toBe(1)
            ->and($this->tracker->isTracked('id-1'))->toBeFalse()
            ->and($this->tracker->isTracked('id-2'))->toBeTrue();
    });

    it('removes multiple tracked deletions', function (): void {
        $this->tracker->trackMany(['id-1', 'id-2', 'id-3']);
        $this->tracker->removeMany(['id-1', 'id-3']);

        expect($this->tracker->count())->toBe(1)
            ->and($this->tracker->isTracked('id-2'))->toBeTrue();
    });

    it('clears all tracked deletions', function (): void {
        $this->tracker->trackMany(['id-1', 'id-2']);
        $this->tracker->clear();

        expect($this->tracker->count())->toBe(0)
            ->and($this->tracker->all())->toBe([]);
    });

    it('persists deletions to disk', function (): void {
        $this->tracker->track('persisted-id', '2025-01-15T08:30:00+00:00');

        // Create a new tracker instance to verify persistence
        $pathService = Mockery::mock(KnowledgePathService::class);
        $pathService->shouldReceive('getKnowledgeDirectory')
            ->andReturn($this->tempDir);

        $newTracker = new DeletionTracker($pathService);

        expect($newTracker->isTracked('persisted-id'))->toBeTrue()
            ->and($newTracker->all())->toBe(['persisted-id' => '2025-01-15T08:30:00+00:00']);
    });

    it('returns correct file path', function (): void {
        expect($this->tracker->getFilePath())->toBe($this->tempDir.'/deletions.json');
    });

    it('handles missing directory gracefully', function (): void {
        $nestedDir = $this->tempDir.'/nested/deep';

        $pathService = Mockery::mock(KnowledgePathService::class);
        $pathService->shouldReceive('getKnowledgeDirectory')
            ->andReturn($nestedDir);

        $tracker = new DeletionTracker($pathService);
        $tracker->track('test-id');

        expect($tracker->isTracked('test-id'))->toBeTrue()
            ->and(file_exists($nestedDir.'/deletions.json'))->toBeTrue();

        // Cleanup nested dirs
        unlink($nestedDir.'/deletions.json');
        rmdir($nestedDir);
        rmdir($this->tempDir.'/nested');
    });

    it('handles corrupt JSON file gracefully', function (): void {
        file_put_contents($this->tempDir.'/deletions.json', 'not valid json');

        $pathService = Mockery::mock(KnowledgePathService::class);
        $pathService->shouldReceive('getKnowledgeDirectory')
            ->andReturn($this->tempDir);

        $tracker = new DeletionTracker($pathService);

        expect($tracker->all())->toBe([])
            ->and($tracker->count())->toBe(0);
    });

    it('uses current timestamp when no deletedAt provided', function (): void {
        $this->tracker->track('auto-timestamp');

        $deletions = $this->tracker->all();
        expect($deletions)->toHaveKey('auto-timestamp');

        // Verify it looks like an ISO 8601 timestamp
        $timestamp = $deletions['auto-timestamp'];
        expect($timestamp)->toBeString()
            ->and(strlen($timestamp))->toBeGreaterThan(10);
    });
});
