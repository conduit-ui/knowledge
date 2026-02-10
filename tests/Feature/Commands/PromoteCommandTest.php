<?php

declare(strict_types=1);

use App\Services\DailyLogService;
use App\Services\QdrantService;

beforeEach(function (): void {
    $this->dailyLogService = mock(DailyLogService::class);
    $this->qdrantService = mock(QdrantService::class);

    app()->instance(DailyLogService::class, $this->dailyLogService);
    app()->instance(QdrantService::class, $this->qdrantService);
});

it('shows promotable entries overview by default', function (): void {
    $this->dailyLogService->shouldReceive('getRetentionDays')->andReturn(7);
    $this->dailyLogService->shouldReceive('listDailyLogs')->andReturn(['2026-02-01', '2026-02-10']);
    $this->dailyLogService->shouldReceive('getPromotableEntries')->with(7)->andReturn([
        ['id' => 'abc', 'title' => 'Old', 'date' => '2026-02-01'],
    ]);
    $this->dailyLogService->shouldReceive('getAutoPromotableEntries')->andReturn([]);
    $this->dailyLogService->shouldReceive('readDailyLog')
        ->with('2026-02-01')
        ->andReturn([['id' => 'abc']]);
    $this->dailyLogService->shouldReceive('readDailyLog')
        ->with('2026-02-10')
        ->andReturn([['id' => 'def'], ['id' => 'ghi']]);

    $this->artisan('promote')->assertSuccessful();
});

it('shows info when no daily logs exist', function (): void {
    $this->dailyLogService->shouldReceive('getRetentionDays')->andReturn(7);
    $this->dailyLogService->shouldReceive('listDailyLogs')->andReturn([]);
    $this->dailyLogService->shouldReceive('getPromotableEntries')->with(7)->andReturn([]);
    $this->dailyLogService->shouldReceive('getAutoPromotableEntries')->andReturn([]);

    $this->artisan('promote')->assertSuccessful();
});

it('promotes a specific entry by ID', function (): void {
    $entry = [
        'id' => 'test-uuid-123',
        'title' => 'Test Entry',
        'content' => 'Test content.',
        'section' => 'Decisions',
        'category' => 'architecture',
        'tags' => ['php'],
        'priority' => 'high',
        'confidence' => 90,
    ];

    $this->dailyLogService->shouldReceive('listDailyLogs')->andReturn(['2026-02-10']);
    $this->dailyLogService->shouldReceive('readDailyLog')->with('2026-02-10')->andReturn([$entry]);
    $this->qdrantService->shouldReceive('upsert')
        ->once()
        ->with(Mockery::on(fn ($data): bool => $data['title'] === 'Test Entry'
            && $data['content'] === 'Test content.'
            && $data['status'] === 'validated'), Mockery::any(), false)
        ->andReturn(true);
    $this->dailyLogService->shouldReceive('removeEntry')->with('2026-02-10', 'test-uuid-123')->andReturn(true);

    $this->artisan('promote', ['--id' => 'test-uuid-123'])->assertSuccessful();
});

it('fails when promoting non-existent entry by ID', function (): void {
    $this->dailyLogService->shouldReceive('listDailyLogs')->andReturn(['2026-02-10']);
    $this->dailyLogService->shouldReceive('readDailyLog')->with('2026-02-10')->andReturn([]);

    $this->artisan('promote', ['--id' => 'non-existent'])->assertFailed();
});

it('promotes all entries for a specific date', function (): void {
    $entries = [
        [
            'id' => 'entry-1',
            'title' => 'Entry 1',
            'content' => 'Content 1.',
            'section' => 'Decisions',
            'category' => null,
            'tags' => [],
            'priority' => 'medium',
            'confidence' => 50,
        ],
        [
            'id' => 'entry-2',
            'title' => 'Entry 2',
            'content' => 'Content 2.',
            'section' => 'Notes',
            'category' => 'testing',
            'tags' => ['test'],
            'priority' => 'high',
            'confidence' => 80,
        ],
    ];

    $this->dailyLogService->shouldReceive('readDailyLog')->with('2026-02-10')->andReturn($entries);
    $this->qdrantService->shouldReceive('upsert')->twice()->andReturn(true);
    $this->dailyLogService->shouldReceive('removeEntry')->twice()->andReturn(true);

    $this->artisan('promote', ['--date' => '2026-02-10'])->assertSuccessful();
});

it('handles empty date gracefully', function (): void {
    $this->dailyLogService->shouldReceive('readDailyLog')->with('2026-01-01')->andReturn([]);

    $this->artisan('promote', ['--date' => '2026-01-01'])->assertSuccessful();
});

it('auto-promotes eligible entries', function (): void {
    $entries = [
        [
            'id' => 'auto-1',
            'title' => 'Auto Entry',
            'content' => 'Content.',
            'section' => 'Decisions',
            'category' => 'architecture',
            'tags' => [],
            'priority' => 'high',
            'confidence' => 90,
            'date' => '2026-02-10',
        ],
    ];

    $this->dailyLogService->shouldReceive('getAutoPromotableEntries')->andReturn($entries);
    $this->qdrantService->shouldReceive('upsert')->once()->andReturn(true);
    $this->dailyLogService->shouldReceive('removeEntry')->with('2026-02-10', 'auto-1')->andReturn(true);

    $this->artisan('promote', ['--auto' => true])->assertSuccessful();
});

it('handles no auto-promotable entries', function (): void {
    $this->dailyLogService->shouldReceive('getAutoPromotableEntries')->andReturn([]);

    $this->artisan('promote', ['--auto' => true])->assertSuccessful();
});

it('promotes all entries past retention period', function (): void {
    $entries = [
        [
            'id' => 'old-1',
            'title' => 'Old Entry',
            'content' => 'Content.',
            'section' => 'Notes',
            'category' => null,
            'tags' => [],
            'priority' => 'medium',
            'confidence' => 50,
            'date' => '2026-01-01',
        ],
    ];

    $this->dailyLogService->shouldReceive('getRetentionDays')->andReturn(7);
    $this->dailyLogService->shouldReceive('getPromotableEntries')->with(7)->andReturn($entries);
    $this->qdrantService->shouldReceive('upsert')->once()->andReturn(true);
    $this->dailyLogService->shouldReceive('removeEntry')->with('2026-01-01', 'old-1')->andReturn(true);

    $this->artisan('promote', ['--all' => true])->assertSuccessful();
});

it('handles no entries past retention period', function (): void {
    $this->dailyLogService->shouldReceive('getRetentionDays')->andReturn(7);
    $this->dailyLogService->shouldReceive('getPromotableEntries')->with(7)->andReturn([]);

    $this->artisan('promote', ['--all' => true])->assertSuccessful();
});

it('supports custom retention period override', function (): void {
    $this->dailyLogService->shouldReceive('getPromotableEntries')->with(14)->andReturn([]);

    $this->artisan('promote', ['--all' => true, '--retention' => 14])->assertSuccessful();
});

it('supports dry-run mode for --id', function (): void {
    $entry = [
        'id' => 'dry-run-1',
        'title' => 'Dry Run Entry',
        'content' => 'Content.',
        'section' => 'Notes',
        'category' => null,
        'tags' => [],
        'priority' => 'medium',
        'confidence' => 50,
    ];

    $this->dailyLogService->shouldReceive('listDailyLogs')->andReturn(['2026-02-10']);
    $this->dailyLogService->shouldReceive('readDailyLog')->with('2026-02-10')->andReturn([$entry]);
    $this->qdrantService->shouldNotReceive('upsert');
    $this->dailyLogService->shouldNotReceive('removeEntry');

    $this->artisan('promote', ['--id' => 'dry-run-1', '--dry-run' => true])->assertSuccessful();
});

it('supports dry-run mode for --date', function (): void {
    $entries = [
        [
            'id' => 'e1',
            'title' => 'Entry',
            'content' => 'Content.',
            'section' => 'Notes',
            'category' => null,
            'tags' => [],
            'priority' => 'medium',
            'confidence' => 50,
        ],
    ];

    $this->dailyLogService->shouldReceive('readDailyLog')->with('2026-02-10')->andReturn($entries);
    $this->qdrantService->shouldNotReceive('upsert');
    $this->dailyLogService->shouldNotReceive('removeEntry');

    $this->artisan('promote', ['--date' => '2026-02-10', '--dry-run' => true])->assertSuccessful();
});

it('supports dry-run mode for --auto', function (): void {
    $entries = [
        [
            'id' => 'a1',
            'title' => 'Auto',
            'content' => 'Content.',
            'section' => 'Notes',
            'category' => 'testing',
            'tags' => [],
            'priority' => 'medium',
            'confidence' => 85,
            'date' => '2026-02-10',
        ],
    ];

    $this->dailyLogService->shouldReceive('getAutoPromotableEntries')->andReturn($entries);
    $this->qdrantService->shouldNotReceive('upsert');
    $this->dailyLogService->shouldNotReceive('removeEntry');

    $this->artisan('promote', ['--auto' => true, '--dry-run' => true])->assertSuccessful();
});

it('supports dry-run mode for --all', function (): void {
    $entries = [
        [
            'id' => 'p1',
            'title' => 'Promotable',
            'content' => 'Content.',
            'section' => 'Notes',
            'category' => null,
            'tags' => [],
            'priority' => 'medium',
            'confidence' => 50,
            'date' => '2026-01-01',
        ],
    ];

    $this->dailyLogService->shouldReceive('getRetentionDays')->andReturn(7);
    $this->dailyLogService->shouldReceive('getPromotableEntries')->with(7)->andReturn($entries);
    $this->qdrantService->shouldNotReceive('upsert');
    $this->dailyLogService->shouldNotReceive('removeEntry');

    $this->artisan('promote', ['--all' => true, '--dry-run' => true])->assertSuccessful();
});

it('handles upsert failure during promotion', function (): void {
    $entry = [
        'id' => 'fail-1',
        'title' => 'Fail Entry',
        'content' => 'Content.',
        'section' => 'Notes',
        'category' => null,
        'tags' => [],
        'priority' => 'medium',
        'confidence' => 50,
    ];

    $this->dailyLogService->shouldReceive('listDailyLogs')->andReturn(['2026-02-10']);
    $this->dailyLogService->shouldReceive('readDailyLog')->with('2026-02-10')->andReturn([$entry]);
    $this->qdrantService->shouldReceive('upsert')->once()->andReturn(false);

    $this->artisan('promote', ['--id' => 'fail-1'])->assertFailed();
});

it('handles upsert exception during promotion', function (): void {
    $entry = [
        'id' => 'exc-1',
        'title' => 'Exception Entry',
        'content' => 'Content.',
        'section' => 'Notes',
        'category' => null,
        'tags' => [],
        'priority' => 'medium',
        'confidence' => 50,
    ];

    $this->dailyLogService->shouldReceive('listDailyLogs')->andReturn(['2026-02-10']);
    $this->dailyLogService->shouldReceive('readDailyLog')->with('2026-02-10')->andReturn([$entry]);
    $this->qdrantService->shouldReceive('upsert')->once()->andThrow(new RuntimeException('Connection failed'));

    $this->artisan('promote', ['--id' => 'exc-1'])->assertFailed();
});

it('shows auto-promote hint when eligible entries exist', function (): void {
    $this->dailyLogService->shouldReceive('getRetentionDays')->andReturn(7);
    $this->dailyLogService->shouldReceive('listDailyLogs')->andReturn(['2026-02-10']);
    $this->dailyLogService->shouldReceive('getPromotableEntries')->with(7)->andReturn([]);
    $this->dailyLogService->shouldReceive('getAutoPromotableEntries')->andReturn([
        ['id' => 'a1', 'title' => 'Auto', 'date' => '2026-02-10'],
    ]);
    $this->dailyLogService->shouldReceive('readDailyLog')
        ->with('2026-02-10')
        ->andReturn([['id' => 'a1']]);

    $this->artisan('promote')->assertSuccessful();
});
