<?php

declare(strict_types=1);

use App\Services\QdrantService;

beforeEach(function (): void {
    $this->qdrantMock = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->qdrantMock);
    mockProjectDetector();
});

it('updates entry title', function (): void {
    $entry = [
        'id' => 'test-id-123',
        'title' => 'Original Title',
        'content' => 'Original content',
        'tags' => ['tag1'],
        'category' => 'architecture',
        'module' => null,
        'priority' => 'medium',
        'status' => 'draft',
        'confidence' => 50,
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('test-id-123', 'default')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(fn ($updatedEntry): bool => $updatedEntry['title'] === 'New Title'
            && $updatedEntry['id'] === 'test-id-123')
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-123',
        '--title' => 'New Title',
    ])->assertSuccessful();
});

it('updates entry content', function (): void {
    $entry = [
        'id' => 'test-id-456',
        'title' => 'Test Entry',
        'content' => 'Original content',
        'tags' => [],
        'category' => 'architecture',
        'module' => null,
        'priority' => 'medium',
        'status' => 'draft',
        'confidence' => 50,
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('test-id-456', 'default')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(fn ($updatedEntry): bool => $updatedEntry['content'] === 'Updated content here')
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-456',
        '--content' => 'Updated content here',
    ])->assertSuccessful();
});

it('updates entry tags by replacing them', function (): void {
    $entry = [
        'id' => 'test-id-789',
        'title' => 'Test Entry',
        'content' => 'Content',
        'tags' => ['old-tag1', 'old-tag2'],
        'category' => 'architecture',
        'module' => null,
        'priority' => 'medium',
        'status' => 'draft',
        'confidence' => 50,
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('test-id-789', 'default')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(fn ($updatedEntry): bool => $updatedEntry['tags'] === ['new-tag1', 'new-tag2'])
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-789',
        '--tags' => 'new-tag1, new-tag2',
    ])->assertSuccessful();
});

it('adds tags to existing tags', function (): void {
    $entry = [
        'id' => 'test-id-add',
        'title' => 'Test Entry',
        'content' => 'Content',
        'tags' => ['existing-tag'],
        'category' => 'architecture',
        'module' => null,
        'priority' => 'medium',
        'status' => 'draft',
        'confidence' => 50,
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('test-id-add', 'default')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(fn ($updatedEntry): bool => in_array('existing-tag', $updatedEntry['tags'], true)
            && in_array('new-tag', $updatedEntry['tags'], true))
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-add',
        '--add-tags' => 'new-tag',
    ])->assertSuccessful();
});

it('updates confidence level', function (): void {
    $entry = [
        'id' => 'test-id-conf',
        'title' => 'Test Entry',
        'content' => 'Content',
        'tags' => [],
        'category' => 'architecture',
        'module' => null,
        'priority' => 'medium',
        'status' => 'draft',
        'confidence' => 50,
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('test-id-conf', 'default')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(fn ($updatedEntry): bool => $updatedEntry['confidence'] === 85)
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-conf',
        '--confidence' => '85',
    ])->assertSuccessful();
});

it('fails for invalid confidence', function (): void {
    $entry = [
        'id' => 'test-id-invalid',
        'title' => 'Test Entry',
        'content' => 'Content',
        'tags' => [],
        'category' => 'architecture',
        'module' => null,
        'priority' => 'medium',
        'status' => 'draft',
        'confidence' => 50,
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('test-id-invalid', 'default')
        ->andReturn($entry);

    $this->artisan('update', [
        'id' => 'test-id-invalid',
        '--confidence' => '150',
    ])->assertFailed();
});

it('fails for invalid category', function (): void {
    $entry = [
        'id' => 'test-id-cat',
        'title' => 'Test Entry',
        'content' => 'Content',
        'tags' => [],
        'category' => 'architecture',
        'module' => null,
        'priority' => 'medium',
        'status' => 'draft',
        'confidence' => 50,
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('test-id-cat', 'default')
        ->andReturn($entry);

    $this->artisan('update', [
        'id' => 'test-id-cat',
        '--category' => 'invalid-category',
    ])->assertFailed();
});

it('fails for invalid priority', function (): void {
    $entry = [
        'id' => 'test-id-pri',
        'title' => 'Test Entry',
        'content' => 'Content',
        'tags' => [],
        'category' => 'architecture',
        'module' => null,
        'priority' => 'medium',
        'status' => 'draft',
        'confidence' => 50,
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('test-id-pri', 'default')
        ->andReturn($entry);

    $this->artisan('update', [
        'id' => 'test-id-pri',
        '--priority' => 'super-high',
    ])->assertFailed();
});

it('fails for invalid status', function (): void {
    $entry = [
        'id' => 'test-id-stat',
        'title' => 'Test Entry',
        'content' => 'Content',
        'tags' => [],
        'category' => 'architecture',
        'module' => null,
        'priority' => 'medium',
        'status' => 'draft',
        'confidence' => 50,
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('test-id-stat', 'default')
        ->andReturn($entry);

    $this->artisan('update', [
        'id' => 'test-id-stat',
        '--status' => 'invalid-status',
    ])->assertFailed();
});

it('fails when entry not found', function (): void {
    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('non-existent-id', 'default')
        ->andReturn(null);

    $this->artisan('update', [
        'id' => 'non-existent-id',
        '--title' => 'New Title',
    ])->assertFailed();
});

it('fails when no updates provided', function (): void {
    $entry = [
        'id' => 'test-id-empty',
        'title' => 'Test Entry',
        'content' => 'Content',
        'tags' => [],
        'category' => 'architecture',
        'module' => null,
        'priority' => 'medium',
        'status' => 'draft',
        'confidence' => 50,
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('test-id-empty', 'default')
        ->andReturn($entry);

    $this->artisan('update', [
        'id' => 'test-id-empty',
    ])->assertFailed();
});

it('updates multiple fields at once', function (): void {
    $entry = [
        'id' => 'test-id-multi',
        'title' => 'Original Title',
        'content' => 'Original content',
        'tags' => ['old'],
        'category' => 'architecture',
        'module' => null,
        'priority' => 'low',
        'status' => 'draft',
        'confidence' => 30,
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('test-id-multi', 'default')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(fn ($updatedEntry): bool => $updatedEntry['title'] === 'New Title'
            && $updatedEntry['priority'] === 'high'
            && $updatedEntry['status'] === 'validated'
            && $updatedEntry['confidence'] === 90)
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-multi',
        '--title' => 'New Title',
        '--priority' => 'high',
        '--status' => 'validated',
        '--confidence' => '90',
    ])->assertSuccessful();
});

it('updates category to valid value', function (): void {
    $entry = [
        'id' => 'test-id-category',
        'title' => 'Test Entry',
        'content' => 'Content',
        'tags' => [],
        'category' => 'architecture',
        'module' => null,
        'priority' => 'medium',
        'status' => 'draft',
        'confidence' => 50,
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('test-id-category', 'default')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(fn ($updatedEntry): bool => $updatedEntry['category'] === 'debugging')
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-category',
        '--category' => 'debugging',
    ])->assertSuccessful();
});

it('updates timestamp on save', function (): void {
    $entry = [
        'id' => 'test-id-time',
        'title' => 'Test Entry',
        'content' => 'Content',
        'tags' => [],
        'category' => 'architecture',
        'module' => null,
        'priority' => 'medium',
        'status' => 'draft',
        'confidence' => 50,
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('test-id-time', 'default')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(fn ($updatedEntry): bool => $updatedEntry['updated_at'] !== '2024-01-01T00:00:00+00:00')
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-time',
        '--title' => 'Updated',
    ])->assertSuccessful();
});
