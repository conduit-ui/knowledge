<?php

declare(strict_types=1);

use App\Services\KnowledgePathService;

describe('KnowledgePathService', function (): void {
    describe('getKnowledgeDirectory', function (): void {
        it('returns path based on HOME environment variable', function (): void {
            $originalHome = getenv('HOME');
            putenv('HOME=/Users/testuser');

            $service = new KnowledgePathService;
            $path = $service->getKnowledgeDirectory();

            expect($path)->toBe('/Users/testuser/.knowledge');

            // Restore
            if ($originalHome !== false) {
                putenv("HOME={$originalHome}");
            } else {
                putenv('HOME');
            }
        });

        it('falls back to USERPROFILE on Windows when HOME not set', function (): void {
            $originalHome = getenv('HOME');
            $originalUserProfile = getenv('USERPROFILE');

            putenv('HOME');
            putenv('USERPROFILE=C:\\Users\\testuser');

            $service = new KnowledgePathService;
            $path = $service->getKnowledgeDirectory();

            expect($path)->toBe('C:\\Users\\testuser/.knowledge');

            // Restore
            if ($originalHome !== false) {
                putenv("HOME={$originalHome}");
            }
            if ($originalUserProfile !== false) {
                putenv("USERPROFILE={$originalUserProfile}");
            } else {
                putenv('USERPROFILE');
            }
        });

        it('respects KNOWLEDGE_HOME environment variable override', function (): void {
            $originalKnowledgeHome = getenv('KNOWLEDGE_HOME');
            putenv('KNOWLEDGE_HOME=/custom/knowledge/path');

            $service = new KnowledgePathService;
            $path = $service->getKnowledgeDirectory();

            expect($path)->toBe('/custom/knowledge/path');

            // Restore
            if ($originalKnowledgeHome !== false) {
                putenv("KNOWLEDGE_HOME={$originalKnowledgeHome}");
            } else {
                putenv('KNOWLEDGE_HOME');
            }
        });
    });

    describe('getDatabasePath', function (): void {
        it('returns database path within knowledge directory', function (): void {
            $originalHome = getenv('HOME');
            putenv('HOME=/Users/testuser');

            $service = new KnowledgePathService;
            $path = $service->getDatabasePath();

            expect($path)->toBe('/Users/testuser/.knowledge/knowledge.sqlite');

            // Restore
            if ($originalHome !== false) {
                putenv("HOME={$originalHome}");
            }
        });

        it('respects KNOWLEDGE_DB_PATH environment variable override', function (): void {
            $originalDbPath = getenv('KNOWLEDGE_DB_PATH');
            putenv('KNOWLEDGE_DB_PATH=/custom/path/mydb.sqlite');

            $service = new KnowledgePathService;
            $path = $service->getDatabasePath();

            expect($path)->toBe('/custom/path/mydb.sqlite');

            // Restore
            if ($originalDbPath !== false) {
                putenv("KNOWLEDGE_DB_PATH={$originalDbPath}");
            } else {
                putenv('KNOWLEDGE_DB_PATH');
            }
        });
    });

    describe('ensureDirectoryExists', function (): void {
        it('creates directory if it does not exist', function (): void {
            $testDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();

            expect(is_dir($testDir))->toBeFalse();

            $service = new KnowledgePathService;
            $service->ensureDirectoryExists($testDir);

            expect(is_dir($testDir))->toBeTrue();

            // Cleanup
            rmdir($testDir);
        });

        it('does nothing if directory already exists', function (): void {
            $testDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
            mkdir($testDir, 0755, true);

            $service = new KnowledgePathService;
            $service->ensureDirectoryExists($testDir);

            expect(is_dir($testDir))->toBeTrue();

            // Cleanup
            rmdir($testDir);
        });

        it('creates nested directories', function (): void {
            $testDir = sys_get_temp_dir().'/knowledge-test-'.uniqid().'/nested/path';

            expect(is_dir($testDir))->toBeFalse();

            $service = new KnowledgePathService;
            $service->ensureDirectoryExists($testDir);

            expect(is_dir($testDir))->toBeTrue();

            // Cleanup
            rmdir($testDir);
            rmdir(dirname($testDir));
            rmdir(dirname(dirname($testDir)));
        });
    });

    describe('databaseExists', function (): void {
        it('returns true when database file exists', function (): void {
            $testDb = sys_get_temp_dir().'/knowledge-test-'.uniqid().'.sqlite';
            touch($testDb);

            $originalDbPath = getenv('KNOWLEDGE_DB_PATH');
            putenv("KNOWLEDGE_DB_PATH={$testDb}");

            $service = new KnowledgePathService;

            expect($service->databaseExists())->toBeTrue();

            // Cleanup
            unlink($testDb);
            if ($originalDbPath !== false) {
                putenv("KNOWLEDGE_DB_PATH={$originalDbPath}");
            } else {
                putenv('KNOWLEDGE_DB_PATH');
            }
        });

        it('returns false when database file does not exist', function (): void {
            $testDb = sys_get_temp_dir().'/knowledge-test-'.uniqid().'.sqlite';

            $originalDbPath = getenv('KNOWLEDGE_DB_PATH');
            putenv("KNOWLEDGE_DB_PATH={$testDb}");

            $service = new KnowledgePathService;

            expect($service->databaseExists())->toBeFalse();

            // Restore
            if ($originalDbPath !== false) {
                putenv("KNOWLEDGE_DB_PATH={$originalDbPath}");
            } else {
                putenv('KNOWLEDGE_DB_PATH');
            }
        });
    });
});
