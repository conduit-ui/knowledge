<?php

declare(strict_types=1);

namespace App\Services;

class QualityGateService
{
    /**
     * Run all quality gates and return aggregated results.
     *
     * @return array<string, mixed>
     */
    public function runAllGates(): array
    {
        $results = [
            'formatting' => $this->applyFormatting(),
            'tests' => $this->runTests(),
            'coverage' => $this->checkCoverage(),
            'static_analysis' => $this->runStaticAnalysis(),
        ];

        $allPassed = collect($results)->every(fn ($result) => $result['passed']);

        return [
            'passed' => $allPassed,
            'gates' => $results,
            'summary' => $this->buildSummary($results),
        ];
    }

    /**
     * Run all tests and return results.
     *
     * @return array<string, mixed>
     */
    public function runTests(): array
    {
        $output = [];
        $exitCode = 0;

        exec('composer test 2>&1', $output, $exitCode);

        $outputString = implode("\n", $output);

        return [
            'passed' => $exitCode === 0,
            'output' => $outputString,
            'errors' => $exitCode !== 0 ? $this->extractTestErrors($output) : [],
            'meta' => [
                'exit_code' => $exitCode,
                'tests_run' => $this->extractTestCount($output),
            ],
        ];
    }

    /**
     * Check test coverage and return results.
     *
     * @return array<string, mixed>
     */
    public function checkCoverage(): array
    {
        $output = [];
        $exitCode = 0;

        exec('composer test-coverage 2>&1', $output, $exitCode);

        $outputString = implode("\n", $output);
        $coverage = $this->extractCoveragePercentage($output);

        return [
            'passed' => $coverage >= 100.0,
            'output' => $outputString,
            'errors' => $coverage < 100.0 ? ['Coverage is below 100%'] : [],
            'meta' => [
                'coverage' => $coverage,
                'required' => 100.0,
                'exit_code' => $exitCode,
            ],
        ];
    }

    /**
     * Run PHPStan static analysis and return results.
     *
     * @return array<string, mixed>
     */
    public function runStaticAnalysis(): array
    {
        $output = [];
        $exitCode = 0;

        exec('composer analyse 2>&1', $output, $exitCode);

        $outputString = implode("\n", $output);
        $errors = $this->extractPhpStanErrors($output);

        return [
            'passed' => $exitCode === 0,
            'output' => $outputString,
            'errors' => $errors,
            'meta' => [
                'exit_code' => $exitCode,
                'error_count' => count($errors),
                'level' => 8,
            ],
        ];
    }

    /**
     * Apply code formatting with Laravel Pint and return results.
     *
     * @return array<string, mixed>
     */
    public function applyFormatting(): array
    {
        $output = [];
        $exitCode = 0;

        exec('composer format 2>&1', $output, $exitCode);

        $outputString = implode("\n", $output);
        $filesFormatted = $this->extractFormattedFilesCount($output);

        return [
            'passed' => true, // Pint always succeeds when it runs
            'output' => $outputString,
            'errors' => [],
            'meta' => [
                'files_formatted' => $filesFormatted,
                'exit_code' => $exitCode,
            ],
        ];
    }

    /**
     * Extract test count from test output.
     *
     * @param  array<string>  $output
     */
    private function extractTestCount(array $output): int
    {
        foreach ($output as $line) {
            // Look for patterns like "Tests: 25 passed"
            if (preg_match('/Tests:\s+(\d+)\s+passed/i', $line, $matches)) {
                return (int) $matches[1];
            }
            // Alternative pattern "25 tests, 50 assertions"
            if (preg_match('/(\d+)\s+tests?,/i', $line, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    /**
     * Extract test errors from output.
     *
     * @param  array<string>  $output
     * @return array<string>
     */
    private function extractTestErrors(array $output): array
    {
        $errors = [];
        $inError = false;

        foreach ($output as $line) {
            if (preg_match('/FAILED|ERRORS|ERROR|Failed/i', $line)) {
                $inError = true;
                $errors[] = trim($line);

                continue;
            }

            if ($inError && trim($line) !== '') {
                $errors[] = trim($line);
            }
        }

        return $errors;
    }

    /**
     * Extract coverage percentage from coverage output.
     *
     * @param  array<string>  $output
     */
    private function extractCoveragePercentage(array $output): float
    {
        foreach ($output as $line) {
            // Look for patterns like "100.0%" or "100%" in coverage reports
            if (preg_match('/(\d+\.?\d*)%/', $line, $matches)) {
                return (float) $matches[1];
            }
        }

        return 0.0;
    }

    /**
     * Extract PHPStan errors from output.
     *
     * @param  array<string>  $output
     * @return array<string>
     */
    private function extractPhpStanErrors(array $output): array
    {
        $errors = [];

        foreach ($output as $line) {
            // PHPStan errors typically contain file paths and line numbers
            if (str_contains($line, '.php:') || str_contains($line, 'ERROR')) {
                $errors[] = trim($line);
            }
        }

        return $errors;
    }

    /**
     * Extract number of files formatted by Pint.
     *
     * @param  array<string>  $output
     */
    private function extractFormattedFilesCount(array $output): int
    {
        foreach ($output as $line) {
            // Pint shows "FIXED  N files" or similar
            if (preg_match('/FIXED\s+(\d+)/i', $line, $matches)) {
                return (int) $matches[1];
            }
        }

        // If no files needed fixing, it's still successful
        return 0;
    }

    /**
     * Build a summary of all gate results.
     *
     * @param  array<string, array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    private function buildSummary(array $results): array
    {
        $passed = array_filter($results, fn ($result) => isset($result['passed']) && $result['passed'] === true);
        $failed = array_filter($results, fn ($result) => ! isset($result['passed']) || $result['passed'] !== true);

        return [
            'total_gates' => count($results),
            'passed_count' => count($passed),
            'failed_count' => count($failed),
            'passed_gates' => array_keys($passed),
            'failed_gates' => array_keys($failed),
        ];
    }
}
