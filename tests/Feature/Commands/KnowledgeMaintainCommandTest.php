<?php

declare(strict_types=1);

use App\Services\QdrantService;

beforeEach(function (): void {
    $this->qdrantMock = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->qdrantMock);
});

it('shows message when no entries exist', function (): void {
    $this->qdrantMock->shouldReceive('scroll')
        ->once()
        ->with([], 50)
        ->andReturn(collect([]));

    $this->artisan('maintain')
        ->assertSuccessful();
});

it('shows message when no stale entries found', function (): void {
    $this->qdrantMock->shouldReceive('scroll')
        ->once()
        ->andReturn(collect([
            [
                'id' => 'fresh-1',
                'title' => 'Fresh Entry',
                'content' => 'Content',
                'tags' => [],
                'category' => null,
                'module' => null,
                'priority' => 'medium',
                'status' => 'validated',
                'confidence' => 80,
                'usage_count' => 5,
                'created_at' => now()->subDays(10)->toIso8601String(),
                'updated_at' => now()->subDays(5)->toIso8601String(),
                'last_verified' => now()->subDays(10)->toIso8601String(),
                'evidence' => null,
            ],
        ]));

    $this->artisan('maintain')
        ->assertSuccessful();
});

it('surfaces stale entries that need review', function (): void {
    $this->qdrantMock->shouldReceive('scroll')
        ->once()
        ->andReturn(collect([
            [
                'id' => 'stale-1',
                'title' => 'Old Entry',
                'content' => 'Content',
                'tags' => [],
                'category' => 'debugging',
                'module' => null,
                'priority' => 'high',
                'status' => 'validated',
                'confidence' => 70,
                'usage_count' => 10,
                'created_at' => now()->subDays(200)->toIso8601String(),
                'updated_at' => now()->subDays(100)->toIso8601String(),
                'last_verified' => now()->subDays(100)->toIso8601String(),
                'evidence' => null,
            ],
            [
                'id' => 'fresh-1',
                'title' => 'Fresh Entry',
                'content' => 'Content',
                'tags' => [],
                'category' => null,
                'module' => null,
                'priority' => 'medium',
                'status' => 'validated',
                'confidence' => 80,
                'usage_count' => 5,
                'created_at' => now()->subDays(10)->toIso8601String(),
                'updated_at' => now()->subDays(5)->toIso8601String(),
                'last_verified' => now()->subDays(10)->toIso8601String(),
                'evidence' => null,
            ],
        ]));

    $this->artisan('maintain')
        ->assertSuccessful()
        ->expectsOutputToContain('stale');
});

it('shows entries with no last_verified as stale', function (): void {
    $this->qdrantMock->shouldReceive('scroll')
        ->once()
        ->andReturn(collect([
            [
                'id' => 'never-verified',
                'title' => 'Never Verified Entry',
                'content' => 'Content',
                'tags' => [],
                'category' => null,
                'module' => null,
                'priority' => 'medium',
                'status' => 'draft',
                'confidence' => 50,
                'usage_count' => 0,
                'created_at' => now()->subDays(100)->toIso8601String(),
                'updated_at' => now()->subDays(100)->toIso8601String(),
                'last_verified' => null,
                'evidence' => null,
            ],
        ]));

    $this->artisan('maintain')
        ->assertSuccessful()
        ->expectsOutputToContain('stale');
});

it('respects limit option', function (): void {
    $this->qdrantMock->shouldReceive('scroll')
        ->once()
        ->with([], 10)
        ->andReturn(collect([]));

    $this->artisan('maintain', ['--limit' => '10'])
        ->assertSuccessful();
});

it('shows multiple stale entries', function (): void {
    $this->qdrantMock->shouldReceive('scroll')
        ->once()
        ->andReturn(collect([
            [
                'id' => 'stale-1',
                'title' => 'Stale Entry One',
                'content' => 'Content',
                'tags' => [],
                'category' => null,
                'module' => null,
                'priority' => 'medium',
                'status' => 'draft',
                'confidence' => 50,
                'usage_count' => 0,
                'created_at' => now()->subDays(120)->toIso8601String(),
                'updated_at' => now()->subDays(120)->toIso8601String(),
                'last_verified' => now()->subDays(120)->toIso8601String(),
                'evidence' => null,
            ],
            [
                'id' => 'stale-2',
                'title' => 'Stale Entry Two',
                'content' => 'Content',
                'tags' => [],
                'category' => null,
                'module' => null,
                'priority' => 'high',
                'status' => 'validated',
                'confidence' => 80,
                'usage_count' => 3,
                'created_at' => now()->subDays(200)->toIso8601String(),
                'updated_at' => now()->subDays(200)->toIso8601String(),
                'last_verified' => now()->subDays(200)->toIso8601String(),
                'evidence' => null,
            ],
        ]));

    $this->artisan('maintain')
        ->assertSuccessful()
        ->expectsOutputToContain('2 stale entries');
});

it('shows help text for next steps', function (): void {
    $this->qdrantMock->shouldReceive('scroll')
        ->once()
        ->andReturn(collect([
            [
                'id' => 'stale-help',
                'title' => 'Help Entry',
                'content' => 'Content',
                'tags' => [],
                'category' => null,
                'module' => null,
                'priority' => 'medium',
                'status' => 'draft',
                'confidence' => 50,
                'usage_count' => 0,
                'created_at' => now()->subDays(120)->toIso8601String(),
                'updated_at' => now()->subDays(120)->toIso8601String(),
                'last_verified' => now()->subDays(120)->toIso8601String(),
                'evidence' => null,
            ],
        ]));

    $this->artisan('maintain')
        ->assertSuccessful()
        ->expectsOutputToContain('validate');
});
