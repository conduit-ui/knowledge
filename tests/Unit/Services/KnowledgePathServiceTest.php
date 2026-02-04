<?php

declare(strict_types=1);

use App\Services\KnowledgePathService;
use App\Services\RuntimeEnvironment;

describe('KnowledgePathService', function (): void {
    describe('getKnowledgeDirectory', function (): void {
        it('returns path based on HOME environment variable', function (): void {
            // Get the current HOME value (don't try to override as putenv doesn't reliably work)
            $home = getenv('HOME');

            // Skip if HOME is not set (unlikely on any Unix-like system)
            if ($home === false || $home === '') {
                $this->markTestSkipped('HOME environment variable is not set');
            }

            $runtime = new RuntimeEnvironment;
            $service = new KnowledgePathService($runtime);
            $path = $service->getKnowledgeDirectory();

            expect($path)->toBe($home.'/.knowledge');
        });

        it('falls back to USERPROFILE on Windows when HOME not set', function (): void {
            // This test only works on Windows where HOME is not set by default
            // On Linux/macOS, putenv('HOME') doesn't truly unset HOME
            if (PHP_OS_FAMILY !== 'Windows') {
                $this->markTestSkipped('USERPROFILE fallback test only runs on Windows');
            }

            $originalHome = getenv('HOME');
            $originalUserProfile = getenv('USERPROFILE');

            putenv('HOME');
            putenv('USERPROFILE=C:\\Users\\testuser');

            $runtime = new RuntimeEnvironment;
            $service = new KnowledgePathService($runtime);
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

            $runtime = new RuntimeEnvironment;
            $service = new KnowledgePathService($runtime);
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

    describe('ensureDirectoryExists', function (): void {
        it('creates directory if it does not exist', function (): void {
            $testDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();

            expect(is_dir($testDir))->toBeFalse();

            $runtime = new RuntimeEnvironment;
            $service = new KnowledgePathService($runtime);
            $service->ensureDirectoryExists($testDir);

            expect(is_dir($testDir))->toBeTrue();

            // Cleanup
            rmdir($testDir);
        });

        it('does nothing if directory already exists', function (): void {
            $testDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
            mkdir($testDir, 0755, true);

            $runtime = new RuntimeEnvironment;
            $service = new KnowledgePathService($runtime);
            $service->ensureDirectoryExists($testDir);

            expect(is_dir($testDir))->toBeTrue();

            // Cleanup
            rmdir($testDir);
        });

        it('creates nested directories', function (): void {
            $testDir = sys_get_temp_dir().'/knowledge-test-'.uniqid().'/nested/path';

            expect(is_dir($testDir))->toBeFalse();

            $runtime = new RuntimeEnvironment;
            $service = new KnowledgePathService($runtime);
            $service->ensureDirectoryExists($testDir);

            expect(is_dir($testDir))->toBeTrue();

            // Cleanup
            rmdir($testDir);
            rmdir(dirname($testDir));
            rmdir(dirname($testDir, 2));
        });
    });
});
