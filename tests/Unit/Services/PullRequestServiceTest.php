<?php

declare(strict_types=1);

use App\Services\PullRequestService;
use Illuminate\Support\Facades\Process;

uses()->group('pr-service-unit');

describe('PullRequestService', function (): void {
    beforeEach(function (): void {
        $this->service = new PullRequestService;
    });

    describe('buildDescription', function (): void {
        it('builds formatted PR description with all sections', function (): void {
            $issue = [
                'number' => 123,
                'title' => 'Add pull request service',
            ];

            $analysis = [
                'summary' => 'This issue requires creating a new PullRequestService class to handle GitHub PR creation via gh CLI.',
                'approach' => 'Create service class with methods for building descriptions and creating PRs.',
            ];

            $todos = [
                ['content' => 'Create PullRequestService class', 'type' => 'implementation'],
                ['content' => 'Add tests for PullRequestService', 'type' => 'test'],
                ['content' => 'Run PHPStan analysis', 'type' => 'quality'],
            ];

            $coverage = [
                'previous' => 95.5,
                'current' => 97.2,
                'delta' => 1.7,
            ];

            $description = $this->service->buildDescription($issue, $analysis, $todos, $coverage);

            expect($description)->toContain('Closes #123');
            expect($description)->toContain('## Summary');
            expect($description)->toContain('This issue requires creating a new PullRequestService class');
            expect($description)->toContain('## AI Analysis');
            expect($description)->toContain('## Todo Checklist');
            expect($description)->toContain('- [ ] Create PullRequestService class');
            expect($description)->toContain('- [ ] Add tests for PullRequestService');
            expect($description)->toContain('- [ ] Run PHPStan analysis');
            expect($description)->toContain('## Coverage');
            expect($description)->toContain('95.5% → 97.2% (+1.7%)');
        });

        it('handles missing coverage data gracefully', function (): void {
            $issue = ['number' => 123, 'title' => 'Test'];
            $analysis = ['summary' => 'Test summary'];
            $todos = [['content' => 'Test todo', 'type' => 'test']];
            $coverage = [];

            $description = $this->service->buildDescription($issue, $analysis, $todos, $coverage);

            expect($description)->toContain('Closes #123');
            expect($description)->toContain('Test summary');
            expect($description)->not->toContain('Coverage');
        });

        it('handles empty todos', function (): void {
            $issue = ['number' => 123, 'title' => 'Test'];
            $analysis = ['summary' => 'Test summary'];
            $todos = [];
            $coverage = [];

            $description = $this->service->buildDescription($issue, $analysis, $todos, $coverage);

            expect($description)->toContain('Closes #123');
            expect($description)->toContain('Test summary');
            expect($description)->not->toContain('Todo Checklist');
        });
    });

    describe('getCurrentCoverage', function (): void {
        it('parses coverage from composer test-coverage output', function (): void {
            Process::fake([
                'composer test-coverage' => Process::result(
                    output: "  PASS  Tests\Unit\Example\n\n  Tests:    10 passed (10 assertions)\n  Duration: 0.15s\n\n  Cov:      95.5%\n"
                ),
            ]);

            $coverage = $this->service->getCurrentCoverage();

            expect($coverage)->toBe(95.5);

            Process::assertRan('composer test-coverage');
        });

        it('returns 0.0 when coverage cannot be determined', function (): void {
            Process::fake([
                'composer test-coverage' => Process::result(
                    output: 'No coverage data found'
                ),
            ]);

            $coverage = $this->service->getCurrentCoverage();

            expect($coverage)->toBe(0.0);
        });

        it('returns 0.0 when command fails', function (): void {
            Process::fake([
                'composer test-coverage' => Process::result(
                    exitCode: 1,
                    output: 'Error running tests'
                ),
            ]);

            $coverage = $this->service->getCurrentCoverage();

            expect($coverage)->toBe(0.0);
        });
    });

    describe('commitChanges', function (): void {
        it('commits changes with provided message', function (): void {
            Process::fake([
                'git add .' => Process::result(),
                'git commit -m *' => Process::result(output: '[main abc123] Test commit'),
            ]);

            $result = $this->service->commitChanges('Test commit message');

            expect($result)->toBeTrue();

            Process::assertRan('git add .');
            Process::assertRan(fn ($command) => str_contains($command, 'git commit -m') && str_contains($command, 'Test commit message'));
        });

        it('returns false when commit fails', function (): void {
            Process::fake([
                'git add .' => Process::result(),
                'git commit -m *' => Process::result(exitCode: 1, output: 'nothing to commit'),
            ]);

            $result = $this->service->commitChanges('Test commit');

            expect($result)->toBeFalse();
        });

        it('returns false when git add fails', function (): void {
            Process::fake([
                'git add .' => Process::result(exitCode: 1, output: 'error'),
            ]);

            $result = $this->service->commitChanges('Test commit');

            expect($result)->toBeFalse();
        });
    });

    describe('pushBranch', function (): void {
        it('pushes branch to remote', function (): void {
            Process::fake([
                'git push -u origin *' => Process::result(output: 'Branch pushed successfully'),
            ]);

            $result = $this->service->pushBranch('feature/test-branch');

            expect($result)->toBeTrue();

            Process::assertRan(fn ($command) => str_contains($command, 'git push -u origin') && str_contains($command, 'feature/test-branch'));
        });

        it('returns false when push fails', function (): void {
            Process::fake([
                'git push -u origin *' => Process::result(exitCode: 1, output: 'push failed'),
            ]);

            $result = $this->service->pushBranch('feature/test-branch');

            expect($result)->toBeFalse();
        });
    });

    describe('create', function (): void {
        it('creates PR successfully and returns details', function (): void {
            Process::fake([
                'git add .' => Process::result(),
                'git commit -m *' => Process::result(),
                'git push -u origin *' => Process::result(),
                'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'feature/test-branch'),
                'composer test-coverage' => Process::result(output: "Cov: 95.5%\n"),
                'gh pr create *' => Process::result(output: 'https://github.com/conduit-ui/knowledge/pull/123'),
            ]);

            $issue = [
                'number' => 123,
                'title' => 'Add test feature',
            ];

            $analysis = [
                'summary' => 'Add new test feature implementation',
            ];

            $todos = [
                ['content' => 'Implement feature', 'type' => 'implementation'],
            ];

            $coverage = [
                'previous' => 94.0,
                'current' => 95.5,
                'delta' => 1.5,
            ];

            $result = $this->service->create($issue, $analysis, $todos, $coverage);

            expect($result['success'])->toBeTrue();
            expect($result['url'])->toBe('https://github.com/conduit-ui/knowledge/pull/123');
            expect($result['number'])->toBe(123);
            expect($result['error'])->toBeNull();

            Process::assertRan(fn ($command) => str_contains($command, 'gh pr create'));
        });

        it('returns error when commit fails', function (): void {
            Process::fake([
                'git add .' => Process::result(),
                'git commit -m *' => Process::result(exitCode: 1),
            ]);

            $result = $this->service->create([], [], [], []);

            expect($result['success'])->toBeFalse();
            expect($result['error'])->toContain('Failed to commit changes');
            expect($result['url'])->toBeNull();
            expect($result['number'])->toBeNull();
        });

        it('returns error when push fails', function (): void {
            Process::fake([
                'git add .' => Process::result(),
                'git commit -m *' => Process::result(),
                'git push -u origin *' => Process::result(exitCode: 1),
                'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'feature/test'),
            ]);

            $result = $this->service->create([], [], [], []);

            expect($result['success'])->toBeFalse();
            expect($result['error'])->toContain('Failed to push branch');
            expect($result['url'])->toBeNull();
            expect($result['number'])->toBeNull();
        });

        it('returns error when PR creation fails', function (): void {
            Process::fake([
                'git add .' => Process::result(),
                'git commit -m *' => Process::result(),
                'git push -u origin *' => Process::result(),
                'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'feature/test'),
                'composer test-coverage' => Process::result(output: "Cov: 95.5%\n"),
                'gh pr create *' => Process::result(exitCode: 1, output: 'PR creation failed'),
            ]);

            $issue = ['number' => 123, 'title' => 'Test'];
            $analysis = ['summary' => 'Test'];
            $todos = [];
            $coverage = [];

            $result = $this->service->create($issue, $analysis, $todos, $coverage);

            expect($result['success'])->toBeFalse();
            expect($result['error'])->toContain('Failed to create PR');
            expect($result['url'])->toBeNull();
            expect($result['number'])->toBeNull();
        });

        it('extracts PR number from URL', function (): void {
            Process::fake([
                'git add .' => Process::result(),
                'git commit -m *' => Process::result(),
                'git push -u origin *' => Process::result(),
                'git rev-parse --abbrev-ref HEAD' => Process::result(output: 'feature/test'),
                'composer test-coverage' => Process::result(output: "Cov: 95.5%\n"),
                'gh pr create *' => Process::result(output: 'https://github.com/conduit-ui/knowledge/pull/456'),
            ]);

            $result = $this->service->create(
                ['number' => 123, 'title' => 'Test'],
                ['summary' => 'Test'],
                [],
                []
            );

            expect($result['success'])->toBeTrue();
            expect($result['number'])->toBe(456);
        });
    });
});
