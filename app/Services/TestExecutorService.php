<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class TestExecutorService
{
    private const MAX_FIX_ATTEMPTS = 3;

    private const MIN_CONFIDENCE_THRESHOLD = 70;

    public function __construct(
        private readonly OllamaService $ollama
    ) {}

    /**
     * Run tests and return detailed results.
     *
     * @param  string|null  $testFile  Specific test file to run, or null for full suite
     * @return array<string, mixed> Test execution results
     */
    public function runTests(?string $testFile = null): array
    {
        if ($testFile !== null) {
            $command = "vendor/bin/pest {$testFile} 2>&1";
        } else {
            $command = 'composer test 2>&1';
        }

        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $outputString = implode("\n", $output);
        $failures = $this->parseFailures($outputString);

        return [
            'passed' => $exitCode === 0,
            'total' => $this->extractTestCount($output),
            'failed' => count($failures),
            'failures' => $failures,
            'fix_attempts' => [],
            'output' => $outputString,
            'exit_code' => $exitCode,
        ];
    }

    /**
     * Parse Pest output for test failures.
     *
     * @param  string  $output  Raw test output
     * @return array<array<string, mixed>> Array of failure details
     */
    public function parseFailures(string $output): array
    {
        $failures = [];
        $lines = explode("\n", $output);
        $currentFailure = null;
        $inStackTrace = false;

        foreach ($lines as $line) {
            // Detect failure start (Pest format: "FAILED  Tests\Feature\ExampleTest > example")
            if (preg_match('/FAILED\s+(.+?)\s+>\s+(.+)/', $line, $matches)) {
                if ($currentFailure !== null) {
                    $failures[] = $currentFailure;
                }

                $currentFailure = [
                    'test' => trim($matches[2]),
                    'file' => $this->extractFilePath($matches[1]),
                    'message' => '',
                    'trace' => '',
                ];
                $inStackTrace = false;

                continue;
            }

            // Capture error message
            if ($currentFailure !== null && ! $inStackTrace) {
                if (preg_match('/^\s*(Expected|Failed asserting|Error|Exception|Call to)/', $line)) {
                    $currentFailure['message'] .= trim($line)."\n";

                    continue;
                }

                // Detect stack trace start
                if (str_contains($line, 'at ') && str_contains($line, '.php:')) {
                    $inStackTrace = true;
                    $currentFailure['trace'] .= trim($line)."\n";

                    continue;
                }
            }

            // Capture stack trace
            if ($currentFailure !== null && $inStackTrace) {
                if (preg_match('/^\s+at\s+/', $line) !== false && (preg_match('/^\s+at\s+/', $line) === 1 || preg_match('/^\s+\d+│/', $line) === 1)) {
                    $currentFailure['trace'] .= trim($line)."\n";

                    continue;
                }

                // End of stack trace
                if (trim($line) !== '' && ! str_contains($line, '│')) {
                    $inStackTrace = false;
                }
            }
        }

        // Add last failure if exists
        if ($currentFailure !== null) {
            $failures[] = $currentFailure;
        }

        return array_map(function ($failure) {
            return [
                'test' => $failure['test'],
                'file' => $failure['file'],
                'message' => trim($failure['message']),
                'trace' => trim($failure['trace']),
            ];
        }, $failures);
    }

    /**
     * Attempt to auto-fix a test failure using AI suggestions.
     *
     * @param  array<string, mixed>  $failure  Failure details
     * @param  int  $attempt  Current attempt number (1-based)
     * @return bool True if fix succeeded
     */
    public function autoFixFailure(array $failure, int $attempt): bool
    {
        if ($attempt > self::MAX_FIX_ATTEMPTS) {
            return false;
        }

        if (! $this->ollama->isAvailable()) {
            return false;
        }

        // Get AI analysis and suggested fix
        $codeFile = $this->getImplementationFileForTest($failure['file']);
        if ($codeFile === null) {
            return false;
        }

        $testOutput = sprintf(
            "Test: %s\nFile: %s\nMessage: %s\nTrace: %s",
            $failure['test'],
            $failure['file'],
            $failure['message'],
            $failure['trace']
        );

        $analysis = $this->ollama->analyzeTestFailure($testOutput, $failure['file'], $codeFile);

        // Only apply fix if confidence is high enough
        if ($analysis['confidence'] < self::MIN_CONFIDENCE_THRESHOLD) {
            return false;
        }

        // Apply the suggested fix
        $fixApplied = $this->applyFix(
            $analysis['file_to_modify'],
            $analysis['suggested_fix']
        );

        if (! $fixApplied) {
            return false;
        }

        // Re-run the specific test to verify fix
        $rerunResult = $this->runTests($failure['file']);

        return $rerunResult['passed'];
    }

    /**
     * Get the test file path for a given class name.
     *
     * @param  string  $className  Fully qualified class name
     * @return string|null Path to test file, or null if not found
     */
    public function getTestFileForClass(string $className): ?string
    {
        // Remove namespace prefix
        $className = str_replace('App\\', '', $className);
        $className = str_replace('\\', '/', $className);

        // Try common test locations
        $possiblePaths = [
            base_path("tests/Feature/{$className}Test.php"),
            base_path("tests/Unit/{$className}Test.php"),
            base_path("tests/{$className}Test.php"),
        ];

        foreach ($possiblePaths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        // Try to find by grepping for the class name
        $output = [];
        exec(sprintf(
            'grep -r "class %sTest" tests/ 2>/dev/null | head -1 | cut -d: -f1',
            escapeshellarg(basename($className))
        ), $output);

        if (count($output) > 0 && isset($output[0]) && File::exists(base_path($output[0]))) {
            return base_path($output[0]);
        }

        return null;
    }

    /**
     * Extract file path from test identifier.
     */
    private function extractFilePath(string $identifier): string
    {
        // Convert "Tests\Feature\ExampleTest" to "tests/Feature/ExampleTest.php"
        $path = str_replace('\\', '/', $identifier);
        $replaced = preg_replace('/^Tests\//', 'tests/', $path);
        $path = $replaced !== null ? $replaced : $path;

        if (! str_ends_with($path, '.php')) {
            $path .= '.php';
        }

        return base_path($path);
    }

    /**
     * Extract test count from output.
     *
     * @param  array<string>  $output
     */
    private function extractTestCount(array $output): int
    {
        foreach ($output as $line) {
            // Pest format: "Tests: 50 passed (100 assertions)"
            if (preg_match('/Tests:\s+(\d+)\s+passed/i', $line, $matches)) {
                return (int) $matches[1];
            }

            // Alternative format: "50 tests, 100 assertions"
            if (preg_match('/(\d+)\s+tests?,/i', $line, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    /**
     * Get implementation file for a test file.
     */
    private function getImplementationFileForTest(string $testFile): ?string
    {
        // Remove "Test.php" suffix and "tests/" prefix
        $baseName = basename($testFile, 'Test.php');
        $testDir = dirname($testFile);

        // Map test directory to source directory
        $sourceDir = str_replace('tests/Feature', 'app', $testDir);
        $sourceDir = str_replace('tests/Unit', 'app', $sourceDir);

        $possiblePaths = [
            "{$sourceDir}/{$baseName}.php",
            "app/Services/{$baseName}.php",
            "app/Commands/{$baseName}.php",
            "app/Models/{$baseName}.php",
        ];

        foreach ($possiblePaths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Apply a code fix to a file.
     */
    private function applyFix(string $filePath, string $suggestedFix): bool
    {
        if (! File::exists($filePath)) {
            return false;
        }

        // For now, we'll log the suggestion rather than auto-apply
        // In production, you'd want more sophisticated code modification
        // This prevents potentially breaking changes
        logger()->info('AI suggested fix', [
            'file' => $filePath,
            'suggestion' => $suggestedFix,
        ]);

        // TODO: Implement safe code modification using AST parsing
        // For now, return false to prevent auto-modification
        return false;
    }
}
