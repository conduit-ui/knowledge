<?php

declare(strict_types=1);

use App\Exceptions\Qdrant\CollectionNotFoundException;
use App\Exceptions\Qdrant\QdrantException;

describe('CollectionNotFoundException', function () {
    it('is instance of QdrantException', function () {
        $exception = CollectionNotFoundException::forCollection('test-collection');

        expect($exception)->toBeInstanceOf(QdrantException::class);
    });

    it('is instance of RuntimeException', function () {
        $exception = CollectionNotFoundException::forCollection('test-collection');

        expect($exception)->toBeInstanceOf(\RuntimeException::class);
    });

    it('creates exception with collection name in message', function () {
        $exception = CollectionNotFoundException::forCollection('my-collection');

        expect($exception->getMessage())->toContain('my-collection');
    });

    it('includes "not found" in message', function () {
        $exception = CollectionNotFoundException::forCollection('test-collection');

        expect($exception->getMessage())->toContain('not found');
    });

    it('includes "Qdrant" in message', function () {
        $exception = CollectionNotFoundException::forCollection('test-collection');

        expect($exception->getMessage())->toContain('Qdrant');
    });

    it('formats message correctly', function () {
        $collectionName = 'knowledge-base';
        $exception = CollectionNotFoundException::forCollection($collectionName);

        $expectedMessage = "Collection '{$collectionName}' not found in Qdrant";
        expect($exception->getMessage())->toBe($expectedMessage);
    });

    it('handles collection names with special characters', function () {
        $exception = CollectionNotFoundException::forCollection('test-collection-123');

        expect($exception->getMessage())->toContain('test-collection-123');
    });

    it('handles empty collection name', function () {
        $exception = CollectionNotFoundException::forCollection('');

        expect($exception->getMessage())->toContain("Collection '' not found");
    });

    it('handles collection names with spaces', function () {
        $exception = CollectionNotFoundException::forCollection('my collection');

        expect($exception->getMessage())->toContain('my collection');
    });

    it('handles collection names with quotes', function () {
        $exception = CollectionNotFoundException::forCollection('collection"with"quotes');

        expect($exception->getMessage())->toContain('collection"with"quotes');
    });

    it('can be thrown and caught as QdrantException', function () {
        try {
            throw CollectionNotFoundException::forCollection('test');
        } catch (QdrantException $e) {
            expect($e)->toBeInstanceOf(CollectionNotFoundException::class);
        }
    });

    it('can be thrown and caught as RuntimeException', function () {
        try {
            throw CollectionNotFoundException::forCollection('test');
        } catch (\RuntimeException $e) {
            expect($e)->toBeInstanceOf(CollectionNotFoundException::class);
        }
    });

    it('can be thrown and caught as Exception', function () {
        try {
            throw CollectionNotFoundException::forCollection('test');
        } catch (\Exception $e) {
            expect($e)->toBeInstanceOf(CollectionNotFoundException::class);
        }
    });

    it('preserves collection name in message for debugging', function () {
        $collectionName = 'debug-collection-xyz';
        $exception = CollectionNotFoundException::forCollection($collectionName);

        expect($exception->getMessage())->toBe("Collection '{$collectionName}' not found in Qdrant");
    });

    it('creates new instance each time', function () {
        $exception1 = CollectionNotFoundException::forCollection('collection1');
        $exception2 = CollectionNotFoundException::forCollection('collection2');

        expect($exception1)->not->toBe($exception2);
        expect($exception1->getMessage())->not->toBe($exception2->getMessage());
    });
});
