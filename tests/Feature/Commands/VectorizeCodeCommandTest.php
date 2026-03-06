<?php

declare(strict_types=1);

use App\Services\CodeIndexerService;
use App\Services\SymbolIndexService;

beforeEach(function (): void {
    $this->codeIndexerMock = Mockery::mock(CodeIndexerService::class);
    $this->symbolIndexMock = Mockery::mock(SymbolIndexService::class);
    $this->app->instance(CodeIndexerService::class, $this->codeIndexerMock);
    $this->app->instance(SymbolIndexService::class, $this->symbolIndexMock);
});

afterEach(function (): void {
    Mockery::close();
});

describe('vectorize-code command', function (): void {
    it('fails when index file does not exist', function (): void {
        $this->codeIndexerMock->shouldNotReceive('ensureCollection');

        $this->artisan('vectorize-code', ['repo' => 'local/nonexistent'])
            ->assertFailed();
    });

    it('fails when collection creation fails', function (): void {
        $home = getenv('HOME') !== false ? (string) getenv('HOME') : '/tmp';
        $indexPath = "{$home}/.code-index/local-test-vectorize.json";

        // Create temporary index file
        @mkdir(dirname($indexPath), 0755, true);
        file_put_contents($indexPath, json_encode(['symbols' => []]));

        $this->codeIndexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(false);

        $this->artisan('vectorize-code', ['repo' => 'local/test-vectorize'])
            ->assertFailed();

        @unlink($indexPath);
    });

    it('successfully vectorizes symbols', function (): void {
        $home = getenv('HOME') !== false ? (string) getenv('HOME') : '/tmp';
        $indexPath = "{$home}/.code-index/local-test-vectorize.json";

        @mkdir(dirname($indexPath), 0755, true);
        file_put_contents($indexPath, json_encode(['symbols' => []]));

        $this->codeIndexerMock->shouldReceive('ensureCollection')
            ->once()
            ->andReturn(true);

        $this->codeIndexerMock->shouldReceive('vectorizeFromIndex')
            ->once()
            ->andReturn(['success' => 5, 'failed' => 1, 'total' => 6]);

        $this->artisan('vectorize-code', ['repo' => 'local/test-vectorize'])
            ->assertSuccessful();

        @unlink($indexPath);
    });

    it('passes kind filters', function (): void {
        $home = getenv('HOME') !== false ? (string) getenv('HOME') : '/tmp';
        $indexPath = "{$home}/.code-index/local-test-vectorize.json";

        @mkdir(dirname($indexPath), 0755, true);
        file_put_contents($indexPath, json_encode(['symbols' => []]));

        $this->codeIndexerMock->shouldReceive('ensureCollection')->once()->andReturn(true);

        $this->codeIndexerMock->shouldReceive('vectorizeFromIndex')
            ->withArgs(function (string $path, string $repo, $si, array $kinds) {
                return $kinds === ['class', 'method'];
            })
            ->once()
            ->andReturn(['success' => 3, 'failed' => 0, 'total' => 3]);

        $this->artisan('vectorize-code', [
            'repo' => 'local/test-vectorize',
            '--kind' => ['class', 'method'],
        ])->assertSuccessful();

        @unlink($indexPath);
    });

    it('passes language filter', function (): void {
        $home = getenv('HOME') !== false ? (string) getenv('HOME') : '/tmp';
        $indexPath = "{$home}/.code-index/local-test-vectorize.json";

        @mkdir(dirname($indexPath), 0755, true);
        file_put_contents($indexPath, json_encode(['symbols' => []]));

        $this->codeIndexerMock->shouldReceive('ensureCollection')->once()->andReturn(true);

        $this->codeIndexerMock->shouldReceive('vectorizeFromIndex')
            ->withArgs(function (string $path, string $repo, $si, array $kinds, ?string $language) {
                return $language === 'php';
            })
            ->once()
            ->andReturn(['success' => 2, 'failed' => 0, 'total' => 2]);

        $this->artisan('vectorize-code', [
            'repo' => 'local/test-vectorize',
            '--language' => 'php',
        ])->assertSuccessful();

        @unlink($indexPath);
    });
});
