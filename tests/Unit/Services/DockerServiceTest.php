<?php

declare(strict_types=1);

use App\Services\DockerService;

describe('DockerService', function () {
    describe('getHostOs', function () {
        it('returns correct OS identifier', function () {
            $service = new DockerService;
            $os = $service->getHostOs();

            expect($os)->toBeIn(['macos', 'linux', 'windows', 'unknown']);
        });

        it('returns macos on Darwin', function () {
            if (PHP_OS_FAMILY !== 'Darwin') {
                $this->markTestSkipped('Not running on macOS');
            }
            $service = new DockerService;
            expect($service->getHostOs())->toBe('macos');
        });
    });

    describe('getInstallUrl', function () {
        it('returns correct URL for macOS', function () {
            $service = new DockerService;
            if (PHP_OS_FAMILY === 'Darwin') {
                expect($service->getInstallUrl())->toBe('https://docs.docker.com/desktop/install/mac-install/');
            }
        });
    });

    describe('checkEndpoint', function () {
        it('returns false for unreachable endpoint', function () {
            $service = new DockerService;

            // Use a non-routable IP to avoid network delays
            $result = $service->checkEndpoint('http://192.0.2.1:9999/test', 1);

            expect($result)->toBeFalse();
        });

        it('returns false for invalid URL', function () {
            $service = new DockerService;

            $result = $service->checkEndpoint('not-a-url', 1);

            expect($result)->toBeFalse();
        });
    });

    describe('getVersion', function () {
        it('returns version string when Docker is installed', function () {
            $service = new DockerService;

            if ($service->isInstalled()) {
                $version = $service->getVersion();
                expect($version)->not->toBeNull();
                expect($version)->toMatch('/^[\d.]+/');
            } else {
                // Docker not installed, version should be null
                expect($service->getVersion())->toBeNull();
            }
        });
    });

    describe('isInstalled', function () {
        it('returns boolean', function () {
            $service = new DockerService;

            expect($service->isInstalled())->toBeBool();
        });
    });

    describe('isRunning', function () {
        it('returns boolean', function () {
            $service = new DockerService;

            expect($service->isRunning())->toBeBool();
        });
    });

    describe('compose', function () {
        it('returns result array with required keys', function () {
            $service = new DockerService;

            // Test with a valid directory but no docker-compose.yml
            $tempDir = sys_get_temp_dir().'/docker-test-'.uniqid();
            mkdir($tempDir);

            try {
                $result = $service->compose($tempDir, ['ps']);

                expect($result)->toHaveKeys(['success', 'output', 'exitCode']);
                expect($result['exitCode'])->toBeInt();
            } finally {
                @rmdir($tempDir);
            }
        });
    });
});
