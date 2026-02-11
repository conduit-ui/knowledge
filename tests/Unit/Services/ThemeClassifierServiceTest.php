<?php

declare(strict_types=1);

use App\Services\ThemeClassifierService;

beforeEach(function (): void {
    $this->classifier = new ThemeClassifierService;
});

describe('classify', function (): void {
    it('classifies quality automation entries', function (): void {
        $entry = [
            'title' => 'PHPStan Analysis Configuration',
            'content' => 'Configure PHPStan for static analysis with strict rules and test coverage requirements',
            'tags' => ['phpstan', 'testing', 'quality'],
        ];

        $result = $this->classifier->classify($entry);

        expect($result['theme'])->toBe('quality-automation');
        expect($result['confidence'])->toBeGreaterThan(0.1);
    });

    it('classifies developer experience entries', function (): void {
        $entry = [
            'title' => 'GitHub CLI Integration',
            'content' => 'Build a natural language interface for GitHub API using Conduit package',
            'tags' => ['github', 'cli', 'api'],
        ];

        $result = $this->classifier->classify($entry);

        expect($result['theme'])->toBe('developer-experience');
    });

    it('classifies context continuity entries', function (): void {
        $entry = [
            'title' => 'Knowledge Synthesis Pipeline',
            'content' => 'Implement semantic search with Qdrant vector embeddings for session memory',
            'tags' => ['knowledge', 'memory', 'synthesis'],
        ];

        $result = $this->classifier->classify($entry);

        expect($result['theme'])->toBe('context-continuity');
    });

    it('classifies infrastructure entries', function (): void {
        $entry = [
            'title' => 'Docker Deployment Configuration',
            'content' => 'Configure Podman containers for homelab server with Tailscale networking',
            'tags' => ['docker', 'infrastructure', 'server'],
        ];

        $result = $this->classifier->classify($entry);

        expect($result['theme'])->toBe('integrated-infrastructure');
    });

    it('returns null for unclassifiable entries', function (): void {
        $entry = [
            'title' => 'Random thoughts',
            'content' => 'Just some random musings about nothing in particular',
            'tags' => [],
        ];

        $result = $this->classifier->classify($entry);

        expect($result['theme'])->toBeNull();
        expect($result['confidence'])->toBeLessThan(0.1);
    });

    it('returns all theme scores', function (): void {
        $entry = [
            'title' => 'Test Entry',
            'content' => 'Some content about testing',
        ];

        $result = $this->classifier->classify($entry);

        expect($result['all_scores'])->toHaveKeys([
            'quality-automation',
            'developer-experience',
            'context-continuity',
            'integrated-infrastructure',
        ]);
    });
});

describe('classifyBatch', function (): void {
    it('returns distribution of themes', function (): void {
        $entries = [
            ['title' => 'PHPStan config', 'content' => 'Testing and quality automation'],
            ['title' => 'GitHub CLI', 'content' => 'Developer experience with API'],
            ['title' => 'Knowledge sync', 'content' => 'Memory and context continuity'],
        ];

        $result = $this->classifier->classifyBatch($entries);

        expect($result['total'])->toBe(3);
        expect($result['distribution'])->toHaveKeys([
            'quality-automation',
            'developer-experience',
            'context-continuity',
            'integrated-infrastructure',
        ]);
    });

    it('counts unclassified entries', function (): void {
        $entries = [
            ['title' => 'Xyz', 'content' => 'Abc def ghi'],
            ['title' => 'Jkl', 'content' => 'Mno pqr stu'],
        ];

        $result = $this->classifier->classifyBatch($entries);

        expect($result['unclassified'])->toBe(2);
    });
});

describe('getThemeTargets', function (): void {
    it('returns targets for all themes', function (): void {
        $targets = $this->classifier->getThemeTargets();

        expect($targets)->toHaveKeys([
            'quality-automation',
            'developer-experience',
            'context-continuity',
            'integrated-infrastructure',
        ]);

        expect($targets['quality-automation']['target'])->toBe(0.78);
        expect($targets['context-continuity']['target'])->toBe(0.35);
    });
});
