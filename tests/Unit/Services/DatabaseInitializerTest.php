<?php

declare(strict_types=1);

use App\Services\DatabaseInitializer;
use App\Services\KnowledgePathService;
use Illuminate\Support\Facades\Artisan;

describe('DatabaseInitializer', function (): void {
    describe('initialize', function (): void {
        it('creates knowledge directory if it does not exist', function (): void {
            $testDir = sys_get_temp_dir().'/knowledge-init-test-'.uniqid();
            $testDb = $testDir.'/knowledge.sqlite';

            // Create directory so touch() works
            mkdir($testDir, 0755, true);

            $pathService = Mockery::mock(KnowledgePathService::class);
            $pathService->shouldReceive('getKnowledgeDirectory')->andReturn($testDir);
            $pathService->shouldReceive('getDatabasePath')->andReturn($testDb);
            $pathService->shouldReceive('databaseExists')->andReturn(false);
            $pathService->shouldReceive('ensureDirectoryExists')->with($testDir)->once();

            Artisan::shouldReceive('call')
                ->with('migrate', ['--force' => true])
                ->once()
                ->andReturn(0);

            $initializer = new DatabaseInitializer($pathService);
            $initializer->initialize();

            // Cleanup
            if (file_exists($testDb)) {
                unlink($testDb);
            }
            rmdir($testDir);
        });

        it('runs migrations when database does not exist', function (): void {
            $testDir = sys_get_temp_dir().'/knowledge-init-test-'.uniqid();
            $testDb = $testDir.'/knowledge.sqlite';

            // Create directory so touch() works
            mkdir($testDir, 0755, true);

            $pathService = Mockery::mock(KnowledgePathService::class);
            $pathService->shouldReceive('getKnowledgeDirectory')->andReturn($testDir);
            $pathService->shouldReceive('getDatabasePath')->andReturn($testDb);
            $pathService->shouldReceive('databaseExists')->andReturn(false);
            $pathService->shouldReceive('ensureDirectoryExists');

            Artisan::shouldReceive('call')
                ->with('migrate', ['--force' => true])
                ->once()
                ->andReturn(0);

            $initializer = new DatabaseInitializer($pathService);
            $initializer->initialize();

            // Cleanup
            if (file_exists($testDb)) {
                unlink($testDb);
            }
            rmdir($testDir);
        });

        it('skips migrations when database already exists', function (): void {
            $testDir = sys_get_temp_dir().'/knowledge-init-test-'.uniqid();
            $testDb = $testDir.'/knowledge.sqlite';

            $pathService = Mockery::mock(KnowledgePathService::class);
            $pathService->shouldReceive('getKnowledgeDirectory')->andReturn($testDir);
            $pathService->shouldReceive('getDatabasePath')->andReturn($testDb);
            $pathService->shouldReceive('databaseExists')->andReturn(true);
            $pathService->shouldReceive('ensureDirectoryExists');

            Artisan::shouldReceive('call')->never();

            $initializer = new DatabaseInitializer($pathService);
            $initializer->initialize();
        });

        it('creates empty database file before running migrations', function (): void {
            $testDir = sys_get_temp_dir().'/knowledge-init-test-'.uniqid();
            $testDb = $testDir.'/knowledge.sqlite';

            mkdir($testDir, 0755, true);

            $pathService = Mockery::mock(KnowledgePathService::class);
            $pathService->shouldReceive('getKnowledgeDirectory')->andReturn($testDir);
            $pathService->shouldReceive('getDatabasePath')->andReturn($testDb);
            $pathService->shouldReceive('databaseExists')->andReturn(false);
            $pathService->shouldReceive('ensureDirectoryExists');

            Artisan::shouldReceive('call')
                ->with('migrate', ['--force' => true])
                ->once()
                ->andReturn(0);

            $initializer = new DatabaseInitializer($pathService);
            $initializer->initialize();

            expect(file_exists($testDb))->toBeTrue();

            // Cleanup
            unlink($testDb);
            rmdir($testDir);
        });
    });

    describe('isInitialized', function (): void {
        it('returns true when database exists', function (): void {
            $pathService = Mockery::mock(KnowledgePathService::class);
            $pathService->shouldReceive('databaseExists')->andReturn(true);

            $initializer = new DatabaseInitializer($pathService);

            expect($initializer->isInitialized())->toBeTrue();
        });

        it('returns false when database does not exist', function (): void {
            $pathService = Mockery::mock(KnowledgePathService::class);
            $pathService->shouldReceive('databaseExists')->andReturn(false);

            $initializer = new DatabaseInitializer($pathService);

            expect($initializer->isInitialized())->toBeFalse();
        });
    });
});
