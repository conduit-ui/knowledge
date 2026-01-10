<?php

declare(strict_types=1);

use App\Exceptions\Qdrant\QdrantException;
use App\Exceptions\Qdrant\UpsertException;

describe('UpsertException', function () {
    it('extends QdrantException', function () {
        $exception = new UpsertException('Test message');

        expect($exception)->toBeInstanceOf(QdrantException::class);
    });

    it('creates exception with reason', function () {
        $exception = UpsertException::withReason('Vector dimension mismatch');

        expect($exception->getMessage())
            ->toBe('Failed to upsert entry to Qdrant: Vector dimension mismatch');
    });

    it('includes reason in error message', function () {
        $exception = UpsertException::withReason('Connection timeout');

        expect($exception->getMessage())
            ->toContain('Connection timeout')
            ->toContain('Failed to upsert');
    });

    it('can be thrown', function () {
        expect(fn () => throw UpsertException::withReason('Test reason'))
            ->toThrow(UpsertException::class);
    });

    it('handles detailed error messages', function () {
        $reason = 'HTTP 500: Internal server error - collection not found';
        $exception = UpsertException::withReason($reason);

        expect($exception->getMessage())
            ->toContain($reason);
    });

    it('preserves full error context', function () {
        $reason = 'Validation failed: payload field "embedding" is required but missing';
        $exception = UpsertException::withReason($reason);

        expect($exception->getMessage())
            ->toContain('payload field "embedding"');
    });
});
