<?php

declare(strict_types=1);

use App\Mcp\Tools\CorrectTool;
use App\Services\CorrectionService;
use Laravel\Mcp\Request;

uses()->group('mcp-tools');

beforeEach(function (): void {
    $this->correctionService = Mockery::mock(CorrectionService::class);
    $this->tool = new CorrectTool($this->correctionService);
});

describe('correct tool', function (): void {
    it('returns error when id is missing', function (): void {
        $request = new Request(['corrected_content' => 'New corrected content here.']);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });

    it('returns error when corrected content is missing', function (): void {
        $request = new Request(['id' => 'entry-123']);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });

    it('returns error when corrected content is too short', function (): void {
        $request = new Request(['id' => 'entry-123', 'corrected_content' => 'Short']);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });

    it('corrects entry and returns result', function (): void {
        $this->correctionService->shouldReceive('correct')
            ->with('entry-123', 'This is the corrected information that replaces the original.')
            ->once()
            ->andReturn([
                'corrected_entry_id' => 'new-entry-456',
                'superseded_ids' => ['entry-789'],
                'conflicts_found' => 1,
            ]);

        $request = new Request([
            'id' => 'entry-123',
            'corrected_content' => 'This is the corrected information that replaces the original.',
        ]);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeFalse();

        $data = json_decode((string) $response->content(), true);
        expect($data['status'])->toBe('corrected')
            ->and($data['corrected_entry_id'])->toBe('new-entry-456')
            ->and($data['original_id'])->toBe('entry-123')
            ->and($data['conflicts_resolved'])->toBe(1);
    });

    it('handles correction service failures', function (): void {
        $this->correctionService->shouldReceive('correct')
            ->once()
            ->andThrow(new RuntimeException('Entry not found'));

        $request = new Request([
            'id' => 'nonexistent-id',
            'corrected_content' => 'Corrected content that will fail to apply.',
        ]);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });
});
