<?php

declare(strict_types=1);

use App\Services\PatternDetectorService;

beforeEach(function (): void {
    $this->detector = new PatternDetectorService;
});

describe('detect', function (): void {
    it('finds frequent topics', function (): void {
        $entries = collect([
            ['id' => '1', 'title' => 'Laravel testing guide', 'content' => 'How to test Laravel applications'],
            ['id' => '2', 'title' => 'Laravel deployment', 'content' => 'Deploy Laravel to production'],
            ['id' => '3', 'title' => 'Laravel best practices', 'content' => 'Laravel coding standards'],
            ['id' => '4', 'title' => 'More Laravel tips', 'content' => 'Additional Laravel knowledge'],
        ]);

        $result = $this->detector->detect($entries);

        expect($result['frequent_topics'])->toHaveKey('laravel');
        expect($result['frequent_topics']['laravel'])->toBeGreaterThanOrEqual(3);
    });

    it('finds recurring tags', function (): void {
        $entries = collect([
            ['id' => '1', 'title' => 'Entry 1', 'content' => 'Content', 'tags' => ['blocker', 'urgent']],
            ['id' => '2', 'title' => 'Entry 2', 'content' => 'Content', 'tags' => ['blocker']],
            ['id' => '3', 'title' => 'Entry 3', 'content' => 'Content', 'tags' => ['blocker', 'bug']],
        ]);

        $result = $this->detector->detect($entries);

        expect($result['recurring_tags'])->toHaveKey('blocker');
        expect($result['recurring_tags']['blocker'])->toBe(3);
    });

    it('finds project associations', function (): void {
        $entries = collect([
            ['id' => '1', 'title' => 'conduit-ui update', 'content' => 'Working on conduit-ui package'],
            ['id' => '2', 'title' => 'conduit-ui fix', 'content' => 'Bug fix for conduit-ui'],
            ['id' => '3', 'title' => 'conduit-ui feature', 'content' => 'New feature for conduit-ui'],
        ]);

        $result = $this->detector->detect($entries);

        expect($result['project_associations'])->toHaveKey('conduit-ui');
    });

    it('calculates category distribution', function (): void {
        $entries = collect([
            ['id' => '1', 'title' => 'Entry', 'content' => 'Content', 'category' => 'debugging'],
            ['id' => '2', 'title' => 'Entry', 'content' => 'Content', 'category' => 'debugging'],
            ['id' => '3', 'title' => 'Entry', 'content' => 'Content', 'category' => 'architecture'],
        ]);

        $result = $this->detector->detect($entries);

        expect($result['category_distribution'])->toHaveKey('debugging');
        expect($result['category_distribution']['debugging'])->toBe(2);
        expect($result['category_distribution']['architecture'])->toBe(1);
    });

    it('generates insights for blockers', function (): void {
        $entries = collect([
            ['id' => '1', 'title' => 'Blocker 1', 'content' => 'Content', 'tags' => ['blocker']],
            ['id' => '2', 'title' => 'Blocker 2', 'content' => 'Content', 'tags' => ['blocker']],
            ['id' => '3', 'title' => 'Blocker 3', 'content' => 'Content', 'tags' => ['blocker']],
        ]);

        $result = $this->detector->detect($entries);

        $hasBlockerInsight = collect($result['insights'])
            ->contains(fn ($i): bool => str_contains($i, 'blocker'));

        expect($hasBlockerInsight)->toBeTrue();
    });

    it('ignores date tags', function (): void {
        $entries = collect([
            ['id' => '1', 'title' => 'Entry', 'content' => 'Content', 'tags' => ['2026-02-03', 'valid-tag']],
            ['id' => '2', 'title' => 'Entry', 'content' => 'Content', 'tags' => ['2026-02-03', 'valid-tag']],
            ['id' => '3', 'title' => 'Entry', 'content' => 'Content', 'tags' => ['2026-02-03', 'valid-tag']],
        ]);

        $result = $this->detector->detect($entries);

        expect($result['recurring_tags'])->not->toHaveKey('2026-02-03');
        expect($result['recurring_tags'])->toHaveKey('valid-tag');
    });

    it('filters out stop words', function (): void {
        $entries = collect([
            ['id' => '1', 'title' => 'The Laravel Framework', 'content' => 'This is a test'],
            ['id' => '2', 'title' => 'The Laravel Guide', 'content' => 'This is another test'],
            ['id' => '3', 'title' => 'The Laravel Docs', 'content' => 'This is yet another test'],
        ]);

        $result = $this->detector->detect($entries);

        // 'the' and 'this' should not appear as frequent topics
        expect($result['frequent_topics'])->not->toHaveKey('the');
        expect($result['frequent_topics'])->not->toHaveKey('this');
    });

    it('detects category imbalance when heavily skewed', function (): void {
        // Create 11+ entries with heavy skew to one category (max > min * 3)
        $entries = collect([
            ['id' => '1', 'title' => 'Entry', 'content' => 'Content', 'category' => 'debugging'],
            ['id' => '2', 'title' => 'Entry', 'content' => 'Content', 'category' => 'debugging'],
            ['id' => '3', 'title' => 'Entry', 'content' => 'Content', 'category' => 'debugging'],
            ['id' => '4', 'title' => 'Entry', 'content' => 'Content', 'category' => 'debugging'],
            ['id' => '5', 'title' => 'Entry', 'content' => 'Content', 'category' => 'debugging'],
            ['id' => '6', 'title' => 'Entry', 'content' => 'Content', 'category' => 'debugging'],
            ['id' => '7', 'title' => 'Entry', 'content' => 'Content', 'category' => 'debugging'],
            ['id' => '8', 'title' => 'Entry', 'content' => 'Content', 'category' => 'debugging'],
            ['id' => '9', 'title' => 'Entry', 'content' => 'Content', 'category' => 'debugging'],
            ['id' => '10', 'title' => 'Entry', 'content' => 'Content', 'category' => 'debugging'],
            ['id' => '11', 'title' => 'Entry', 'content' => 'Content', 'category' => 'architecture'],
        ]);

        $result = $this->detector->detect($entries);

        $hasSkewedInsight = collect($result['insights'])
            ->contains(fn ($i): bool => str_contains($i, "heavily skewed toward 'debugging'"));

        expect($hasSkewedInsight)->toBeTrue();
    });

    it('generates insights for decision tracking', function (): void {
        // Need 3+ occurrences to pass MIN_OCCURRENCES filter
        $entries = collect([
            ['id' => '1', 'title' => 'Decision 1', 'content' => 'Content', 'tags' => ['decision']],
            ['id' => '2', 'title' => 'Decision 2', 'content' => 'Content', 'tags' => ['decision']],
            ['id' => '3', 'title' => 'Decision 3', 'content' => 'Content', 'tags' => ['decision']],
        ]);

        $result = $this->detector->detect($entries);

        $hasDecisionInsight = collect($result['insights'])
            ->contains(fn ($i): bool => str_contains($i, 'decisions recorded'));

        expect($hasDecisionInsight)->toBeTrue();
    });

    it('generates insights for milestone tracking', function (): void {
        // Need 3+ occurrences to pass MIN_OCCURRENCES filter
        $entries = collect([
            ['id' => '1', 'title' => 'Milestone 1', 'content' => 'Content', 'tags' => ['milestone']],
            ['id' => '2', 'title' => 'Milestone 2', 'content' => 'Content', 'tags' => ['milestone']],
            ['id' => '3', 'title' => 'Milestone 3', 'content' => 'Content', 'tags' => ['milestone']],
        ]);

        $result = $this->detector->detect($entries);

        $hasMilestoneInsight = collect($result['insights'])
            ->contains(fn ($i): bool => str_contains($i, 'milestones captured'));

        expect($hasMilestoneInsight)->toBeTrue();
    });
});

describe('findEntriesMatchingPattern', function (): void {
    it('finds entries containing pattern', function (): void {
        $entries = collect([
            ['id' => '1', 'title' => 'Laravel testing', 'content' => 'Test content'],
            ['id' => '2', 'title' => 'PHP basics', 'content' => 'PHP content'],
            ['id' => '3', 'title' => 'More Laravel', 'content' => 'Laravel framework'],
        ]);

        $matches = $this->detector->findEntriesMatchingPattern($entries, 'laravel');

        expect($matches)->toHaveCount(2);
        expect($matches->pluck('id')->toArray())->toContain('1', '3');
    });

    it('is case insensitive', function (): void {
        $entries = collect([
            ['id' => '1', 'title' => 'LARAVEL uppercase', 'content' => 'Content'],
            ['id' => '2', 'title' => 'laravel lowercase', 'content' => 'Content'],
        ]);

        $matches = $this->detector->findEntriesMatchingPattern($entries, 'Laravel');

        expect($matches)->toHaveCount(2);
    });
});
