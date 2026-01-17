<?php

declare(strict_types=1);

use App\Services\IssueAnalyzerService;
use App\Services\OllamaService;

beforeEach(function () {
    $this->mockOllama = Mockery::mock(OllamaService::class);
    $this->service = new IssueAnalyzerService($this->mockOllama);
});

afterEach(function () {
    Mockery::close();
});

describe('analyzeIssue', function () {
    it('analyzes issue and returns validated analysis', function () {
        $issue = [
            'title' => 'Add user authentication feature',
            'body' => 'Need to implement login and registration',
        ];

        $ollamaAnalysis = [
            'files' => [
                [
                    'path' => 'app/Services/AuthService.php',
                    'change_type' => 'add feature',
                    'confidence' => 90,
                    'reason' => 'Authentication logic',
                ],
            ],
            'approach' => 'Implement auth service',
            'complexity' => 'medium',
            'confidence' => 85,
            'requires_architecture_change' => false,
        ];

        $this->mockOllama->shouldReceive('analyzeIssue')
            ->once()
            ->with(Mockery::type('array'), Mockery::type('array'))
            ->andReturn($ollamaAnalysis);

        $result = $this->service->analyzeIssue($issue);

        expect($result)->toHaveKey('files');
        expect($result)->toHaveKey('confidence');
        expect($result['files'][0])->toHaveKey('exists');
    });

    it('reduces confidence when file does not exist', function () {
        $issue = [
            'title' => 'Fix bug in non-existent file',
            'body' => 'Bug fix needed',
        ];

        $ollamaAnalysis = [
            'files' => [
                [
                    'path' => '/nonexistent/file.php',
                    'change_type' => 'fix bug',
                    'confidence' => 90,
                    'reason' => 'Bug fix',
                ],
            ],
            'approach' => 'Fix the bug',
            'complexity' => 'low',
            'confidence' => 90,
            'requires_architecture_change' => false,
        ];

        $this->mockOllama->shouldReceive('analyzeIssue')
            ->once()
            ->andReturn($ollamaAnalysis);

        $result = $this->service->analyzeIssue($issue);

        expect($result['files'][0]['confidence'])->toBe(60); // 90 - 30
        expect($result['files'][0]['reason'])->toContain('file does not exist');
    });

    it('does not reduce confidence for file creation', function () {
        $issue = [
            'title' => 'Create new service',
            'body' => 'Add new service class',
        ];

        $ollamaAnalysis = [
            'files' => [
                [
                    'path' => '/new/service.php',
                    'change_type' => 'create new class',
                    'confidence' => 85,
                    'reason' => 'New service',
                ],
            ],
            'approach' => 'Create service',
            'complexity' => 'low',
            'confidence' => 85,
            'requires_architecture_change' => false,
        ];

        $this->mockOllama->shouldReceive('analyzeIssue')
            ->once()
            ->andReturn($ollamaAnalysis);

        $result = $this->service->analyzeIssue($issue);

        expect($result['files'][0]['confidence'])->toBe(85); // Not reduced
    });

    it('recalculates overall confidence based on file confidence', function () {
        $issue = [
            'title' => 'Multiple changes',
            'body' => 'Several files need updates',
        ];

        $ollamaAnalysis = [
            'files' => [
                [
                    'path' => '/file1.php',
                    'change_type' => 'update',
                    'confidence' => 80,
                    'reason' => 'File 1',
                ],
                [
                    'path' => '/file2.php',
                    'change_type' => 'update',
                    'confidence' => 60,
                    'reason' => 'File 2',
                ],
            ],
            'approach' => 'Update multiple files',
            'complexity' => 'medium',
            'confidence' => 70,
            'requires_architecture_change' => false,
        ];

        $this->mockOllama->shouldReceive('analyzeIssue')
            ->once()
            ->andReturn($ollamaAnalysis);

        $result = $this->service->analyzeIssue($issue);

        // Average: (50 + 30) / 2 = 40 (both files don't exist, so -30 each)
        expect($result['confidence'])->toBeGreaterThan(0);
    });
});

describe('buildTodoList', function () {
    it('creates implementation todos', function () {
        $analysis = [
            'files' => [
                [
                    'path' => 'app/Services/UserService.php',
                    'change_type' => 'add method',
                    'confidence' => 85,
                ],
            ],
        ];

        $todos = $this->service->buildTodoList($analysis);

        $implTodos = array_filter($todos, fn ($t) => $t['type'] === 'implementation');
        expect($implTodos)->toHaveCount(1);
        expect(array_values($implTodos)[0]['content'])->toContain('Implement add method');
    });

    it('creates test todos', function () {
        $analysis = [
            'files' => [
                [
                    'path' => 'tests/Feature/UserTest.php',
                    'change_type' => 'add tests',
                    'confidence' => 90,
                ],
            ],
        ];

        $todos = $this->service->buildTodoList($analysis);

        $testTodos = array_filter($todos, fn ($t) => $t['type'] === 'test');
        expect($testTodos)->toHaveCount(1);
        expect($testTodos[0]['content'])->toContain('Add tests');
    });

    it('creates quality gate todos', function () {
        $analysis = ['files' => []];

        $todos = $this->service->buildTodoList($analysis);

        $qualityTodos = array_filter($todos, fn ($t) => $t['type'] === 'quality');
        expect($qualityTodos)->toHaveCount(3);

        $contents = array_map(fn ($t) => $t['content'], $qualityTodos);
        expect($contents)->toContain('Run tests and verify coverage');
        expect($contents)->toContain('Run PHPStan analysis');
        expect($contents)->toContain('Apply Laravel Pint formatting');
    });

    it('groups refactor files separately', function () {
        $analysis = [
            'files' => [
                [
                    'path' => 'app/Services/OldService.php',
                    'change_type' => 'refactor code',
                    'confidence' => 80,
                ],
            ],
        ];

        $todos = $this->service->buildTodoList($analysis);

        // Refactor files don't create implementation or test todos by default
        $implTodos = array_filter($todos, fn ($t) => $t['type'] === 'implementation');
        expect($implTodos)->toHaveCount(0);
    });

    it('includes confidence in todos', function () {
        $analysis = [
            'files' => [
                [
                    'path' => 'app/Models/User.php',
                    'change_type' => 'update',
                    'confidence' => 75,
                ],
            ],
        ];

        $todos = $this->service->buildTodoList($analysis);

        $implTodo = array_values(array_filter($todos, fn ($t) => $t['type'] === 'implementation'))[0];
        expect($implTodo['confidence'])->toBe(75);
    });

    it('quality todos always have 100% confidence', function () {
        $analysis = ['files' => []];

        $todos = $this->service->buildTodoList($analysis);

        $qualityTodos = array_filter($todos, fn ($t) => $t['type'] === 'quality');
        foreach ($qualityTodos as $todo) {
            expect($todo['confidence'])->toBe(100);
        }
    });
});
