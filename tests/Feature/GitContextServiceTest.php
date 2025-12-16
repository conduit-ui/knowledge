<?php

declare(strict_types=1);

use App\Services\GitContextService;

beforeEach(function () {
    $this->service = new GitContextService;
});

it('detects if directory is a git repository', function () {
    $isGit = $this->service->isGitRepository();
    expect($isGit)->toBeTrue();
});

it('gets current repository path', function () {
    $repoPath = $this->service->getRepositoryPath();
    expect($repoPath)->toBeString()->not->toBeEmpty();
});

it('gets repository URL from remote origin', function () {
    $repoUrl = $this->service->getRepositoryUrl();
    if ($repoUrl !== null) {
        expect($repoUrl)->toBeString();
    } else {
        expect($repoUrl)->toBeNull();
    }
});

it('gets current branch name', function () {
    $branch = $this->service->getCurrentBranch();
    expect($branch)->toBeString()->not->toBeEmpty();
});

it('gets current commit hash', function () {
    $commit = $this->service->getCurrentCommit();
    expect($commit)->toBeString()->toHaveLength(40); // Full SHA-1 hash
});

it('gets git user name', function () {
    $author = $this->service->getAuthor();
    if ($author !== null) {
        expect($author)->toBeString();
    } else {
        expect($author)->toBeNull();
    }
});

it('returns full git context', function () {
    $context = $this->service->getContext();

    expect($context)->toBeArray()
        ->toHaveKeys(['repo', 'branch', 'commit', 'author']);

    expect($context['repo'])->toBeString();
    expect($context['branch'])->toBeString();
    expect($context['commit'])->toBeString();
});

it('handles non-git directory gracefully', function () {
    $service = new GitContextService('/tmp');

    expect($service->isGitRepository())->toBeFalse();
    expect($service->getRepositoryPath())->toBeNull();
    expect($service->getRepositoryUrl())->toBeNull();
    expect($service->getCurrentBranch())->toBeNull();
    expect($service->getCurrentCommit())->toBeNull();

    $context = $service->getContext();
    expect($context)->toBeArray()
        ->toHaveKeys(['repo', 'branch', 'commit', 'author']);
    expect($context['repo'])->toBeNull();
    expect($context['branch'])->toBeNull();
    expect($context['commit'])->toBeNull();
});
