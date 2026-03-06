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
});
