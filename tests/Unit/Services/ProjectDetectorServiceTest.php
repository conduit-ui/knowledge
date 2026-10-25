<?php

declare(strict_types=1);

use App\Services\GitContextService;
use App\Services\ProjectDetectorService;

if (! function_exists('fakeGitContext')) {
    function fakeGitContext(): Mockery\MockInterface
    {
        $git = Mockery::mock(GitContextService::class);
        $git->shouldReceive('isWorktree')->andReturn(false)->byDefault();
        $git->shouldReceive('getWorkingDirectory')->andReturn('/home/user/projects/app')->byDefault();
        $git->shouldReceive('getRepositoryPath')->andReturn(null)->byDefault();
        $git->shouldReceive('getMainRepositoryPath')->andReturn(null)->byDefault();
        $git->shouldReceive('getRepositoryUrl')->andReturn(null)->byDefault();

        return $git;
    }
}

describe('ProjectDetectorService', function (): void {
    it('returns default when not in a git repository', function (): void {
        $gitContext = fakeGitContext();
        $gitContext->shouldReceive('isGitRepository')->andReturn(false);

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('default');
    });

    it('extracts repo name from HTTPS remote URL', function (): void {
        $gitContext = fakeGitContext();
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/conduit-ui/knowledge.git');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('knowledge');
    });

    it('extracts repo name from SSH remote URL', function (): void {
        $gitContext = fakeGitContext();
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('git@github.com:conduit-ui/knowledge.git');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('knowledge');
    });

    it('extracts repo name from HTTPS URL without .git suffix', function (): void {
        $gitContext = fakeGitContext();
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/conduit-ui/my-project');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('my-project');
    });

    it('falls back to directory name when no remote URL', function (): void {
        $gitContext = fakeGitContext();
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn(null);
        $gitContext->shouldReceive('getRepositoryPath')->andReturn('/home/user/projects/my-app');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('my-app');
    });

    it('returns default when no remote URL and no repo path', function (): void {
        $gitContext = fakeGitContext();
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn(null);
        $gitContext->shouldReceive('getRepositoryPath')->andReturn(null);

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('default');
    });

    it('sanitizes project names with special characters', function (): void {
        $gitContext = fakeGitContext();
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/user/My Project Name.git');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('my_project_name');
    });

    it('sanitizes project names to lowercase', function (): void {
        $gitContext = fakeGitContext();
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/user/MyApp.git');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('myapp');
    });

    it('handles SSH URLs with port numbers', function (): void {
        $gitContext = fakeGitContext();
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('ssh://git@gitlab.com:2222/group/project.git');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('project');
    });

    it('handles trailing slashes in URLs', function (): void {
        $gitContext = fakeGitContext();
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('https://github.com/user/repo/');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('repo');
    });

    it('falls back to directory name when URL does not match extraction pattern', function (): void {
        $gitContext = fakeGitContext();
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn('localhost');
        $gitContext->shouldReceive('getRepositoryPath')->andReturn('/some/path/myrepo');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('myrepo');
    });

    it('returns default when directory name sanitizes to empty string', function (): void {
        $gitContext = fakeGitContext();
        $gitContext->shouldReceive('isGitRepository')->andReturn(true);
        $gitContext->shouldReceive('getRepositoryUrl')->andReturn(null);
        $gitContext->shouldReceive('getRepositoryPath')->andReturn('/path/to/!!!');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('default');
    });

    it('returns default for an empty working directory outside git', function (): void {
        $gitContext = fakeGitContext();
        $gitContext->shouldReceive('isGitRepository')->andReturn(false);
        $gitContext->shouldReceive('getWorkingDirectory')->andReturn('');

        $detector = new ProjectDetectorService($gitContext);

        expect($detector->detect())->toBe('default')
            ->and($detector->subagentId())->toBeNull();
    });
});

describe('ephemeral project mapping', function (): void {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir().'/know-project-'.uniqid();
        mkdir($this->root, 0777, true);
    });

    afterEach(function (): void {
        removeDirectory($this->root);
    });

    it('maps a subagent folder to its parent project collection', function (): void {
        $parent = $this->root.'/my-app';
        $subagent = $parent.'/subagent-42';
        initGitRepo($parent, 'https://github.com/conduit-ui/knowledge.git');
        initGitRepo($subagent);

        $detector = new ProjectDetectorService(new GitContextService($subagent));

        expect($detector->detect())->toBe('knowledge')
            ->and($detector->subagentId())->toBeNull();
    });

    it('maps a subagent directory inside a repository to that repository', function (): void {
        $parent = $this->root.'/my-app';
        $subagent = $parent.'/subagent-7';
        initGitRepo($parent);
        mkdir($subagent);

        $detector = new ProjectDetectorService(new GitContextService($subagent));

        expect($detector->detect())->toBe('my-app')
            ->and($detector->subagentId())->toBeNull();
    });

    it('maps a worktree folder to the main repository collection', function (): void {
        $parent = $this->root.'/my-app';
        $worktree = $this->root.'/feature-wt';
        initGitRepo($parent, 'https://github.com/conduit-ui/knowledge.git');
        commitEmpty($parent);
        addWorktree($parent, $worktree, 'feature-wt');

        $detector = new ProjectDetectorService(new GitContextService($worktree));

        expect($detector->detect())->toBe('knowledge')
            ->and($detector->subagentId())->toBeNull();
    });

    it('maps a subagent worktree to the main repository collection', function (): void {
        $parent = $this->root.'/my-app';
        $worktree = $this->root.'/subagent-9';
        initGitRepo($parent);
        commitEmpty($parent);
        addWorktree($parent, $worktree, 'agent-9');

        $detector = new ProjectDetectorService(new GitContextService($worktree));

        expect($detector->detect())->toBe('my-app')
            ->and($detector->subagentId())->toBeNull();
    });

    it('maps a worktree to the folder above it when the main repo path is unavailable', function (): void {
        $parent = $this->root.'/my-app';
        $worktree = $parent.'/feature-wt';
        initGitRepo($parent);
        mkdir($worktree);

        $git = fakeGitContext();
        $git->shouldReceive('isGitRepository')->andReturn(true);
        $git->shouldReceive('isWorktree')->andReturn(true);
        $git->shouldReceive('getMainRepositoryPath')->andReturn('/definitely/missing/'.uniqid());
        $git->shouldReceive('getRepositoryPath')->andReturn($worktree);
        $git->shouldReceive('getWorkingDirectory')->andReturn($worktree);
        $git->shouldReceive('getRepositoryUrl')->andReturn(null);

        $detector = new ProjectDetectorService($git);

        expect($detector->detect())->toBe('my-app')
            ->and($detector->subagentId())->toBeNull();
    });

    it('uses the shared subagents collection when a subagent has no parent project', function (): void {
        $subagent = sys_get_temp_dir().'/subagent-lonely-'.uniqid();
        mkdir($subagent);
        initGitRepo($subagent);

        try {
            $detector = new ProjectDetectorService(new GitContextService($subagent));

            $id = substr(basename($subagent), strlen('subagent-'));

            expect($detector->detect())->toBe(ProjectDetectorService::SHARED_SUBAGENTS_PROJECT)
                ->and($detector->subagentId())->toBe($id);
        } finally {
            removeDirectory($subagent);
        }
    });

    it('uses the shared collection for a loose subagent directory', function (): void {
        $subagent = sys_get_temp_dir().'/subagent-loose-'.uniqid();
        mkdir($subagent);

        try {
            $detector = new ProjectDetectorService(new GitContextService($subagent));
            $id = substr(basename($subagent), strlen('subagent-'));

            expect($detector->detect())->toBe('subagents')
                ->and($detector->subagentId())->toBe($id);
        } finally {
            removeDirectory($subagent);
        }
    });

    it('maps a sandbox folder to its parent project', function (): void {
        $parent = $this->root.'/my-app';
        $sandbox = $parent.'/sandbox-job';
        initGitRepo($parent, 'https://github.com/acme/widget.git');
        initGitRepo($sandbox);

        $detector = new ProjectDetectorService(new GitContextService($sandbox));

        expect($detector->detect())->toBe('widget')
            ->and($detector->subagentId())->toBeNull();
    });

    it('maps a temporary folder to the folder above it', function (): void {
        $parent = $this->root.'/my-app';
        $temp = $parent.'/tmp-build';
        mkdir($parent);
        initGitRepo($temp);

        $detector = new ProjectDetectorService(new GitContextService($temp));

        expect($detector->detect())->toBe('my-app')
            ->and($detector->subagentId())->toBeNull();
    });

    it('returns default for a sandbox folder with no parent project', function (): void {
        $sandbox = sys_get_temp_dir().'/sandbox-'.uniqid();
        mkdir($sandbox);
        initGitRepo($sandbox);

        try {
            $detector = new ProjectDetectorService(new GitContextService($sandbox));

            expect($detector->detect())->toBe('default')
                ->and($detector->subagentId())->toBeNull();
        } finally {
            removeDirectory($sandbox);
        }
    });

    it('keeps the remote name when a sandbox checkout has no parent directory', function (): void {
        $sandbox = sys_get_temp_dir().'/temp-'.uniqid();
        mkdir($sandbox);
        initGitRepo($sandbox, 'https://github.com/acme/my-app.git');

        try {
            $detector = new ProjectDetectorService(new GitContextService($sandbox));

            expect($detector->detect())->toBe('my-app')
                ->and($detector->subagentId())->toBeNull();
        } finally {
            removeDirectory($sandbox);
        }
    });

    it('skips a parent whose project name is itself ephemeral', function (): void {
        $projects = $this->root.'/projects';
        $parent = $projects.'/my-app';
        $subagent = $parent.'/subagent-3';
        initGitRepo($parent, 'https://github.com/acme/sandbox-job.git');
        initGitRepo($subagent);

        $detector = new ProjectDetectorService(new GitContextService($subagent));

        expect($detector->detect())->toBe('projects')
            ->and($detector->subagentId())->toBeNull();
    });
});

function initGitRepo(string $path, ?string $remote = null): void
{
    if (! is_dir($path)) {
        mkdir($path, 0777, true);
    }

    gitIn($path, ['init', '-b', 'main']);
    gitIn($path, ['config', 'user.email', 'test@example.com']);
    gitIn($path, ['config', 'user.name', 'Test']);

    if ($remote !== null) {
        gitIn($path, ['remote', 'add', 'origin', $remote]);
    }
}

function commitEmpty(string $path): void
{
    gitIn($path, ['commit', '--allow-empty', '-m', 'init']);
}

function addWorktree(string $repo, string $path, string $branch): void
{
    gitIn($repo, ['worktree', 'add', '-b', $branch, $path]);
}

/**
 * @param  array<int, string>  $args
 */
function gitIn(string $path, array $args): void
{
    $process = new Symfony\Component\Process\Process(['git', '-C', $path, ...$args]);
    $process->mustRun();
}
