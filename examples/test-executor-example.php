<?php

declare(strict_types=1);

/**
 * TestExecutorService Usage Examples
 *
 * This file demonstrates how to use the TestExecutorService
 * for automated test execution and AI-assisted fixing.
 */

use App\Services\TestExecutorService;

// Example 1: Run Full Test Suite
function runFullTestSuite(TestExecutorService $executor): void
{
    echo "Running full test suite...\n\n";

    $results = $executor->runTests();

    echo "Results:\n";
    echo "- Passed: ".($results['passed'] ? 'Yes' : 'No')."\n";
    echo "- Total Tests: {$results['total']}\n";
    echo "- Failed Tests: {$results['failed']}\n";
    echo "- Exit Code: {$results['exit_code']}\n\n";

    if (! $results['passed'] && ! empty($results['failures'])) {
        echo "Failures:\n";
        foreach ($results['failures'] as $i => $failure) {
            echo sprintf(
                "%d. Test: %s\n   File: %s\n   Error: %s\n\n",
                $i + 1,
                $failure['test'],
                $failure['file'],
                substr($failure['message'], 0, 100)
            );
        }
    }
}

// Example 2: Run Specific Test File
function runSpecificTest(TestExecutorService $executor): void
{
    $testFile = 'tests/Unit/Services/TestExecutorServiceTest.php';

    echo "Running specific test: {$testFile}\n\n";

    $results = $executor->runTests($testFile);

    if ($results['passed']) {
        echo "✓ All tests passed!\n";
    } else {
        echo "✗ {$results['failed']} test(s) failed\n";
    }
}

// Example 3: Parse Test Output
function parseTestOutput(TestExecutorService $executor): void
{
    $sampleOutput = <<<'OUTPUT'
   FAILED  Tests\Feature\UserTest > it validates email
  Expected email to be valid.

  at tests/Feature/UserTest.php:25
     21│     it('validates email', function () {
     22│         expect('invalid-email')->toBeEmail();
     23│     });

   FAILED  Tests\Feature\UserTest > it creates user
  Failed asserting that null is not null.

  at tests/Feature/UserTest.php:30
OUTPUT;

    echo "Parsing test output...\n\n";

    $failures = $executor->parseFailures($sampleOutput);

    echo "Found ".count($failures)." failure(s):\n\n";

    foreach ($failures as $i => $failure) {
        echo sprintf(
            "%d. %s\n   File: %s\n   Message: %s\n\n",
            $i + 1,
            $failure['test'],
            basename($failure['file']),
            trim($failure['message'])
        );
    }
}

// Example 4: Find Test File for Class
function findTestFile(TestExecutorService $executor): void
{
    $className = 'App\Services\OllamaService';

    echo "Finding test file for class: {$className}\n\n";

    $testFile = $executor->getTestFileForClass($className);

    if ($testFile !== null) {
        echo "✓ Found test file: {$testFile}\n";
    } else {
        echo "✗ No test file found for class\n";
    }
}

// Example 5: Auto-Fix Workflow (Demonstration)
function autoFixWorkflow(TestExecutorService $executor): void
{
    echo "Auto-fix workflow demonstration\n\n";

    // Run tests and get failures
    $results = $executor->runTests();

    if ($results['passed']) {
        echo "All tests passing - nothing to fix!\n";

        return;
    }

    echo "Found {$results['failed']} failing test(s)\n\n";

    foreach ($results['failures'] as $failure) {
        echo "Attempting to fix: {$failure['test']}\n";

        // Try up to 3 fix attempts
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            echo "  Attempt {$attempt}... ";

            $fixed = $executor->autoFixFailure($failure, $attempt);

            if ($fixed) {
                echo "✓ Fixed!\n";
                break;
            } else {
                echo "✗ Failed\n";
            }

            if ($attempt === 3) {
                echo "  Gave up after 3 attempts\n";
            }
        }

        echo "\n";
    }
}

// Example 6: Integration with Quality Gates
function integrationExample(TestExecutorService $executor): void
{
    echo "Quality gate integration example\n\n";

    // Step 1: Run tests
    echo "1. Running tests...\n";
    $testResults = $executor->runTests();

    if (! $testResults['passed']) {
        echo "   ✗ Tests failed ({$testResults['failed']} failures)\n\n";

        // Step 2: Attempt auto-fix
        echo "2. Attempting auto-fixes...\n";
        $fixedCount = 0;

        foreach ($testResults['failures'] as $failure) {
            if ($executor->autoFixFailure($failure, 1)) {
                $fixedCount++;
            }
        }

        echo "   Fixed {$fixedCount}/{$testResults['failed']} failures\n\n";

        // Step 3: Re-run tests
        echo "3. Re-running tests...\n";
        $retestResults = $executor->runTests();

        if ($retestResults['passed']) {
            echo "   ✓ All tests now passing!\n";
        } else {
            echo "   ✗ Still {$retestResults['failed']} failing tests\n";
            echo "   Manual intervention required\n";
        }
    } else {
        echo "   ✓ All tests passed\n";
    }
}

// Example 7: Batch Processing Multiple Test Files
function batchProcessing(TestExecutorService $executor): void
{
    $testFiles = [
        'tests/Unit/Services/OllamaServiceTest.php',
        'tests/Unit/Services/SessionServiceTest.php',
        'tests/Feature/Services/SemanticSearchServiceTest.php',
    ];

    echo "Batch processing ".count($testFiles)." test files\n\n";

    $summary = [
        'total' => 0,
        'passed' => 0,
        'failed' => 0,
    ];

    foreach ($testFiles as $testFile) {
        if (! file_exists($testFile)) {
            echo "✗ {$testFile} - File not found\n";

            continue;
        }

        $results = $executor->runTests($testFile);

        $summary['total']++;
        if ($results['passed']) {
            $summary['passed']++;
            echo "✓ ".basename($testFile)." - Passed\n";
        } else {
            $summary['failed']++;
            echo "✗ ".basename($testFile)." - Failed ({$results['failed']} failures)\n";
        }
    }

    echo "\nSummary:\n";
    echo "- Total Files: {$summary['total']}\n";
    echo "- Passed: {$summary['passed']}\n";
    echo "- Failed: {$summary['failed']}\n";
}

/*
 * To use these examples:
 *
 * 1. In a Laravel Zero command:
 *    $executor = app(TestExecutorService::class);
 *    runFullTestSuite($executor);
 *
 * 2. In Tinker:
 *    $executor = app(TestExecutorService::class);
 *    runSpecificTest($executor);
 *
 * 3. In tests:
 *    $executor = app(TestExecutorService::class);
 *    parseTestOutput($executor);
 */
