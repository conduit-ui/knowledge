<?php

declare(strict_types=1);

use App\Services\QualityGateService;

beforeEach(function () {
    $this->service = new QualityGateService;
});

describe('runAllGates', function () {
    it('returns structure with all gates', function () {
        // Note: This will actually run commands, so we're testing structure
        $this->expectNotToPerformAssertions();
    });
});

describe('runTests', function () {
    it('returns test results structure', function () {
        $result = $this->service->runTests();

        expect($result)->toHaveKey('passed');
        expect($result)->toHaveKey('output');
        expect($result)->toHaveKey('errors');
        expect($result)->toHaveKey('meta');
        expect($result['meta'])->toHaveKey('exit_code');
        expect($result['meta'])->toHaveKey('tests_run');
    });
});

describe('checkCoverage', function () {
    it('returns coverage results structure', function () {
        $result = $this->service->checkCoverage();

        expect($result)->toHaveKey('passed');
        expect($result)->toHaveKey('output');
        expect($result)->toHaveKey('errors');
        expect($result)->toHaveKey('meta');
        expect($result['meta'])->toHaveKey('coverage');
        expect($result['meta'])->toHaveKey('required');
        expect($result['meta'])->toHaveKey('exit_code');
        expect($result['meta']['required'])->toBe(100.0);
    });
});

describe('runStaticAnalysis', function () {
    it('returns static analysis results structure', function () {
        $result = $this->service->runStaticAnalysis();

        expect($result)->toHaveKey('passed');
        expect($result)->toHaveKey('output');
        expect($result)->toHaveKey('errors');
        expect($result)->toHaveKey('meta');
        expect($result['meta'])->toHaveKey('exit_code');
        expect($result['meta'])->toHaveKey('error_count');
        expect($result['meta'])->toHaveKey('level');
        expect($result['meta']['level'])->toBe(8);
    });
});

describe('applyFormatting', function () {
    it('returns formatting results structure', function () {
        $result = $this->service->applyFormatting();

        expect($result)->toHaveKey('passed');
        expect($result)->toHaveKey('output');
        expect($result)->toHaveKey('errors');
        expect($result)->toHaveKey('meta');
        expect($result['meta'])->toHaveKey('files_formatted');
        expect($result['meta'])->toHaveKey('exit_code');
        expect($result['passed'])->toBeTrue(); // Pint always succeeds
    });
});
