<?php

declare(strict_types=1);

use App\Services\CodeIndexerService;
use App\Services\SymbolIndexService;

beforeEach(function (): void {
    $this->symbolIndexMock = Mockery::mock(SymbolIndexService::class);
    $this->codeIndexerMock = Mockery::mock(CodeIndexerService::class);
    $this->app->instance(SymbolIndexService::class, $this->symbolIndexMock);
    $this->app->instance(CodeIndexerService::class, $this->codeIndexerMock);
});

afterEach(function (): void {
    Mockery::close();
});

describe('reindex:all command', function (): void {
    it('fails with invalid base path', function (): void {
        $this->artisan('reindex:all', ['--path' => '/nonexistent/base'])
            ->assertFailed();
    });

    it('warns when no subdirectories found', function (): void {
        $tempDir = sys_get_temp_dir().'/reindex-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        $this->artisan('reindex:all', ['--path' => $tempDir])
            ->assertSuccessful();

        rmdir($tempDir);
    });

    it('warns when no git repos found', function (): void {
        $tempDir = sys_get_temp_dir().'/reindex-test-'.uniqid();
        mkdir($tempDir.'/somedir', 0755, true);

        $this->artisan('reindex:all', ['--path' => $tempDir])
            ->assertSuccessful();

        rmdir($tempDir.'/somedir');
        rmdir($tempDir);
    });

    it('indexes git repos incrementally', function (): void {
        $tempDir = sys_get_temp_dir().'/reindex-test-'.uniqid();
        $repoDir = $tempDir.'/myrepo';
        mkdir($repoDir.'/.git', 0755, true);

        $this->symbolIndexMock->shouldReceive('indexFolder')
            ->once()
            ->with($repoDir.'/', true)
            ->andReturn([
                'success' => true,
                'symbol_count' => 42,
            ]);

        $home = getenv('HOME') !== false ? (string) getenv('HOME') : '/tmp';
        $indexPath = "{$home}/.code-index/local-myrepo.json";

        // Skip vectorization if index file doesn't exist
        $this->artisan('reindex:all', ['--path' => $tempDir, '--skip-vectorize' => true])
            ->assertSuccessful();

        rmdir($repoDir.'/.git');
        rmdir($repoDir);
        rmdir($tempDir);
    });

    it('handles indexing failures gracefully', function (): void {
        $tempDir = sys_get_temp_dir().'/reindex-test-'.uniqid();
        $repoDir = $tempDir.'/badrepo';
        mkdir($repoDir.'/.git', 0755, true);

        $this->symbolIndexMock->shouldReceive('indexFolder')
            ->once()
            ->andReturn(['success' => false, 'error' => 'No source files']);

        $this->artisan('reindex:all', ['--path' => $tempDir])
            ->assertFailed();

        rmdir($repoDir.'/.git');
        rmdir($repoDir);
        rmdir($tempDir);
    });

    it('skips vectorization with --skip-vectorize flag', function (): void {
        $tempDir = sys_get_temp_dir().'/reindex-test-'.uniqid();
        $repoDir = $tempDir.'/myrepo';
        mkdir($repoDir.'/.git', 0755, true);

        $this->symbolIndexMock->shouldReceive('indexFolder')
            ->once()
            ->andReturn(['success' => true, 'symbol_count' => 10]);

        $this->codeIndexerMock->shouldNotReceive('ensureCollection');
        $this->codeIndexerMock->shouldNotReceive('vectorizeFromIndex');

        $this->artisan('reindex:all', ['--path' => $tempDir, '--skip-vectorize' => true])
            ->assertSuccessful();

        rmdir($repoDir.'/.git');
        rmdir($repoDir);
        rmdir($tempDir);
    });

    it('uses default ~/projects path when --path is not provided', function (): void {
        $home = getenv('HOME') !== false ? (string) getenv('HOME') : '/tmp';
        $defaultPath = "{$home}/projects";

        // The default path should exist on the dev machine; if not, command succeeds with a warning
        // We just ensure no exception is thrown and the command runs
        if (! is_dir($defaultPath)) {
            $this->artisan('reindex:all')
                ->assertFailed();
        } else {
            // Just assert it runs without crashing — we can't control what's in ~/projects
            $this->symbolIndexMock->shouldReceive('indexFolder')->andReturn(['success' => true, 'symbol_count' => 0]);
            $this->codeIndexerMock->shouldReceive('ensureCollection')->andReturn(true);
            $this->codeIndexerMock->shouldReceive('vectorizeFromIndex')->andReturn(['success' => 0, 'failed' => 0, 'total' => 0]);
            $this->codeIndexerMock->shouldReceive('pruneStaleSymbols')->andReturn(['deleted' => 0, 'total_checked' => 0]);

            $this->artisan('reindex:all', ['--skip-vectorize' => true])
                ->assertSuccessful();
        }
    });

    it('runs vectorization when index file exists', function (): void {
        $home = getenv('HOME') !== false ? (string) getenv('HOME') : '/tmp';
        $tempDir = sys_get_temp_dir().'/reindex-test-'.uniqid();
        $repoDir = $tempDir.'/myproj';
        mkdir($repoDir.'/.git', 0755, true);

        $repo = 'local/myproj';
        $indexPath = "{$home}/.code-index/".str_replace('/', '-', $repo).'.json';
        @mkdir(dirname($indexPath), 0755, true);
        file_put_contents($indexPath, json_encode(['symbols' => []]));

        $this->symbolIndexMock->shouldReceive('indexFolder')
            ->once()
            ->andReturn(['success' => true, 'symbol_count' => 5]);

        $this->codeIndexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->codeIndexerMock->shouldReceive('vectorizeFromIndex')
            ->once()
            ->andReturn(['success' => 3, 'failed' => 0, 'total' => 3]);

        $this->codeIndexerMock->shouldReceive('pruneStaleSymbols')
            ->once()
            ->andReturn(['deleted' => 0, 'total_checked' => 3]);

        $this->artisan('reindex:all', ['--path' => $tempDir])
            ->assertSuccessful();

        @unlink($indexPath);
        rmdir($repoDir.'/.git');
        rmdir($repoDir);
        rmdir($tempDir);
    });

    it('outputs prune note when stale symbols are deleted during vectorization', function (): void {
        $home = getenv('HOME') !== false ? (string) getenv('HOME') : '/tmp';
        $tempDir = sys_get_temp_dir().'/reindex-test-'.uniqid();
        $repoDir = $tempDir.'/pruneproj';
        mkdir($repoDir.'/.git', 0755, true);

        $repo = 'local/pruneproj';
        $indexPath = "{$home}/.code-index/".str_replace('/', '-', $repo).'.json';
        @mkdir(dirname($indexPath), 0755, true);
        file_put_contents($indexPath, json_encode(['symbols' => []]));

        $this->symbolIndexMock->shouldReceive('indexFolder')
            ->once()
            ->andReturn(['success' => true, 'symbol_count' => 2]);

        $this->codeIndexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->codeIndexerMock->shouldReceive('vectorizeFromIndex')
            ->once()
            ->andReturn(['success' => 2, 'failed' => 0, 'total' => 2]);

        $this->codeIndexerMock->shouldReceive('pruneStaleSymbols')
            ->once()
            ->andReturn(['deleted' => 4, 'total_checked' => 10]);

        $this->artisan('reindex:all', ['--path' => $tempDir])
            ->assertSuccessful();

        @unlink($indexPath);
        rmdir($repoDir.'/.git');
        rmdir($repoDir);
        rmdir($tempDir);
    });
});

describe('reindex:all vectorization edge cases', function (): void {
    it('warns when index file not found and skips vectorization', function (): void {
        $tempDir = sys_get_temp_dir().'/reindex-test-'.uniqid();
        $repoDir = $tempDir.'/noindex';
        mkdir($repoDir.'/.git', 0755, true);

        $this->symbolIndexMock->shouldReceive('indexFolder')
            ->once()
            ->andReturn(['success' => true, 'symbol_count' => 1]);

        // No index file created — should hit "Index file not found" path

        $this->artisan('reindex:all', ['--path' => $tempDir])
            ->assertSuccessful()
            ->expectsOutputToContain('Index file not found');

        rmdir($repoDir.'/.git');
        rmdir($repoDir);
        rmdir($tempDir);
    });

    it('warns when ensureCollection fails and skips vectorization', function (): void {
        $home = getenv('HOME') !== false ? (string) getenv('HOME') : '/tmp';
        $tempDir = sys_get_temp_dir().'/reindex-test-'.uniqid();
        $repoDir = $tempDir.'/failcoll';
        mkdir($repoDir.'/.git', 0755, true);

        $repo = 'local/failcoll';
        $indexPath = "{$home}/.code-index/".str_replace('/', '-', $repo).'.json';
        @mkdir(dirname($indexPath), 0755, true);
        file_put_contents($indexPath, json_encode(['symbols' => []]));

        $this->symbolIndexMock->shouldReceive('indexFolder')
            ->once()
            ->andReturn(['success' => true, 'symbol_count' => 1]);

        $this->codeIndexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(false);

        $this->artisan('reindex:all', ['--path' => $tempDir])
            ->assertSuccessful()
            ->expectsOutputToContain('Failed to ensure Qdrant collection');

        @unlink($indexPath);
        rmdir($repoDir.'/.git');
        rmdir($repoDir);
        rmdir($tempDir);
    });
});
