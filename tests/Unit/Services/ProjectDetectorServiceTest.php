<?php

declare(strict_types=1);

use App\Services\GitContextService;
use App\Services\ProjectDetectorService;

describe('ProjectDetectorService', function (): void {
    it('returns default when not in a git repository', function (): void {
        $gitContext = Mockery::mock(GitContextService::class);
        $gitContext->shouldReceive('isGitRepository')->andReturn(false);

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('default');
    });

    it('extracts repo name from HTTPS remote URL', function (): void {
        $gitContext = Mockery::mock(GitContextService::class);
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/conduit-ui/knowledge.git');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('knowledge');
    });

    it('extracts repo name from SSH remote URL', function (): void {
        $gitContext = Mockery::mock(GitContextService::class);
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('git@github.com:conduit-ui/knowledge.git');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('knowledge');
    });

    it('extracts repo name from HTTPS URL without .git suffix', function (): void {
        $gitContext = Mockery::mock(GitContextService::class);
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/conduit-ui/my-project');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('my-project');
    });

    it('falls back to directory name when no remote URL', function (): void {
        $gitContext = Mockery::mock(GitContextService::class);
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn(null);
        $gitContext->shouldReceive('getRepositoryPath')->andReturn('/home/user/projects/my-app');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('my-app');
    });

    it('returns default when no remote URL and no repo path', function (): void {
        $gitContext = Mockery::mock(GitContextService::class);
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn(null);
        $gitContext->shouldReceive('getRepositoryPath')->andReturn(null);

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('default');
    });

    it('sanitizes project names with special characters', function (): void {
        $gitContext = Mockery::mock(GitContextService::class);
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/user/My Project Name.git');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('my_project_name');
    });

    it('sanitizes project names to lowercase', function (): void {
        $gitContext = Mockery::mock(GitContextService::class);
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/user/MyApp.git');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('myapp');
    });

    it('handles SSH URLs with port numbers', function (): void {
        $gitContext = Mockery::mock(GitContextService::class);
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('ssh://git@gitlab.com:2222/group/project.git');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('project');
    });

    it('handles trailing slashes in URLs', function (): void {
        $gitContext = Mockery::mock(GitContextService::class);
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/user/repo/');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('repo');
    });
});
