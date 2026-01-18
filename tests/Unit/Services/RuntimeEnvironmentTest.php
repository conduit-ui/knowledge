<?php

declare(strict_types=1);

use App\Services\RuntimeEnvironment;

describe('RuntimeEnvironment', function (): void {
    describe('isPhar', function (): void {
        it('returns false when not running as PHAR', function (): void {
            $runtime = new RuntimeEnvironment;

            expect($runtime->isPhar())->toBeFalse();
        });
    });

    describe('basePath', function (): void {
        it('returns project root in dev mode', function (): void {
            $runtime = new RuntimeEnvironment;
            $expectedPath = dirname(__DIR__, 3);

            expect($runtime->basePath())->toBe($expectedPath);
        });
    });

    // Note: databasePath() method was removed when migrating from SQLite to Qdrant

    describe('cachePath', function (): void {
        it('returns storage/framework cache path in dev mode', function (): void {
            $runtime = new RuntimeEnvironment;
            $expectedPath = dirname(__DIR__, 3).'/storage/framework';

            expect($runtime->cachePath())->toBe($expectedPath);
        });

        it('returns storage/framework/views for views cache in dev mode', function (): void {
            $runtime = new RuntimeEnvironment;
            $expectedPath = dirname(__DIR__, 3).'/storage/framework/views';

            expect($runtime->cachePath('views'))->toBe($expectedPath);
        });

        it('returns storage/framework/data for data cache in dev mode', function (): void {
            $runtime = new RuntimeEnvironment;
            $expectedPath = dirname(__DIR__, 3).'/storage/framework/data';

            expect($runtime->cachePath('data'))->toBe($expectedPath);
        });
    });

    describe('ensureDirectoryExists', function (): void {
        it('creates directory if it does not exist', function (): void {
            $testDir = sys_get_temp_dir().'/runtime-test-'.uniqid();

            expect(is_dir($testDir))->toBeFalse();

            $runtime = new RuntimeEnvironment;
            $runtime->ensureDirectoryExists($testDir);

            expect(is_dir($testDir))->toBeTrue();

            // Cleanup
            rmdir($testDir);
        });

        it('does nothing if directory already exists', function (): void {
            $testDir = sys_get_temp_dir().'/runtime-test-'.uniqid();
            mkdir($testDir, 0755, true);

            $runtime = new RuntimeEnvironment;
            $runtime->ensureDirectoryExists($testDir);

            expect(is_dir($testDir))->toBeTrue();

            // Cleanup
            rmdir($testDir);
        });

        it('creates nested directories', function (): void {
            $testDir = sys_get_temp_dir().'/runtime-test-'.uniqid().'/nested/path';

            expect(is_dir($testDir))->toBeFalse();

            $runtime = new RuntimeEnvironment;
            $runtime->ensureDirectoryExists($testDir);

            expect(is_dir($testDir))->toBeTrue();

            // Cleanup
            rmdir($testDir);
            rmdir(dirname($testDir));
            rmdir(dirname(dirname($testDir)));
        });
    });
});
