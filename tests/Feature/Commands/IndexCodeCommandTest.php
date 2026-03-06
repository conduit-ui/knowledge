<?php

declare(strict_types=1);

use App\Services\SymbolIndexService;

beforeEach(function (): void {
    $this->indexerMock = Mockery::mock(SymbolIndexService::class);
    $this->app->instance(SymbolIndexService::class, $this->indexerMock);
});

afterEach(function (): void {
    Mockery::close();
});

describe('index-code command', function (): void {
    it('indexes a folder successfully', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('indexFolder')
            ->once()
            ->with($tempDir, false)
            ->andReturn([
                'success' => true,
                'repo' => 'local/test',
                'file_count' => 10,
                'symbol_count' => 50,
                'languages' => ['php' => 10],
            ]);

        $this->artisan('index-code', ['path' => $tempDir])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('supports incremental indexing', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('indexFolder')
            ->once()
            ->with($tempDir, true)
            ->andReturn([
                'success' => true,
                'repo' => 'local/test',
                'incremental' => true,
                'changed' => 2,
                'new' => 1,
                'deleted' => 0,
                'symbol_count' => 15,
            ]);

        $this->artisan('index-code', ['path' => $tempDir, '--incremental' => true])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('handles no changes in incremental mode', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('indexFolder')
            ->once()
            ->andReturn([
                'success' => true,
                'message' => 'No changes detected',
            ]);

        $this->artisan('index-code', ['path' => $tempDir, '--incremental' => true])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('fails when indexing fails', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('indexFolder')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'No source files found',
            ]);

        $this->artisan('index-code', ['path' => $tempDir])
            ->assertFailed();

        rmdir($tempDir);
    });

    it('fails with invalid path', function (): void {
        $this->indexerMock->shouldNotReceive('indexFolder');

        $this->artisan('index-code', ['path' => '/nonexistent/path'])
            ->assertFailed();
    });

    it('defaults to current directory when no path given', function (): void {
        $this->indexerMock->shouldReceive('indexFolder')
            ->once()
            ->withArgs(function (string $path, bool $incremental): bool {
                return is_dir($path) && ! $incremental;
            })
            ->andReturn([
                'success' => true,
                'repo' => 'local/knowledge',
                'file_count' => 5,
                'symbol_count' => 25,
                'languages' => ['php' => 5],
            ]);

        $this->artisan('index-code')
            ->assertSuccessful();
    });

    it('shows warnings when present', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('indexFolder')
            ->once()
            ->andReturn([
                'success' => true,
                'repo' => 'local/test',
                'file_count' => 8,
                'symbol_count' => 40,
                'languages' => ['php' => 8],
                'warnings' => ['Skipped secret file: .env'],
            ]);

        $this->artisan('index-code', ['path' => $tempDir])
            ->assertSuccessful();

        rmdir($tempDir);
    });
});

describe('--list option', function (): void {
    it('lists indexed repositories', function (): void {
        $this->indexerMock->shouldReceive('listRepos')
            ->once()
            ->andReturn([
                [
                    'repo' => 'local/knowledge',
                    'file_count' => 96,
                    'symbol_count' => 508,
                    'languages' => ['php' => 95, 'python' => 1],
                    'indexed_at' => '2026-03-05T12:00:00',
                ],
            ]);

        $this->artisan('index-code', ['--list' => true])
            ->assertSuccessful();
    });

    it('shows message when no repos indexed', function (): void {
        $this->indexerMock->shouldReceive('listRepos')
            ->once()
            ->andReturn([]);

        $this->artisan('index-code', ['--list' => true])
            ->assertSuccessful();
    });
});
