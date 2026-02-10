<?php

declare(strict_types=1);

use App\Services\ProjectDetectorService;
use App\Services\QdrantService;

beforeEach(function (): void {
    $this->qdrantMock = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->qdrantMock);

    $this->detectorMock = Mockery::mock(ProjectDetectorService::class);
    $this->app->instance(ProjectDetectorService::class, $this->detectorMock);
});

it('lists all project knowledge bases', function (): void {
    $this->detectorMock->shouldReceive('detect')->andReturn('knowledge');

    $this->qdrantMock->shouldReceive('listCollections')
        ->once()
        ->andReturn(['knowledge_knowledge', 'knowledge_other-project']);

    $this->qdrantMock->shouldReceive('count')
        ->with('knowledge')
        ->andReturn(42);

    $this->qdrantMock->shouldReceive('count')
        ->with('other-project')
        ->andReturn(15);

    $this->artisan('projects')
        ->assertSuccessful()
        ->expectsOutputToContain('Project Knowledge Bases');
});

it('shows warning when no projects exist', function (): void {
    $this->detectorMock->shouldReceive('detect')->andReturn('default');

    $this->qdrantMock->shouldReceive('listCollections')
        ->once()
        ->andReturn([]);

    $this->artisan('projects')
        ->assertSuccessful();
});

it('shows current project indicator', function (): void {
    $this->detectorMock->shouldReceive('detect')->andReturn('my-project');

    $this->qdrantMock->shouldReceive('listCollections')
        ->once()
        ->andReturn(['knowledge_my-project']);

    $this->qdrantMock->shouldReceive('count')
        ->with('my-project')
        ->andReturn(10);

    $this->artisan('projects')
        ->assertSuccessful()
        ->expectsOutputToContain('Current project: my-project');
});
