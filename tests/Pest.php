<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    Tests\TestCase::class,
)->in('Feature');

uses(
    Tests\TestCase::class,
)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

if (! function_exists('removeDirectory')) {
    function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path) ?: [], ['.', '..']);

        foreach ($files as $file) {
            $filePath = "$path/$file";
            is_dir($filePath) ? removeDirectory($filePath) : unlink($filePath);
        }

        rmdir($path);
    }
}

if (! function_exists('mockProjectDetector')) {
    /**
     * Mock the ProjectDetectorService to return a fixed project name.
     * Use in beforeEach() blocks for commands that use ResolvesProject trait.
     */
    function mockProjectDetector(string $project = 'default'): void
    {
        $mock = Mockery::mock(\App\Services\ProjectDetectorService::class);
        $mock->shouldReceive('detect')->andReturn($project);
        app()->instance(\App\Services\ProjectDetectorService::class, $mock);
    }
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/
