<?php

declare(strict_types=1);

use App\Mcp\Tools\StatsTool;
use App\Services\ProjectDetectorService;
use App\Services\QdrantService;
use Laravel\Mcp\Request;

uses()->group('mcp-tools');

beforeEach(function (): void {
    $this->qdrant = Mockery::mock(QdrantService::class);
    $this->projectDetector = Mockery::mock(ProjectDetectorService::class);

    $this->tool = new StatsTool($this->qdrant, $this->projectDetector);
});

describe('stats tool', function (): void {
    it('returns stats across all projects', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('knowledge');
        $this->qdrant->shouldReceive('listCollections')
            ->once()
            ->andReturn(['knowledge_default', 'knowledge_odin', 'knowledge_knowledge']);
        $this->qdrant->shouldReceive('count')
            ->with('default')
            ->once()
            ->andReturn(4549);
        $this->qdrant->shouldReceive('count')
            ->with('odin')
            ->once()
            ->andReturn(3);
        $this->qdrant->shouldReceive('count')
            ->with('knowledge')
            ->once()
            ->andReturn(18);

        $request = new Request([]);
        $response = $this->tool->handle($request);

        expect($response->isError())->toBeFalse();

        $data = json_decode((string) $response->content(), true);
        expect($data['current_project'])->toBe('knowledge')
            ->and($data['current_project_entries'])->toBe(18)
            ->and($data['total_entries'])->toBe(4570)
            ->and($data['project_count'])->toBe(3)
            ->and($data['projects']['default'])->toBe(4549);
    });

    it('uses explicit project parameter', function (): void {
        $this->projectDetector->shouldNotReceive('detect');
        $this->qdrant->shouldReceive('listCollections')->once()->andReturn(['knowledge_odin']);
        $this->qdrant->shouldReceive('count')->with('odin')->once()->andReturn(3);

        $request = new Request(['project' => 'odin']);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['current_project'])->toBe('odin')
            ->and($data['current_project_entries'])->toBe(3);
    });
});

describe('schema', function (): void {
    it('returns valid schema definition', function (): void {
        $schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;
        $result = $this->tool->schema($schema);
        expect($result)->toBeArray()->not->toBeEmpty();
    });
});
