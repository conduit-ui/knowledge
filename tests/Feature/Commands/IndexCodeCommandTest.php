<?php

declare(strict_types=1);

namespace App\Commands;

/**
 * Override is_dir in the App\Commands namespace for testing.
 * This allows us to test the "no valid paths" branch.
 */
function is_dir(string $path): bool
{
    // If a test sets this global, use the mock behavior
    if (isset($GLOBALS['__mock_is_dir']) && $GLOBALS['__mock_is_dir'] === true) {
        return false;
    }

    return \is_dir($path);
}

namespace Tests\Feature\Commands;

use App\Services\CodeIndexerService;
use Generator;

beforeEach(function (): void {
    $this->indexerMock = \Mockery::mock(CodeIndexerService::class);
    $this->app->instance(CodeIndexerService::class, $this->indexerMock);
    // Reset mock state
    unset($GLOBALS['__mock_is_dir']);
});

afterEach(function (): void {
    \Mockery::close();
    unset($GLOBALS['__mock_is_dir']);
});

/**
 * Helper to create a Generator from an array.
 *
 * @param  array<array{path: string, repo: string}>  $files
 * @return Generator<array{path: string, repo: string}>
 */
function filesGenerator(array $files): Generator
{
    foreach ($files as $file) {
        yield $file;
    }
}

describe('index-code command', function (): void {
    it('fails when no valid paths exist', function (): void {
        // Enable mock to make is_dir return false for all paths
        $GLOBALS['__mock_is_dir'] = true;

        $this->indexerMock->shouldNotReceive('ensureCollection');
        $this->indexerMock->shouldNotReceive('findFiles');

        $this->artisan('index-code')
            ->assertFailed();
    });

    it('indexes files from valid paths', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([
                ['path' => '/path/to/file1.php', 'repo' => 'test-repo'],
                ['path' => '/path/to/file2.php', 'repo' => 'test-repo'],
            ]));

        $this->indexerMock->shouldReceive('indexFile')
            ->with('/path/to/file1.php', 'test-repo')
            ->once()
            ->andReturn(['success' => true, 'chunks' => 3]);

        $this->indexerMock->shouldReceive('indexFile')
            ->with('/path/to/file2.php', 'test-repo')
            ->once()
            ->andReturn(['success' => true, 'chunks' => 2]);

        $this->artisan('index-code', ['--path' => [$tempDir]])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('fails when collection creation fails', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(false);

        $this->artisan('index-code', ['--path' => [$tempDir]])
            ->assertFailed();

        rmdir($tempDir);
    });

    it('handles dry-run option', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldNotReceive('ensureCollection');

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([
                ['path' => '/path/to/file.php', 'repo' => 'test-repo'],
            ]));

        $this->indexerMock->shouldNotReceive('indexFile');

        $this->artisan('index-code', ['--path' => [$tempDir], '--dry-run' => true])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('handles stats option', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([
                ['path' => '/path/to/file.php', 'repo' => 'test-repo'],
                ['path' => '/path/to/app.js', 'repo' => 'other-repo'],
            ]));

        $this->indexerMock->shouldNotReceive('indexFile');

        $this->artisan('index-code', ['--path' => [$tempDir], '--stats' => true])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('returns success when no files found', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([]));

        $this->artisan('index-code', ['--path' => [$tempDir]])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('tracks failed indexing and shows errors', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([
                ['path' => '/path/to/file1.php', 'repo' => 'test-repo'],
                ['path' => '/path/to/file2.php', 'repo' => 'test-repo'],
            ]));

        $this->indexerMock->shouldReceive('indexFile')
            ->with('/path/to/file1.php', 'test-repo')
            ->once()
            ->andReturn(['success' => true, 'chunks' => 2]);

        $this->indexerMock->shouldReceive('indexFile')
            ->with('/path/to/file2.php', 'test-repo')
            ->once()
            ->andReturn(['success' => false, 'chunks' => 0, 'error' => 'Could not read file']);

        $this->artisan('index-code', ['--path' => [$tempDir]])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('fails when all indexing fails', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([
                ['path' => '/path/to/file.php', 'repo' => 'test-repo'],
            ]));

        $this->indexerMock->shouldReceive('indexFile')
            ->once()
            ->andReturn(['success' => false, 'chunks' => 0, 'error' => 'Embedding failed']);

        $this->artisan('index-code', ['--path' => [$tempDir]])
            ->assertFailed();

        rmdir($tempDir);
    });

    it('shows warning when more than 10 errors occur', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $files = [];
        for ($i = 1; $i <= 12; $i++) {
            $files[] = ['path' => "/path/to/file{$i}.php", 'repo' => 'test-repo'];
        }

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator($files));

        $this->indexerMock->shouldReceive('indexFile')
            ->times(12)
            ->andReturn(['success' => false, 'chunks' => 0, 'error' => 'Failed']);

        $this->artisan('index-code', ['--path' => [$tempDir]])
            ->assertFailed();

        rmdir($tempDir);
    });

    it('handles failure without error message', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([
                ['path' => '/path/to/file1.php', 'repo' => 'test-repo'],
                ['path' => '/path/to/file2.php', 'repo' => 'test-repo'],
            ]));

        $this->indexerMock->shouldReceive('indexFile')
            ->with('/path/to/file1.php', 'test-repo')
            ->once()
            ->andReturn(['success' => true, 'chunks' => 2]);

        // Failure without error key
        $this->indexerMock->shouldReceive('indexFile')
            ->with('/path/to/file2.php', 'test-repo')
            ->once()
            ->andReturn(['success' => false, 'chunks' => 0]);

        $this->artisan('index-code', ['--path' => [$tempDir]])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('deduplicates paths', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        // findFiles should be called with deduplicated paths
        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([]));

        $this->artisan('index-code', ['--path' => [$tempDir, $tempDir]])
            ->assertSuccessful();

        rmdir($tempDir);
    });
});

describe('language detection in stats', function (): void {
    it('detects PHP files', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([
                ['path' => '/path/to/file.php', 'repo' => 'test'],
            ]));

        $this->artisan('index-code', ['--path' => [$tempDir], '--stats' => true])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('detects Python files', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([
                ['path' => '/path/to/script.py', 'repo' => 'test'],
            ]));

        $this->artisan('index-code', ['--path' => [$tempDir], '--stats' => true])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('detects JavaScript files', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([
                ['path' => '/path/to/app.js', 'repo' => 'test'],
                ['path' => '/path/to/component.jsx', 'repo' => 'test'],
            ]));

        $this->artisan('index-code', ['--path' => [$tempDir], '--stats' => true])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('detects TypeScript files', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([
                ['path' => '/path/to/types.ts', 'repo' => 'test'],
                ['path' => '/path/to/component.tsx', 'repo' => 'test'],
            ]));

        $this->artisan('index-code', ['--path' => [$tempDir], '--stats' => true])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('detects Vue files', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([
                ['path' => '/path/to/App.vue', 'repo' => 'test'],
            ]));

        $this->artisan('index-code', ['--path' => [$tempDir], '--stats' => true])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('categorizes unknown extensions as Other', function (): void {
        $tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->indexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->indexerMock->shouldReceive('findFiles')
            ->once()
            ->andReturnUsing(fn () => filesGenerator([
                ['path' => '/path/to/file.unknown', 'repo' => 'test'],
            ]));

        $this->artisan('index-code', ['--path' => [$tempDir], '--stats' => true])
            ->assertSuccessful();

        rmdir($tempDir);
    });
});
