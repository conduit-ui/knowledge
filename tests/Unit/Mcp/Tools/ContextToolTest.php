<?php

declare(strict_types=1);

use App\Mcp\Tools\ContextTool;
use App\Services\EntryMetadataService;
use App\Services\ProjectDetectorService;
use App\Services\QdrantService;
use Laravel\Mcp\Request;

uses()->group('mcp-tools');

beforeEach(function (): void {
    $this->qdrant = Mockery::mock(QdrantService::class);
    $this->metadata = Mockery::mock(EntryMetadataService::class);
    $this->projectDetector = Mockery::mock(ProjectDetectorService::class);

    $this->tool = new ContextTool(
        $this->qdrant,
        $this->metadata,
        $this->projectDetector,
    );
});

describe('context tool', function (): void {
    it('returns empty results with available projects when no entries found', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('empty-project');
        $this->qdrant->shouldReceive('scroll')
            ->once()
            ->andReturn(collect());
        $this->qdrant->shouldReceive('listCollections')
            ->once()
            ->andReturn(['knowledge_default', 'knowledge_odin']);

        $request = new Request([]);
        $response = $this->tool->handle($request);

        expect($response->isError())->toBeFalse();

        $data = json_decode((string) $response->content(), true);
        expect($data['total'])->toBe(0)
            ->and($data['project'])->toBe('empty-project')
            ->and($data['available_projects'])->toContain('default', 'odin');
    });

    it('returns grouped and ranked entries', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('test-project');
        $this->qdrant->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'entry-1',
                    'title' => 'Architecture Pattern',
                    'content' => 'Use hexagonal architecture.',
                    'category' => 'architecture',
                    'tags' => ['patterns'],
                    'priority' => 'high',
                    'usage_count' => 5,
                    'updated_at' => now()->toIso8601String(),
                    'confidence' => 80,
                ],
                [
                    'id' => 'entry-2',
                    'title' => 'Debug Tip',
                    'content' => 'Check logs first.',
                    'category' => 'debugging',
                    'tags' => [],
                    'priority' => 'medium',
                    'usage_count' => 2,
                    'updated_at' => now()->subDays(30)->toIso8601String(),
                    'confidence' => 60,
                ],
            ]));

        $this->metadata->shouldReceive('calculateEffectiveConfidence')->twice()->andReturn(75, 55);
        $this->metadata->shouldReceive('isStale')->twice()->andReturn(false, true);

        $request = new Request([]);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['total'])->toBe(2)
            ->and($data['categories'])->toHaveKey('architecture')
            ->and($data['categories'])->toHaveKey('debugging');
    });

    it('filters by specific categories', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('test-project');
        $this->qdrant->shouldReceive('scroll')
            ->with(['category' => 'architecture'], Mockery::any(), 'test-project')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'entry-1',
                    'title' => 'Arch Pattern',
                    'content' => 'Content here.',
                    'category' => 'architecture',
                    'tags' => [],
                    'priority' => 'high',
                    'usage_count' => 1,
                    'updated_at' => now()->toIso8601String(),
                    'confidence' => 80,
                ],
            ]));

        $this->metadata->shouldReceive('calculateEffectiveConfidence')->once()->andReturn(80);
        $this->metadata->shouldReceive('isStale')->once()->andReturn(false);

        $request = new Request(['categories' => ['architecture']]);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['total'])->toBe(1);
    });

    it('uses explicit project parameter', function (): void {
        $this->projectDetector->shouldNotReceive('detect');
        $this->qdrant->shouldReceive('scroll')
            ->withArgs(fn ($f, $l, $project) => $project === 'odin')
            ->once()
            ->andReturn(collect());
        $this->qdrant->shouldReceive('listCollections')->once()->andReturn([]);

        $request = new Request(['project' => 'odin']);
        $this->tool->handle($request);
    });
});

describe('schema', function (): void {
    it('returns valid schema definition', function (): void {
        $schema = new \Illuminate\JsonSchema\JsonSchemaTypeFactory;
        $result = $this->tool->schema($schema);
        expect($result)->toBeArray()->not->toBeEmpty();
    });
});

describe('context tool edge cases', function (): void {
    it('handles entry with invalid date string in updated_at', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('test-project');
        $this->qdrant->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'entry-bad-date',
                    'title' => 'Bad Date Entry',
                    'content' => 'Some content here.',
                    'category' => 'architecture',
                    'tags' => [],
                    'priority' => 'medium',
                    'usage_count' => 1,
                    'updated_at' => 'not-a-date',
                    'confidence' => 70,
                ],
            ]));

        $this->metadata->shouldReceive('calculateEffectiveConfidence')->once()->andReturn(70);
        $this->metadata->shouldReceive('isStale')->once()->andReturn(false);

        $request = new Request([]);
        $response = $this->tool->handle($request);

        expect($response->isError())->toBeFalse();

        $data = json_decode((string) $response->content(), true);
        expect($data['total'])->toBe(1);
    });

    it('groups entry with null category into uncategorized', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('test-project');
        $this->qdrant->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'entry-no-cat',
                    'title' => 'No Category Entry',
                    'content' => 'Some content here.',
                    'category' => null,
                    'tags' => [],
                    'priority' => 'medium',
                    'usage_count' => 1,
                    'updated_at' => now()->toIso8601String(),
                    'confidence' => 70,
                ],
            ]));

        $this->metadata->shouldReceive('calculateEffectiveConfidence')->once()->andReturn(70);
        $this->metadata->shouldReceive('isStale')->once()->andReturn(false);

        $request = new Request([]);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['categories'])->toHaveKey('uncategorized');
    });

    it('truncates output when max_tokens budget is exceeded', function (): void {
        $this->projectDetector->shouldReceive('detect')->once()->andReturn('test-project');
        $this->qdrant->shouldReceive('scroll')
            ->once()
            ->andReturn(collect([
                [
                    'id' => 'entry-1',
                    'title' => 'First Entry',
                    'content' => 'Some content here that is reasonably long.',
                    'category' => 'architecture',
                    'tags' => [],
                    'priority' => 'high',
                    'usage_count' => 5,
                    'updated_at' => now()->toIso8601String(),
                    'confidence' => 80,
                ],
                [
                    'id' => 'entry-2',
                    'title' => 'Second Entry',
                    'content' => 'More content here that pushes over the tiny budget.',
                    'category' => 'debugging',
                    'tags' => [],
                    'priority' => 'medium',
                    'usage_count' => 2,
                    'updated_at' => now()->toIso8601String(),
                    'confidence' => 60,
                ],
            ]));

        $this->metadata->shouldReceive('calculateEffectiveConfidence')->andReturn(80, 60);
        $this->metadata->shouldReceive('isStale')->andReturn(false, false);

        // max_tokens=1 means max 4 chars — both entries will exceed the budget
        $request = new Request(['max_tokens' => 1]);
        $response = $this->tool->handle($request);

        $data = json_decode((string) $response->content(), true);
        expect($data['total'])->toBe(0)
            ->and($data['categories'])->toBeEmpty();
    });
});
