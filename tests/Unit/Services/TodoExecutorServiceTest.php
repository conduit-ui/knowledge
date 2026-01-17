<?php

declare(strict_types=1);

use App\Services\OllamaService;
use App\Services\QualityGateService;
use App\Services\TestExecutorService;
use App\Services\TodoExecutorService;
use LaravelZero\Framework\Commands\Command;

beforeEach(function () {
    $this->mockOllama = Mockery::mock(OllamaService::class);
    $this->mockTestExecutor = Mockery::mock(TestExecutorService::class);
    $this->mockQualityGate = Mockery::mock(QualityGateService::class);
    $this->mockCommand = Mockery::mock(Command::class);

    $this->service = new TodoExecutorService(
        $this->mockOllama,
        $this->mockTestExecutor,
        $this->mockQualityGate
    );

    // Default command mock expectations
    $this->mockCommand->shouldReceive('info')->andReturn(null);
    $this->mockCommand->shouldReceive('line')->andReturn(null);
    $this->mockCommand->shouldReceive('warn')->andReturn(null);
    $this->mockCommand->shouldReceive('error')->andReturn(null);
    $this->mockCommand->shouldReceive('newLine')->andReturn(null);
});

afterEach(function () {
    Mockery::close();
});

describe('execute', function () {
    it('executes todos and returns results', function () {
        $todos = [
            [
                'content' => 'Run tests',
                'type' => 'quality',
            ],
        ];

        $issue = ['title' => 'Test Issue', 'body' => 'Description'];

        $this->mockQualityGate->shouldReceive('runAllGates')
            ->once()
            ->andReturn(['passed' => true]);

        $result = $this->service->execute($todos, $issue, $this->mockCommand);

        expect($result)->toHaveKey('completed');
        expect($result)->toHaveKey('failed');
        expect($result)->toHaveKey('success');
    });

    it('marks todos as completed when successful', function () {
        $todos = [
            [
                'content' => 'Run coverage',
                'type' => 'quality',
            ],
        ];

        $issue = ['title' => 'Test', 'body' => 'Test'];

        $this->mockQualityGate->shouldReceive('checkCoverage')
            ->once()
            ->andReturn(['passed' => true]);

        $result = $this->service->execute($todos, $issue, $this->mockCommand);

        expect($result['completed'])->toHaveCount(1);
        expect($result['failed'])->toBeEmpty();
        expect($result['success'])->toBeTrue();
    });

    it('marks todos as failed when unsuccessful', function () {
        $todos = [
            [
                'content' => 'Run tests',
                'type' => 'quality',
            ],
        ];

        $issue = ['title' => 'Test', 'body' => 'Test'];

        $this->mockQualityGate->shouldReceive('runAllGates')
            ->once()
            ->andReturn(['passed' => false, 'errors' => ['Test failed']]);

        $result = $this->service->execute($todos, $issue, $this->mockCommand);

        expect($result['completed'])->toBeEmpty();
        expect($result['failed'])->toHaveCount(1);
        expect($result['success'])->toBeFalse();
    });

    it('stops execution on blocking failure', function () {
        $todos = [
            [
                'content' => 'First todo',
                'type' => 'test',
                'file' => 'test.php',
            ],
            [
                'content' => 'Second todo',
                'type' => 'quality',
            ],
        ];

        $issue = ['title' => 'Test', 'body' => 'Test'];

        $this->mockTestExecutor->shouldReceive('runTests')
            ->once()
            ->andReturn(['passed' => false, 'failures' => []]);

        // Second todo should not be executed
        $this->mockQualityGate->shouldReceive('runAllGates')
            ->never();

        $result = $this->service->execute($todos, $issue, $this->mockCommand);

        expect($result['success'])->toBeFalse();
    });

    it('handles unknown todo type', function () {
        $todos = [
            [
                'content' => 'Unknown task',
                'type' => 'unknown',
            ],
        ];

        $issue = ['title' => 'Test', 'body' => 'Test'];

        $result = $this->service->execute($todos, $issue, $this->mockCommand);

        expect($result['failed'])->toHaveCount(1);
        expect($result['failed'][0]['reason'])->toContain('Unknown todo type');
    });
});

describe('getCompletedTodos', function () {
    it('returns empty array initially', function () {
        $result = $this->service->getCompletedTodos();

        expect($result)->toBeArray();
        expect($result)->toBeEmpty();
    });

    it('returns completed todos after execution', function () {
        $todos = [
            [
                'content' => 'Apply Pint formatting',
                'type' => 'quality',
            ],
        ];

        $issue = ['title' => 'Test', 'body' => 'Test'];

        $this->mockQualityGate->shouldReceive('applyFormatting')
            ->once()
            ->andReturn(['passed' => true]);

        $this->service->execute($todos, $issue, $this->mockCommand);

        $completed = $this->service->getCompletedTodos();

        expect($completed)->toHaveCount(1);
    });
});

describe('getFailedTodos', function () {
    it('returns empty array initially', function () {
        $result = $this->service->getFailedTodos();

        expect($result)->toBeArray();
        expect($result)->toBeEmpty();
    });

    it('returns failed todos after execution', function () {
        $todos = [
            [
                'content' => 'Run static analysis',
                'type' => 'quality',
            ],
        ];

        $issue = ['title' => 'Test', 'body' => 'Test'];

        $this->mockQualityGate->shouldReceive('runStaticAnalysis')
            ->once()
            ->andReturn(['passed' => false, 'errors' => ['PHPStan failed']]);

        $this->service->execute($todos, $issue, $this->mockCommand);

        $failed = $this->service->getFailedTodos();

        expect($failed)->toHaveCount(1);
        expect($failed[0])->toHaveKey('todo');
        expect($failed[0])->toHaveKey('reason');
    });
});

describe('quality todo execution', function () {
    it('executes coverage check', function () {
        $todo = [
            'content' => 'Check coverage',
            'type' => 'quality',
        ];

        $this->mockQualityGate->shouldReceive('checkCoverage')
            ->once()
            ->andReturn(['passed' => true]);

        $result = $this->service->execute([$todo], [], $this->mockCommand);

        expect($result['success'])->toBeTrue();
    });

    it('executes PHPStan check', function () {
        $todo = [
            'content' => 'Run PHPStan analysis',
            'type' => 'quality',
        ];

        $this->mockQualityGate->shouldReceive('runStaticAnalysis')
            ->once()
            ->andReturn(['passed' => true]);

        $result = $this->service->execute([$todo], [], $this->mockCommand);

        expect($result['success'])->toBeTrue();
    });

    it('executes Pint formatting', function () {
        $todo = [
            'content' => 'Apply Laravel Pint formatting',
            'type' => 'quality',
        ];

        $this->mockQualityGate->shouldReceive('applyFormatting')
            ->once()
            ->andReturn(['passed' => true]);

        $result = $this->service->execute([$todo], [], $this->mockCommand);

        expect($result['success'])->toBeTrue();
    });

    it('executes all gates for generic quality todo', function () {
        $todo = [
            'content' => 'Run quality checks',
            'type' => 'quality',
        ];

        $this->mockQualityGate->shouldReceive('runAllGates')
            ->once()
            ->andReturn(['passed' => true]);

        $result = $this->service->execute([$todo], [], $this->mockCommand);

        expect($result['success'])->toBeTrue();
    });
});

describe('test todo execution', function () {
    it('executes tests successfully', function () {
        $todo = [
            'content' => 'Run tests',
            'type' => 'test',
            'file' => 'tests/Unit/ExampleTest.php',
        ];

        $this->mockTestExecutor->shouldReceive('runTests')
            ->once()
            ->with('tests/Unit/ExampleTest.php')
            ->andReturn(['passed' => true, 'failures' => []]);

        $result = $this->service->execute([$todo], [], $this->mockCommand);

        expect($result['success'])->toBeTrue();
    });

    it('attempts auto-fix on test failure', function () {
        $todo = [
            'content' => 'Run tests',
            'type' => 'test',
            'file' => 'tests/Unit/ExampleTest.php',
        ];

        $failure = [
            'test' => 'example test',
            'file' => 'tests/Unit/ExampleTest.php',
            'message' => 'Failed',
            'trace' => '',
        ];

        $this->mockTestExecutor->shouldReceive('runTests')
            ->once()
            ->andReturn(['passed' => false, 'failures' => [$failure]]);

        $this->mockTestExecutor->shouldReceive('autoFixFailure')
            ->once()
            ->with($failure, 0)
            ->andReturn(false);

        $result = $this->service->execute([$todo], [], $this->mockCommand);

        expect($result['success'])->toBeFalse();
    });
});

describe('implementation todo execution', function () {
    it('fails when file does not exist', function () {
        $todo = [
            'content' => 'Implement feature',
            'type' => 'implementation',
            'file' => '/nonexistent/file.php',
        ];

        $result = $this->service->execute([$todo], [], $this->mockCommand);

        expect($result['success'])->toBeFalse();
        expect($result['failed'][0]['reason'])->toContain('not yet implemented');
    });
});
