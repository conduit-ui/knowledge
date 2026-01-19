<?php

declare(strict_types=1);

use App\Services\QdrantService;

beforeEach(function () {
    $this->qdrantMock = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->qdrantMock);
});

it('updates entry title', function () {
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
        ->with('test-id-123')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(function ($updatedEntry) {
            return $updatedEntry['title'] === 'New Title'
                && $updatedEntry['id'] === 'test-id-123';
        })
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-123',
        '--title' => 'New Title',
    ])->assertSuccessful();
});

it('updates entry content', function () {
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
        ->with('test-id-456')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(function ($updatedEntry) {
            return $updatedEntry['content'] === 'Updated content here';
        })
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-456',
        '--content' => 'Updated content here',
    ])->assertSuccessful();
});

it('updates entry tags by replacing them', function () {
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
        ->with('test-id-789')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(function ($updatedEntry) {
            return $updatedEntry['tags'] === ['new-tag1', 'new-tag2'];
        })
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-789',
        '--tags' => 'new-tag1, new-tag2',
    ])->assertSuccessful();
});

it('adds tags to existing tags', function () {
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
        ->with('test-id-add')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(function ($updatedEntry) {
            return in_array('existing-tag', $updatedEntry['tags'], true)
                && in_array('new-tag', $updatedEntry['tags'], true);
        })
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-add',
        '--add-tags' => 'new-tag',
    ])->assertSuccessful();
});

it('updates confidence level', function () {
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
        ->with('test-id-conf')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(function ($updatedEntry) {
            return $updatedEntry['confidence'] === 85;
        })
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-conf',
        '--confidence' => '85',
    ])->assertSuccessful();
});

it('fails for invalid confidence', function () {
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
        ->with('test-id-invalid')
        ->andReturn($entry);

    $this->artisan('update', [
        'id' => 'test-id-invalid',
        '--confidence' => '150',
    ])->assertFailed();
});

it('fails for invalid category', function () {
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
        ->with('test-id-cat')
        ->andReturn($entry);

    $this->artisan('update', [
        'id' => 'test-id-cat',
        '--category' => 'invalid-category',
    ])->assertFailed();
});

it('fails for invalid priority', function () {
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
        ->with('test-id-pri')
        ->andReturn($entry);

    $this->artisan('update', [
        'id' => 'test-id-pri',
        '--priority' => 'super-high',
    ])->assertFailed();
});

it('fails for invalid status', function () {
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
        ->with('test-id-stat')
        ->andReturn($entry);

    $this->artisan('update', [
        'id' => 'test-id-stat',
        '--status' => 'invalid-status',
    ])->assertFailed();
});

it('fails when entry not found', function () {
    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('non-existent-id')
        ->andReturn(null);

    $this->artisan('update', [
        'id' => 'non-existent-id',
        '--title' => 'New Title',
    ])->assertFailed();
});

it('fails when no updates provided', function () {
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
        ->with('test-id-empty')
        ->andReturn($entry);

    $this->artisan('update', [
        'id' => 'test-id-empty',
    ])->assertFailed();
});

it('updates multiple fields at once', function () {
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
        ->with('test-id-multi')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(function ($updatedEntry) {
            return $updatedEntry['title'] === 'New Title'
                && $updatedEntry['priority'] === 'high'
                && $updatedEntry['status'] === 'validated'
                && $updatedEntry['confidence'] === 90;
        })
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-multi',
        '--title' => 'New Title',
        '--priority' => 'high',
        '--status' => 'validated',
        '--confidence' => '90',
    ])->assertSuccessful();
});

it('updates category to valid value', function () {
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
        ->with('test-id-category')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(function ($updatedEntry) {
            return $updatedEntry['category'] === 'debugging';
        })
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-category',
        '--category' => 'debugging',
    ])->assertSuccessful();
});

it('updates timestamp on save', function () {
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
        ->with('test-id-time')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('upsert')
        ->once()
        ->withArgs(function ($updatedEntry) {
            return $updatedEntry['updated_at'] !== '2024-01-01T00:00:00+00:00';
        })
        ->andReturn(true);

    $this->artisan('update', [
        'id' => 'test-id-time',
        '--title' => 'Updated',
    ])->assertSuccessful();
});
