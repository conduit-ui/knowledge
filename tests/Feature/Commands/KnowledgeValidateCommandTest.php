<?php

declare(strict_types=1);

use App\Services\QdrantService;

beforeEach(function (): void {
    $this->qdrantMock = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->qdrantMock);
});

it('validates an entry and boosts confidence', function (): void {
    $entry = [
        'id' => '1',
        'title' => 'Test Entry',
        'content' => 'Content',
        'confidence' => 60,
        'status' => 'draft',
        'category' => null,
        'tags' => [],
        'module' => null,
        'priority' => 'medium',
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('1')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('updateFields')
        ->once()
        ->with('1', Mockery::on(fn (array $fields): bool => $fields['status'] === 'validated'
            && $fields['confidence'] === 80
            && isset($fields['last_verified'])))
        ->andReturn(true);

    $this->artisan('validate', ['id' => '1'])
        ->assertSuccessful()
        ->expectsOutput('Entry #1 validated successfully!')
        ->expectsOutput('Title: Test Entry')
        ->expectsOutput('Status: draft -> validated')
        ->expectsOutput('Confidence: 60% -> 80%');
});

it('shows error when entry not found', function (): void {
    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('9999')
        ->andReturn(null);

    $this->artisan('validate', ['id' => '9999'])
        ->assertFailed()
        ->expectsOutput('Entry not found with ID: 9999');
});

it('validates id must be numeric', function (): void {
    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('abc')
        ->andReturn(null);

    $this->artisan('validate', ['id' => 'abc'])
        ->assertFailed();
});

it('validates entry that is already validated', function (): void {
    $entry = [
        'id' => '2',
        'title' => 'Already Validated',
        'content' => 'Content',
        'confidence' => 90,
        'status' => 'validated',
        'category' => null,
        'tags' => [],
        'module' => null,
        'priority' => 'medium',
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('2')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('updateFields')
        ->once()
        ->with('2', Mockery::on(fn (array $fields): bool => $fields['status'] === 'validated'
            && $fields['confidence'] === 100
            && isset($fields['last_verified'])))
        ->andReturn(true);

    $this->artisan('validate', ['id' => '2'])
        ->assertSuccessful();
});

it('displays validation date after validation', function (): void {
    $entry = [
        'id' => '3',
        'title' => 'Test Entry',
        'content' => 'Content',
        'confidence' => 70,
        'status' => 'draft',
        'category' => null,
        'tags' => [],
        'module' => null,
        'priority' => 'medium',
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('3')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('updateFields')
        ->once()
        ->with('3', Mockery::on(fn (array $fields): bool => $fields['status'] === 'validated'
            && $fields['confidence'] === 90
            && isset($fields['last_verified'])))
        ->andReturn(true);

    $this->artisan('validate', ['id' => '3'])
        ->assertSuccessful();
});

it('validates entry with high confidence', function (): void {
    $entry = [
        'id' => '4',
        'title' => 'High Confidence Entry',
        'content' => 'Content',
        'confidence' => 95,
        'status' => 'draft',
        'category' => null,
        'tags' => [],
        'module' => null,
        'priority' => 'medium',
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('4')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('updateFields')
        ->once()
        ->with('4', Mockery::on(fn (array $fields): bool => $fields['status'] === 'validated'
            && $fields['confidence'] === 100
            && isset($fields['last_verified'])))
        ->andReturn(true);

    $this->artisan('validate', ['id' => '4'])
        ->assertSuccessful()
        ->expectsOutput('Confidence: 95% -> 100%');
});

it('validates entry with low confidence', function (): void {
    $entry = [
        'id' => '5',
        'title' => 'Low Confidence Entry',
        'content' => 'Content',
        'confidence' => 10,
        'status' => 'draft',
        'category' => null,
        'tags' => [],
        'module' => null,
        'priority' => 'medium',
        'usage_count' => 0,
        'created_at' => '2024-01-01T00:00:00+00:00',
        'updated_at' => '2024-01-01T00:00:00+00:00',
    ];

    $this->qdrantMock->shouldReceive('getById')
        ->once()
        ->with('5')
        ->andReturn($entry);

    $this->qdrantMock->shouldReceive('updateFields')
        ->once()
        ->with('5', Mockery::on(fn (array $fields): bool => $fields['status'] === 'validated'
            && $fields['confidence'] === 30
            && isset($fields['last_verified'])))
        ->andReturn(true);

    $this->artisan('validate', ['id' => '5'])
        ->assertSuccessful()
        ->expectsOutput('Confidence: 10% -> 30%');
});
