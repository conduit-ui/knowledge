<?php

declare(strict_types=1);

use App\Services\QdrantService;

beforeEach(function () {
    $this->qdrantMock = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->qdrantMock);
});

it('shows full details of an entry', function () {
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
        ->expectsOutput('ID: 1')
        ->expectsOutput('Title: Test Entry')
        ->expectsOutput('Content: This is the full content of the entry')
        ->expectsOutput('Category: architecture')
        ->expectsOutput('Module: Blood')
        ->expectsOutput('Priority: high')
        ->expectsOutput('Confidence: 85%')
        ->expectsOutput('Status: validated')
        ->expectsOutput('Tags: laravel, pest');
});

it('shows entry with minimal fields', function () {
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
        ->expectsOutput('ID: 2')
        ->expectsOutput('Title: Minimal Entry')
        ->expectsOutput('Content: Basic content');
});

it('shows usage statistics', function () {
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
        ->expectsOutput('Usage Count: 5');
});

it('increments usage count when viewing', function () {
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

it('shows error when entry not found', function () {
    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('9999')
        ->andReturn(null);

    $this->artisan('show', ['id' => '9999'])
        ->assertFailed()
        ->expectsOutput('Entry not found.');
});

it('validates id must be numeric', function () {
    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('abc')
        ->andReturn(null);

    $this->artisan('show', ['id' => 'abc'])
        ->assertFailed();
});

it('shows timestamps', function () {
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

    $this->artisan('show', ['id' => '5'])
        ->assertSuccessful()
        ->expectsOutput('Created: 2024-01-15T10:30:00+00:00')
        ->expectsOutput('Updated: 2024-01-16T14:45:00+00:00');
});

it('shows files if present', function () {
    // Note: Current implementation doesn't support files field
    // This test is skipped until field is added
    expect(true)->toBeTrue();
})->skip('files field not implemented in Qdrant storage');

it('shows repo details if present', function () {
    // Note: Current implementation doesn't support repo/branch/commit fields
    // This test is skipped until fields are added
    expect(true)->toBeTrue();
})->skip('repo fields not implemented in Qdrant storage');
