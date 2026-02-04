<?php

declare(strict_types=1);

use App\Services\StubEmbeddingService;

beforeEach(function (): void {
    $this->service = new StubEmbeddingService;
});

describe('generate', function (): void {
    it('returns empty array for any input', function (): void {
        $result = $this->service->generate('test text');

        expect($result)->toBe([]);
    });

    it('returns empty array for empty string', function (): void {
        $result = $this->service->generate('');

        expect($result)->toBe([]);
    });

    it('returns empty array for long text', function (): void {
        $longText = str_repeat('Lorem ipsum dolor sit amet. ', 1000);
        $result = $this->service->generate($longText);

        expect($result)->toBe([]);
    });

    it('returns empty array for special characters', function (): void {
        $result = $this->service->generate('Special chars: !@#$%^&*()');

        expect($result)->toBe([]);
    });
});

describe('similarity', function (): void {
    it('returns zero for any two vectors', function (): void {
        $vector1 = [0.1, 0.2, 0.3];
        $vector2 = [0.4, 0.5, 0.6];

        $result = $this->service->similarity($vector1, $vector2);

        expect($result)->toBe(0.0);
    });

    it('returns zero for empty vectors', function (): void {
        $result = $this->service->similarity([], []);

        expect($result)->toBe(0.0);
    });

    it('returns zero for identical vectors', function (): void {
        $vector = [0.5, 0.5, 0.5];

        $result = $this->service->similarity($vector, $vector);

        expect($result)->toBe(0.0);
    });

    it('returns zero for different sized vectors', function (): void {
        $vector1 = [0.1, 0.2];
        $vector2 = [0.3, 0.4, 0.5];

        $result = $this->service->similarity($vector1, $vector2);

        expect($result)->toBe(0.0);
    });

    it('always returns float type', function (): void {
        $result = $this->service->similarity([1.0], [2.0]);

        expect($result)->toBeFloat();
    });
});
