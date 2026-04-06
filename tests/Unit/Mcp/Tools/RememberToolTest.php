<?php

declare(strict_types=1);

use App\Exceptions\Qdrant\DuplicateEntryException;
use App\Mcp\Tools\RememberTool;
use App\Services\EnhancementQueueService;
use App\Services\GitContextService;
use App\Services\ProjectDetectorService;
use App\Services\QdrantService;
use App\Services\WriteGateService;
use Laravel\Mcp\Request;

uses()->group('mcp-tools');

beforeEach(function (): void {
    $this->qdrant = Mockery::mock(QdrantService::class);
    $this->writeGate = Mockery::mock(WriteGateService::class);
    $this->gitContext = Mockery::mock(GitContextService::class);
    $this->projectDetector = Mockery::mock(ProjectDetectorService::class);
    $this->enhancementQueue = Mockery::mock(EnhancementQueueService::class);

    $this->tool = new RememberTool(
        $this->qdrant,
        $this->writeGate,
        $this->gitContext,
        $this->projectDetector,
        $this->enhancementQueue,
    );
});

describe('remember tool', function (): void {
    it('returns error when title is missing', function (): void {
        $request = new Request(['content' => 'Some long content here']);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });

    it('returns error when title is too short', function (): void {
        $request = new Request(['title' => 'Hi', 'content' => 'Some long content here']);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });

    it('returns error when content is missing', function (): void {
        $request = new Request(['title' => 'Valid Title']);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });

    it('returns error when content is too short', function (): void {
        $request = new Request(['title' => 'Valid Title', 'content' => 'Short']);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });

    it('creates entry successfully with git context', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('test-project');
        $this->gitContext->shouldReceive('isGitRepository')->once()->andReturn(true);
        $this->gitContext->shouldReceive('getContext')->once()->andReturn([
            'repo' => 'knowledge',
            'branch' => 'main',
            'commit' => 'abc123',
            'author' => 'test',
        ]);
        $this->writeGate->shouldReceive('evaluate')->once()->andReturn(['passed' => true]);
        $this->qdrant->shouldReceive('upsert')->once()->andReturn(true);
        $this->enhancementQueue->shouldReceive('queue')->once();

        config(['search.ollama.enabled' => true]);

        $request = new Request([
            'title' => 'Test Discovery',
            'content' => 'This is an important discovery about the system.',
        ]);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeFalse();

        $data = json_decode((string) $response->content(), true);
        expect($data['status'])->toBe('created')
            ->and($data['project'])->toBe('test-project');
    });

    it('returns write gate rejection', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('default');
        $this->gitContext->shouldReceive('isGitRepository')->once()->andReturn(false);
        $this->writeGate->shouldReceive('evaluate')->once()->andReturn([
            'passed' => false,
            'reason' => 'Content too generic',
        ]);

        $request = new Request([
            'title' => 'Generic Title Here',
            'content' => 'This is some generic content for testing.',
        ]);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });

    it('handles duplicate detection gracefully', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('default');
        $this->gitContext->shouldReceive('isGitRepository')->once()->andReturn(false);
        $this->writeGate->shouldReceive('evaluate')->once()->andReturn(['passed' => true]);
        $this->qdrant->shouldReceive('upsert')->once()->andThrow(
            DuplicateEntryException::similarityMatch('existing-id', 0.98)
        );

        $request = new Request([
            'title' => 'Duplicate Entry',
            'content' => 'This content already exists in the knowledge base.',
        ]);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeFalse();

        $data = json_decode((string) $response->content(), true);
        expect($data['status'])->toBe('duplicate_detected')
            ->and($data['existing_id'])->toBe('existing-id');
    });

    it('uses explicit project when provided', function (): void {
        $this->projectDetector->shouldNotReceive('detect');
        $this->gitContext->shouldReceive('isGitRepository')->once()->andReturn(false);
        $this->writeGate->shouldReceive('evaluate')->once()->andReturn(['passed' => true]);
        $this->qdrant->shouldReceive('upsert')
            ->withArgs(fn ($entry, $project) => $project === 'custom-project')
            ->once()
            ->andReturn(true);
        $this->enhancementQueue->shouldReceive('queue')->once();

        config(['search.ollama.enabled' => true]);

        $request = new Request([
            'title' => 'Project Specific',
            'content' => 'This belongs to a specific project namespace.',
            'project' => 'custom-project',
        ]);

        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['project'])->toBe('custom-project');
    });
});

describe('schema', function (): void {
    it('returns valid schema definition', function (): void {
        $schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;
        $result = $this->tool->schema($schema);
        expect($result)->toBeArray()->not->toBeEmpty();
    });
});
