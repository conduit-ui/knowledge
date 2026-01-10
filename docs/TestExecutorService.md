# TestExecutorService

## Overview

The `TestExecutorService` provides automated test execution, failure parsing, and AI-assisted test fixing capabilities for the knowledge Laravel application. It integrates with Pest test runner and Ollama AI service to automatically detect, analyze, and attempt to fix test failures.

## Location

`app/Services/TestExecutorService.php`

## Dependencies

- `OllamaService` - AI service for analyzing test failures and suggesting fixes
- `Illuminate\Support\Facades\File` - File system operations
- Pest test runner via `vendor/bin/pest`

## Constructor

```php
public function __construct(
    private readonly OllamaService $ollama
) {}
```

## Key Features

### 1. Test Execution

Run individual test files or the full test suite and get detailed results.

```php
$service = app(TestExecutorService::class);

// Run full test suite
$results = $service->runTests();

// Run specific test file
$results = $service->runTests('tests/Feature/ExampleTest.php');
```

### 2. Failure Parsing

Automatically parse Pest test output to extract failure details.

```php
$output = "... pest test output ...";
$failures = $service->parseFailures($output);

// Returns array of failures:
[
    [
        'test' => 'example test name',
        'file' => '/path/to/test.php',
        'message' => 'Expected true but got false',
        'trace' => 'at tests/Feature/ExampleTest.php:15'
    ]
]
```

### 3. AI-Assisted Auto-Fix

Attempt to automatically fix failing tests using Ollama AI suggestions.

```php
$failure = [
    'test' => 'it validates user input',
    'file' => 'tests/Feature/UserTest.php',
    'message' => 'Expected validation to pass',
    'trace' => '...'
];

$fixed = $service->autoFixFailure($failure, $attempt = 1);
// Returns: bool - true if fix succeeded, false otherwise
```

**Auto-fix constraints:**
- Maximum 3 attempts per failure
- Requires Ollama service to be available
- Only applies fixes with confidence >= 70%
- Currently logs suggestions (manual review required)

### 4. Test File Discovery

Find test files for specific classes.

```php
$testFile = $service->getTestFileForClass('App\Services\ExampleService');
// Returns: '/path/to/tests/Feature/Services/ExampleServiceTest.php'
```

## Return Format from runTests()

```php
[
    'passed' => true|false,        // Overall test suite status
    'total' => 50,                 // Total number of tests run
    'failed' => 2,                 // Number of failed tests
    'failures' => [...],           // Array of failure details
    'fix_attempts' => [],          // Array of auto-fix attempts
    'output' => '...',            // Raw test output
    'exit_code' => 0               // Process exit code
]
```

## Configuration

### Constants

- `MAX_FIX_ATTEMPTS` = 3 - Maximum retry attempts per failure
- `MIN_CONFIDENCE_THRESHOLD` = 70 - Minimum AI confidence required to apply fix

## Test File Mapping

The service automatically maps test files to implementation files:

| Test Location | Implementation Location |
|--------------|------------------------|
| `tests/Feature/Services/ExampleTest.php` | `app/Services/Example.php` |
| `tests/Unit/Services/ExampleTest.php` | `app/Services/Example.php` |
| `tests/Feature/Commands/ExampleTest.php` | `app/Commands/Example.php` |

## Failure Parsing

The service parses Pest output to extract:

1. **Test name** - From failure header
2. **File path** - Converted from namespace to file path
3. **Error message** - Expected/assertion failure messages
4. **Stack trace** - File and line number information

### Supported Failure Formats

- Assertion failures: `Failed asserting that...`
- Expectation failures: `Expected X but got Y`
- Exceptions: `Exception: message`
- Method call errors: `Call to undefined method...`

## Example Usage

### Basic Test Execution

```php
use App\Services\TestExecutorService;

$executor = app(TestExecutorService::class);

// Run all tests
$results = $executor->runTests();

if (!$results['passed']) {
    foreach ($results['failures'] as $failure) {
        echo "Failed: {$failure['test']}\n";
        echo "File: {$failure['file']}\n";
        echo "Error: {$failure['message']}\n";
    }
}
```

### Auto-Fix Workflow

```php
$results = $executor->runTests();

if (!$results['passed']) {
    foreach ($results['failures'] as $failure) {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            if ($executor->autoFixFailure($failure, $attempt)) {
                echo "Fixed on attempt {$attempt}\n";
                break;
            }
        }
    }
}
```

## Testing

Test file: `tests/Unit/Services/TestExecutorServiceTest.php`

Run tests:
```bash
vendor/bin/pest tests/Unit/Services/TestExecutorServiceTest.php
```

## Quality Standards

- **PHPStan Level 8** - Full strict type checking
- **100% Test Coverage** - All methods tested
- **Laravel Pint** - Code style compliance

## Future Enhancements

### Planned Features

1. **Safe Code Modification** - AST-based code patching instead of text replacement
2. **Fix History Tracking** - Database logging of all fix attempts and outcomes
3. **Success Rate Analytics** - Track which types of failures are fixable
4. **Integration with CI/CD** - Automatic PR creation with fixes
5. **Multi-Strategy Fixes** - Try multiple approaches per failure
6. **Test Generation** - Auto-generate missing tests

### Known Limitations

1. Auto-fix currently only logs suggestions (manual review required)
2. No support for modifying database migrations or config files
3. Cannot fix failures caused by missing dependencies
4. Stack trace parsing may fail on highly customized test output formats

## Related Services

- `OllamaService` - AI analysis and suggestions
- `QualityGateService` - Comprehensive quality checking including tests
- `IssueAnalyzerService` - GitHub issue analysis and file recommendations

## See Also

- [OllamaService Documentation](./OllamaService.md)
- [Quality Gates Documentation](./QualityGates.md)
- [Testing Guide](../TESTING.md)
