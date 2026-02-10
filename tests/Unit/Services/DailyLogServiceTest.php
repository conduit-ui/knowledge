<?php

declare(strict_types=1);

use App\Services\DailyLogService;
use App\Services\KnowledgePathService;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/knowledge-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);

    $this->pathService = mock(KnowledgePathService::class);
    $this->pathService->shouldReceive('getKnowledgeDirectory')->andReturn($this->tempDir);
    $this->pathService->shouldReceive('ensureDirectoryExists')->andReturnUsing(function (string $path): void {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    });

    $this->service = new DailyLogService($this->pathService);

    Carbon::setTestNow(Carbon::parse('2026-02-10 14:30:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
    removeDirectory($this->tempDir);
});

it('returns the staging directory path', function (): void {
    expect($this->service->getStagingDirectory())
        ->toBe($this->tempDir.'/staging');
});

it('returns the daily log path for a date', function (): void {
    expect($this->service->getDailyLogPath('2026-02-10'))
        ->toBe($this->tempDir.'/staging/2026-02-10.md');
});

it('stages an entry into today\'s daily log', function (): void {
    $id = $this->service->stage([
        'title' => 'Test Decision',
        'content' => 'We decided to use Redis for caching.',
        'section' => 'Decisions',
        'category' => 'architecture',
        'tags' => ['redis', 'caching'],
        'priority' => 'high',
        'confidence' => 90,
    ]);

    expect($id)->toBeString()->not->toBeEmpty();

    $logPath = $this->service->getDailyLogPath('2026-02-10');
    expect(file_exists($logPath))->toBeTrue();

    $content = file_get_contents($logPath);
    expect($content)
        ->toContain('# Daily Log: 2026-02-10')
        ->toContain('## Decisions')
        ->toContain('## Corrections')
        ->toContain('## Commitments')
        ->toContain('## Notes')
        ->toContain('Test Decision')
        ->toContain('We decided to use Redis for caching.')
        ->toContain('**Category:** architecture')
        ->toContain('**Tags:** redis, caching')
        ->toContain('**Priority:** high')
        ->toContain('**Confidence:** 90%')
        ->toContain("<!-- entry:{$id} -->")
        ->toContain('<!-- /entry -->');
});

it('stages multiple entries into the same daily log', function (): void {
    $id1 = $this->service->stage([
        'title' => 'First Entry',
        'content' => 'First content.',
        'section' => 'Decisions',
    ]);

    $id2 = $this->service->stage([
        'title' => 'Second Entry',
        'content' => 'Second content.',
        'section' => 'Notes',
    ]);

    $entries = $this->service->readDailyLog('2026-02-10');

    expect($entries)->toHaveCount(2);
    expect($entries[0]['id'])->toBe($id1);
    expect($entries[1]['id'])->toBe($id2);
});

it('stages entries into correct sections', function (): void {
    $this->service->stage([
        'title' => 'A Decision',
        'content' => 'Decision content.',
        'section' => 'Decisions',
    ]);

    $this->service->stage([
        'title' => 'A Correction',
        'content' => 'Correction content.',
        'section' => 'Corrections',
    ]);

    $entries = $this->service->readDailyLog('2026-02-10');

    expect($entries[0]['section'])->toBe('Decisions');
    expect($entries[1]['section'])->toBe('Corrections');
});

it('defaults to Notes section for invalid section', function (): void {
    $this->service->stage([
        'title' => 'Invalid Section',
        'content' => 'Content.',
        'section' => 'InvalidSection',
    ]);

    $entries = $this->service->readDailyLog('2026-02-10');
    expect($entries[0]['section'])->toBe('Notes');
});

it('defaults to Notes section when section not provided', function (): void {
    $this->service->stage([
        'title' => 'No Section',
        'content' => 'Content.',
    ]);

    $entries = $this->service->readDailyLog('2026-02-10');
    expect($entries[0]['section'])->toBe('Notes');
});

it('reads entries from a daily log', function (): void {
    $this->service->stage([
        'title' => 'Test Entry',
        'content' => 'Test content.',
        'category' => 'testing',
        'tags' => ['php', 'pest'],
        'priority' => 'medium',
        'confidence' => 75,
    ]);

    $entries = $this->service->readDailyLog('2026-02-10');

    expect($entries)->toHaveCount(1);
    expect($entries[0]['title'])->toBe('Test Entry');
    expect($entries[0]['content'])->toBe('Test content.');
    expect($entries[0]['category'])->toBe('testing');
    expect($entries[0]['tags'])->toBe(['php', 'pest']);
    expect($entries[0]['priority'])->toBe('medium');
    expect($entries[0]['confidence'])->toBe(75);
    expect($entries[0]['timestamp'])->toBe('14:30:00');
});

it('returns empty array for non-existent daily log', function (): void {
    expect($this->service->readDailyLog('2026-01-01'))->toBe([]);
});

it('lists daily log files', function (): void {
    $this->service->stage(['title' => 'Entry 1', 'content' => 'Content.']);

    Carbon::setTestNow(Carbon::parse('2026-02-11 10:00:00'));
    $this->service->stage(['title' => 'Entry 2', 'content' => 'Content.']);

    $logs = $this->service->listDailyLogs();

    expect($logs)->toBe(['2026-02-10', '2026-02-11']);
});

it('returns empty array when no staging directory exists', function (): void {
    expect($this->service->listDailyLogs())->toBe([]);
});

it('gets promotable entries past retention period', function (): void {
    // Create an old entry
    Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
    $this->service->stage(['title' => 'Old Entry', 'content' => 'Old.']);

    // Create a recent entry
    Carbon::setTestNow(Carbon::parse('2026-02-10 10:00:00'));
    $this->service->stage(['title' => 'New Entry', 'content' => 'New.']);

    $promotable = $this->service->getPromotableEntries(7);

    expect($promotable)->toHaveCount(1);
    expect($promotable[0]['title'])->toBe('Old Entry');
    expect($promotable[0]['date'])->toBe('2026-01-01');
});

it('gets auto-promotable entries with high confidence and valid category', function (): void {
    $this->service->stage([
        'title' => 'High Confidence',
        'content' => 'Content.',
        'category' => 'architecture',
        'confidence' => 90,
    ]);

    $this->service->stage([
        'title' => 'Low Confidence',
        'content' => 'Content.',
        'category' => 'testing',
        'confidence' => 30,
    ]);

    $this->service->stage([
        'title' => 'No Category High Conf',
        'content' => 'Content.',
        'confidence' => 95,
    ]);

    $autoPromotable = $this->service->getAutoPromotableEntries();

    expect($autoPromotable)->toHaveCount(1);
    expect($autoPromotable[0]['title'])->toBe('High Confidence');
});

it('removes a specific entry from a daily log', function (): void {
    $id1 = $this->service->stage(['title' => 'Keep', 'content' => 'Keep.']);
    $id2 = $this->service->stage(['title' => 'Remove', 'content' => 'Remove.']);

    $result = $this->service->removeEntry('2026-02-10', $id2);

    expect($result)->toBeTrue();

    $entries = $this->service->readDailyLog('2026-02-10');
    expect($entries)->toHaveCount(1);
    expect($entries[0]['id'])->toBe($id1);
});

it('removes the daily log file when last entry is removed', function (): void {
    $id = $this->service->stage(['title' => 'Only Entry', 'content' => 'Content.']);

    $result = $this->service->removeEntry('2026-02-10', $id);

    expect($result)->toBeTrue();
    expect(file_exists($this->service->getDailyLogPath('2026-02-10')))->toBeFalse();
});

it('returns false when removing entry from non-existent log', function (): void {
    expect($this->service->removeEntry('2026-01-01', 'non-existent'))->toBeFalse();
});

it('returns false when removing non-existent entry', function (): void {
    $this->service->stage(['title' => 'Entry', 'content' => 'Content.']);

    expect($this->service->removeEntry('2026-02-10', 'non-existent-id'))->toBeFalse();
});

it('removes an entire daily log file', function (): void {
    $this->service->stage(['title' => 'Entry', 'content' => 'Content.']);

    $result = $this->service->removeDailyLog('2026-02-10');

    expect($result)->toBeTrue();
    expect(file_exists($this->service->getDailyLogPath('2026-02-10')))->toBeFalse();
});

it('returns false when removing non-existent daily log', function (): void {
    expect($this->service->removeDailyLog('2026-01-01'))->toBeFalse();
});

it('returns configured retention days', function (): void {
    config(['staging.retention_days' => 14]);

    expect($this->service->getRetentionDays())->toBe(14);
});

it('returns default retention days when not configured', function (): void {
    config(['staging.retention_days' => null]);

    expect($this->service->getRetentionDays())->toBe(7);
});

it('stages entry with source and ticket metadata', function (): void {
    $this->service->stage([
        'title' => 'With Metadata',
        'content' => 'Content.',
        'source' => 'https://example.com',
        'ticket' => 'JIRA-123',
        'author' => 'Test Author',
    ]);

    $logPath = $this->service->getDailyLogPath('2026-02-10');
    $content = file_get_contents($logPath);

    expect($content)
        ->toContain('**Source:** https://example.com')
        ->toContain('**Ticket:** JIRA-123')
        ->toContain('**Author:** Test Author');
});

it('stages entry with default priority and confidence', function (): void {
    $this->service->stage([
        'title' => 'Defaults',
        'content' => 'Content.',
    ]);

    $entries = $this->service->readDailyLog('2026-02-10');

    expect($entries[0]['priority'])->toBe('medium');
    expect($entries[0]['confidence'])->toBe(50);
});

it('handles entries in Commitments section', function (): void {
    $this->service->stage([
        'title' => 'Commitment',
        'content' => 'We commit to this.',
        'section' => 'Commitments',
    ]);

    $entries = $this->service->readDailyLog('2026-02-10');
    expect($entries[0]['section'])->toBe('Commitments');
});

it('returns empty for scandir failure on non-dir staging', function (): void {
    // staging dir doesn't exist
    expect($this->service->listDailyLogs())->toBe([]);
});

it('ignores non-date files in staging directory', function (): void {
    $stagingDir = $this->tempDir.'/staging';
    mkdir($stagingDir, 0755, true);

    // Create a non-date file
    file_put_contents($stagingDir.'/notes.txt', 'not a daily log');

    // Create a valid date file
    $this->service->stage(['title' => 'Entry', 'content' => 'Content.']);

    $logs = $this->service->listDailyLogs();

    expect($logs)->toBe(['2026-02-10']);
});
