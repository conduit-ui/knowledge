<?php

declare(strict_types=1);

use App\Exceptions\Qdrant\ConnectionException;
use App\Exceptions\Qdrant\QdrantException;

describe('ConnectionException', function (): void {
    it('extends QdrantException', function (): void {
        $exception = new ConnectionException('Test message');

        expect($exception)->toBeInstanceOf(QdrantException::class);
    });

    it('creates exception with custom message', function (): void {
        $exception = ConnectionException::withMessage('Could not connect to localhost:6333');

        expect($exception->getMessage())
            ->toBe('Qdrant connection failed: Could not connect to localhost:6333');
    });

    it('includes connection details in error message', function (): void {
        $exception = ConnectionException::withMessage('Connection refused');

        expect($exception->getMessage())
            ->toContain('Connection refused')
            ->toContain('Qdrant connection failed');
    });

    it('can be thrown', function (): void {
        expect(fn () => throw ConnectionException::withMessage('Timeout'))
            ->toThrow(ConnectionException::class);
    });

    it('handles network error messages', function (): void {
        $message = 'Failed to connect to [::1]:6333: Connection refused';
        $exception = ConnectionException::withMessage($message);

        expect($exception->getMessage())
            ->toContain($message);
    });

    it('preserves detailed connection errors', function (): void {
        $message = 'SSL certificate verification failed for https://qdrant.example.com:6333';
        $exception = ConnectionException::withMessage($message);

        expect($exception->getMessage())
            ->toContain('SSL certificate')
            ->toContain('qdrant.example.com');
    });
});
