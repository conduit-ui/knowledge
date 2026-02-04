<?php

declare(strict_types=1);

use App\Exceptions\Qdrant\DuplicateEntryException;
use App\Exceptions\Qdrant\QdrantException;

describe('DuplicateEntryException', function (): void {
    it('extends QdrantException', function (): void {
        $exception = DuplicateEntryException::hashMatch('existing-id', 'abc123');

        expect($exception)->toBeInstanceOf(QdrantException::class);
    });

    describe('hashMatch', function (): void {
        it('creates exception for hash-based duplicate', function (): void {
            $exception = DuplicateEntryException::hashMatch('test-id-123', 'sha256hash');

            expect($exception->duplicateType)->toBe(DuplicateEntryException::TYPE_HASH);
            expect($exception->existingId)->toBe('test-id-123');
            expect($exception->similarityScore)->toBeNull();
            expect($exception->getMessage())->toContain('sha256hash');
            expect($exception->getMessage())->toContain('test-id-123');
        });

        it('works with integer IDs', function (): void {
            $exception = DuplicateEntryException::hashMatch(42, 'hash123');

            expect($exception->existingId)->toBe(42);
        });
    });

    describe('similarityMatch', function (): void {
        it('creates exception for similarity-based duplicate', function (): void {
            $exception = DuplicateEntryException::similarityMatch('similar-id', 0.97);

            expect($exception->duplicateType)->toBe(DuplicateEntryException::TYPE_SIMILARITY);
            expect($exception->existingId)->toBe('similar-id');
            expect($exception->similarityScore)->toBe(0.97);
            expect($exception->getMessage())->toContain('97%');
            expect($exception->getMessage())->toContain('similar-id');
        });

        it('rounds similarity percentage correctly', function (): void {
            $exception = DuplicateEntryException::similarityMatch('id', 0.9567);

            expect($exception->getMessage())->toContain('95.7%');
        });

        it('works with integer IDs', function (): void {
            $exception = DuplicateEntryException::similarityMatch(999, 0.95);

            expect($exception->existingId)->toBe(999);
        });
    });

    describe('type constants', function (): void {
        it('has correct type constants', function (): void {
            expect(DuplicateEntryException::TYPE_HASH)->toBe('hash');
            expect(DuplicateEntryException::TYPE_SIMILARITY)->toBe('similarity');
        });
    });
});
