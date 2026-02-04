<?php

declare(strict_types=1);

use App\Exceptions\Qdrant\CollectionCreationException;
use App\Exceptions\Qdrant\QdrantException;

describe('CollectionCreationException', function (): void {
    it('extends QdrantException', function (): void {
        $exception = new CollectionCreationException('Test message');

        expect($exception)->toBeInstanceOf(QdrantException::class);
    });

    it('creates exception with collection name and reason', function (): void {
        $exception = CollectionCreationException::withReason('my-collection', 'Invalid schema');

        expect($exception->getMessage())
            ->toBe("Failed to create collection 'my-collection': Invalid schema");
    });

    it('includes collection name in error message', function (): void {
        $exception = CollectionCreationException::withReason('test-collection', 'Network timeout');

        expect($exception->getMessage())
            ->toContain('test-collection')
            ->toContain('Network timeout');
    });

    it('can be thrown', function (): void {
        expect(fn () => throw CollectionCreationException::withReason('test', 'reason'))
            ->toThrow(CollectionCreationException::class);
    });

    it('handles special characters in collection name', function (): void {
        $exception = CollectionCreationException::withReason('collection-with-dashes_123', 'Error occurred');

        expect($exception->getMessage())
            ->toContain('collection-with-dashes_123');
    });

    it('preserves full reason in message', function (): void {
        $reason = 'Detailed error: vector size mismatch (expected 384, got 512)';
        $exception = CollectionCreationException::withReason('vectors', $reason);

        expect($exception->getMessage())
            ->toContain($reason);
    });
});
