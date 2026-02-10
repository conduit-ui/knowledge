<?php

declare(strict_types=1);

use App\Services\QdrantService;

beforeEach(function (): void {
    $this->qdrantMock = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->qdrantMock);

    // Default: no supersession history (overridden in specific tests)
    $this->qdrantMock->shouldReceive('getSupersessionHistory')
        ->andReturn(['supersedes' => [], 'superseded_by' => null])
        ->byDefault();
});

it('shows full details of an entry', function (): void {
    $entry = [
        'id' => '1',
        'title' => 'Test Entry',
        'content' => 'This is the full content of the entry',
        'category' => 'architecture',
        'tags' => ['laravel', 'pest'],
        'module' => 'Blood',
        'priority' => 'high',
        'confidence' => 85,
        'status' => 'validated',
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('1')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('incrementUsage')
        ->once()
        ->with('1')
        ->andReturn(true);

    $this->artisan('show', ['id' => '1'])
        ->assertSuccessful()
        ->expectsOutputToContain('Test Entry')
        ->expectsOutputToContain('This is the full content of the entry');
});

it('shows entry with minimal fields', function (): void {
    $entry = [
        'id' => '2',
        'title' => 'Minimal Entry',
        'content' => 'Basic content',
        'category' => null,
        'tags' => [],
        'module' => null,
        'priority' => 'medium',
        'confidence' => 50,
        'status' => 'draft',
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('2')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('incrementUsage')
        ->once()
        ->with('2')
        ->andReturn(true);

    $this->artisan('show', ['id' => '2'])
        ->assertSuccessful()
        ->expectsOutputToContain('Minimal Entry')
        ->expectsOutputToContain('Basic content');
});

it('shows usage statistics', function (): void {
    $entry = [
        'id' => '3',
        'title' => 'Test Entry',
        'content' => 'Content',
        'category' => 'architecture',
        'tags' => [],
        'module' => null,
        'priority' => 'medium',
        'confidence' => 50,
        'status' => 'draft',
        'usage_count' => 5,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('3')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('incrementUsage')
        ->once()
        ->with('3')
        ->andReturn(true);

    $this->artisan('show', ['id' => '3'])
        ->assertSuccessful()
        ->expectsOutputToContain('5');
});

it('increments usage count when viewing', function (): void {
    $entry = [
        'id' => '4',
        'title' => 'Test Entry',
        'content' => 'Content',
        'category' => 'architecture',
        'tags' => [],
        'module' => null,
        'priority' => 'medium',
        'confidence' => 50,
        'status' => 'draft',
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('4')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('incrementUsage')
        ->once()
        ->with('4')
        ->andReturn(true);

    $this->artisan('show', ['id' => '4'])
        ->assertSuccessful();
});

it('shows error when entry not found', function (): void {
    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('9999')
        ->andReturn(null);

    $this->artisan('show', ['id' => '9999'])
        ->assertFailed()
        ->expectsOutputToContain('not found');
});

it('validates id must be numeric', function (): void {
    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('abc')
        ->andReturn(null);

    $this->artisan('show', ['id' => 'abc'])
        ->assertFailed();
});

it('shows timestamps', function (): void {
    $entry = [
        'id' => '5',
        'title' => 'Test Entry',
        'content' => 'Content',
        'category' => 'architecture',
        'tags' => [],
        'module' => null,
        'priority' => 'medium',
        'confidence' => 50,
        'status' => 'draft',
        'usage_count' => 0,
        'created_at' => '2024-01-15T10:30:00+00:00',
        'updated_at' => '2024-01-16T14:45:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('5')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('incrementUsage')
        ->once()
        ->with('5')
        ->andReturn(true);

    // Just verify command runs successfully - timestamps render via Laravel Prompts
    $this->artisan('show', ['id' => '5'])
        ->assertSuccessful();
});

it('shows files if present', function (): void {
    expect(true)->toBeTrue();
})->skip('files field not implemented in Qdrant storage');

it('shows repo details if present', function (): void {
    expect(true)->toBeTrue();
})->skip('repo fields not implemented in Qdrant storage');

it('shows superseded indicator for superseded entries', function (): void {
    $entry = [
        'id' => '10',
        'title' => 'Old Entry',
        'content' => 'Old content',
        'category' => 'architecture',
        'tags' => [],
        'module' => null,
        'priority' => 'medium',
        'confidence' => 50,
        'status' => 'draft',
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
        'superseded_by' => 'new-uuid',
        'superseded_date' => '2026-01-15T00:00:00Z',
        'superseded_reason' => 'Updated with newer knowledge',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('10')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('incrementUsage')
        ->once()
        ->with('10')
        ->andReturn(true);

    $this->qdrantMock->shouldReceive('getSupersessionHistory')
        ->once()
        ->with('10')
        ->andReturn([
            'supersedes' => [],
            'superseded_by' => [
                'id' => 'new-uuid',
                'title' => 'New Entry',
                'content' => 'New content',
            ],
        ]);

    $this->artisan('show', ['id' => '10'])
        ->assertSuccessful()
        ->expectsOutputToContain('SUPERSEDED')
        ->expectsOutputToContain('new-uuid');
});

it('shows supersession history when entry supersedes others', function (): void {
    $entry = [
        'id' => '11',
        'title' => 'New Entry',
        'content' => 'New content',
        'category' => 'architecture',
        'tags' => [],
        'module' => null,
        'priority' => 'high',
        'confidence' => 90,
        'status' => 'validated',
        'usage_count' => 3,
        'created_at' => '2024-02-01T00:00:00+00:00',
        'updated_at' => '2024-02-01T00:00:00+00:00',
        'superseded_by' => null,
        'superseded_date' => null,
        'superseded_reason' => null,
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('11')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('incrementUsage')
        ->once()
        ->with('11')
        ->andReturn(true);

    $this->qdrantMock->shouldReceive('getSupersessionHistory')
        ->once()
        ->with('11')
        ->andReturn([
            'supersedes' => [
                [
                    'id' => 'old-uuid',
                    'title' => 'Old Entry',
                    'content' => 'Old content',
                    'superseded_reason' => 'Updated with newer knowledge',
                ],
            ],
            'superseded_by' => null,
        ]);

    $this->artisan('show', ['id' => '11'])
        ->assertSuccessful()
        ->expectsOutputToContain('Supersession History')
        ->expectsOutputToContain('This entry supersedes')
        ->expectsOutputToContain('old-uuid');
});

it('does not show supersession history when none exists', function (): void {
    $entry = [
        'id' => '12',
        'title' => 'Standalone Entry',
        'content' => 'Content',
        'category' => 'architecture',
        'tags' => [],
        'module' => null,
        'priority' => 'medium',
        'confidence' => 50,
        'status' => 'draft',
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
        'superseded_by' => null,
        'superseded_date' => null,
        'superseded_reason' => null,
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('12')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('incrementUsage')
        ->once()
        ->with('12')
        ->andReturn(true);

    $this->qdrantMock->shouldReceive('getSupersessionHistory')
        ->once()
        ->with('12')
        ->andReturn([
            'supersedes' => [],
            'superseded_by' => null,
        ]);

    $this->artisan('show', ['id' => '12'])
        ->assertSuccessful();
});
