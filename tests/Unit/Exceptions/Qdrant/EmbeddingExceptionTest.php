<?php

declare(strict_types=1);

use App\Exceptions\Qdrant\EmbeddingException;
use App\Exceptions\Qdrant\QdrantException;

describe('EmbeddingException', function (): void {
    it('extends QdrantException', function (): void {
        $exception = new EmbeddingException('Test message');

        expect($exception)->toBeInstanceOf(QdrantException::class);
    });

    it('creates exception with text preview', function (): void {
        $text = 'This is a sample text that should be embedded';
        $exception = EmbeddingException::generationFailed($text);

        expect($exception->getMessage())
            ->toBe('Failed to generate embedding for text: This is a sample text that should be embedded...');
    });

    it('truncates long text to 50 characters', function (): void {
        $text = str_repeat('a', 100);
        $exception = EmbeddingException::generationFailed($text);

        expect($exception->getMessage())
            ->toContain(str_repeat('a', 50).'...')
            ->not->toContain(str_repeat('a', 51));
    });

    it('handles short text without truncation', function (): void {
        $text = 'Short text';
        $exception = EmbeddingException::generationFailed($text);

        expect($exception->getMessage())
            ->toContain($text.'...');
    });

    it('can be thrown', function (): void {
        expect(fn () => throw EmbeddingException::generationFailed('test'))
            ->toThrow(EmbeddingException::class);
    });

    it('handles multibyte characters correctly', function (): void {
        $text = str_repeat('🚀', 60);
        $exception = EmbeddingException::generationFailed($text);

        expect($exception->getMessage())
            ->toContain('Failed to generate embedding');
    });

    it('preserves special characters in preview', function (): void {
        $text = 'Text with special chars: @#$%^&*()';
        $exception = EmbeddingException::generationFailed($text);

        expect($exception->getMessage())
            ->toContain('Text with special chars: @#$%^&*()');
    });

    it('handles empty string', function (): void {
        $exception = EmbeddingException::generationFailed('');

        expect($exception->getMessage())
            ->toBe('Failed to generate embedding for text: ...');
    });

    it('handles exactly 50 character text', function (): void {
        $text = str_repeat('x', 50);
        $exception = EmbeddingException::generationFailed($text);

        expect($exception->getMessage())
            ->toContain($text.'...');
    });
});
