<?php

declare(strict_types=1);

use App\Exceptions\Qdrant\QdrantException;

describe('QdrantException', function (): void {
    it('extends RuntimeException', function (): void {
        $exception = new QdrantException('Test message');

        expect($exception)->toBeInstanceOf(RuntimeException::class);
    });

    it('can be instantiated with a message', function (): void {
        $message = 'Test Qdrant error';
        $exception = new QdrantException($message);

        expect($exception->getMessage())->toBe($message);
    });

    it('can be instantiated with message and code', function (): void {
        $message = 'Test error';
        $code = 500;
        $exception = new QdrantException($message, $code);

        expect($exception->getMessage())->toBe($message)
            ->and($exception->getCode())->toBe($code);
    });

    it('can be instantiated with message code and previous exception', function (): void {
        $previous = new Exception('Previous error');
        $exception = new QdrantException('Current error', 0, $previous);

        expect($exception->getPrevious())->toBe($previous);
    });

    it('can be thrown', function (): void {
        expect(fn () => throw new QdrantException('Test'))
            ->toThrow(QdrantException::class, 'Test');
    });
});
