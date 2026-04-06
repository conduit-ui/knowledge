<?php

declare(strict_types=1);

use App\Mcp\Tools\RecallTool;
use App\Services\EntryMetadataService;
use App\Services\ProjectDetectorService;
use App\Services\QdrantService;
use App\Services\TieredSearchService;
use Laravel\Mcp\Request;

uses()->group('mcp-tools');

beforeEach(function (): void {
    $this->tieredSearch = Mockery::mock(TieredSearchService::class);
    $this->qdrant = Mockery::mock(QdrantService::class);
    $this->metadata = Mockery::mock(EntryMetadataService::class);
    $this->projectDetector = Mockery::mock(ProjectDetectorService::class);

    $this->tool = new RecallTool(
        $this->tieredSearch,
        $this->qdrant,
        $this->metadata,
        $this->projectDetector,
    );
});

describe('recall tool', function (): void {
    it('returns error when query is missing', function (): void {
        $request = new Request([]);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });

    it('returns error when query is too short', function (): void {
        $request = new Request(['query' => 'a']);

        $response = $this->tool->handle($request);

        expect($response->isError())->toBeTrue();
    });

    it('returns empty results when nothing found', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('test-project');
        $this->tieredSearch->shouldReceive('search')
            ->once()
            ->andReturn(collect());

        $request = new Request(['query' => 'test query']);
        $response = $this->tool->handle($request);

        expect($response->isError())->toBeFalse();

        $data = json_decode((string) $response->content(), true);
        expect($data['results'])->toBeEmpty()
            ->and($data['meta']['total'])->toBe(0)
            ->and($data['meta']['project'])->toBe('test-project');
    });

    it('returns formatted results with confidence and freshness', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('test-project');
        $this->tieredSearch->shouldReceive('search')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'entry-1',
                    'title' => 'Test Entry',
                    'content' => 'Some content',
                    'category' => 'architecture',
                    'tags' => ['laravel'],
                    'tiered_score' => 0.95,
                    'tier_label' => 'exact',
                    'confidence' => 80,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]));

        $this->metadata->shouldReceive('calculateEffectiveConfidence')->once()->andReturn(75);
        $this->metadata->shouldReceive('isStale')->once()->andReturn(false);

        $request = new Request(['query' => 'test query']);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['results'])->toHaveCount(1)
            ->and($data['results'][0]['id'])->toBe('entry-1')
            ->and($data['results'][0]['confidence'])->toBe(75)
            ->and($data['results'][0]['freshness'])->toBe('fresh')
            ->and($data['meta']['total'])->toBe(1);
    });

    it('uses explicit project when provided', function (): void {
        $this->projectDetector->shouldNotReceive('detect');
        $this->tieredSearch->shouldReceive('search')
            ->withArgs(fn ($q, $f, $l, $forceTier, $p) => $p === 'my-project')
            ->once()
            ->andReturn(collect());

        $request = new Request(['query' => 'test', 'project' => 'my-project']);
        $this->tool->handle($request);
    });

    it('searches globally across all collections', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('default');
        $this->qdrant->shouldReceive('listCollections')
            ->once()
            ->andReturn(['knowledge_project_a', 'knowledge_project_b']);

        $this->tieredSearch->shouldReceive('search')
            ->twice()
            ->andReturn(collect());

        $request = new Request(['query' => 'test', 'global' => true]);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['meta']['collections_searched'])->toBe(2);
    });

    it('respects limit parameter', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('default');
        $this->tieredSearch->shouldReceive('search')
            ->withArgs(fn ($q, $f, $limit) => $limit === 10)
            ->once()
            ->andReturn(collect());

        $request = new Request(['query' => 'test', 'limit' => 10]);
        $this->tool->handle($request);
    });

    it('adds project field to each result when searching globally', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('default');
        $this->qdrant->shouldReceive('listCollections')
            ->once()
            ->andReturn(['knowledge_alpha', 'knowledge_beta']);

        $alphaEntry = [
            'id' => 'entry-a1',
            'title' => 'Alpha Entry',
            'content' => 'Alpha content',
            'category' => 'architecture',
            'tags' => [],
            'tiered_score' => 0.9,
            'tier_label' => 'exact',
            'confidence' => 80,
            'updated_at' => now()->toIso8601String(),
        ];

        $this->tieredSearch->shouldReceive('search')
            ->withArgs(fn ($q, $f, $l, $forceTier, $project) => $project === 'alpha')
            ->once()
            ->andReturn(collect([$alphaEntry]));

        $this->tieredSearch->shouldReceive('search')
            ->withArgs(fn ($q, $f, $l, $forceTier, $project) => $project === 'beta')
            ->once()
            ->andReturn(collect());

        $this->metadata->shouldReceive('calculateEffectiveConfidence')->once()->andReturn(80);
        $this->metadata->shouldReceive('isStale')->once()->andReturn(false);

        $request = new Request(['query' => 'shared concept', 'global' => true]);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['results'])->toHaveCount(1)
            ->and($data['results'][0]['project'])->toBe('alpha')
            ->and($data['meta']['collections_searched'])->toBe(2);
    });
});

describe('schema', function (): void {
    it('returns valid schema definition', function (): void {
        $schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;
        $result = $this->tool->schema($schema);
        expect($result)->toBeArray()->not->toBeEmpty();
    });
});
