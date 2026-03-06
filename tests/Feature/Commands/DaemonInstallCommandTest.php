<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;

describe('daemon:install command', function (): void {
    it('shows status without error on non-systemd systems', function (): void {
        Process::fake([
            'systemctl list-timers --no-pager 2>/dev/null' => Process::result(output: '', exitCode: 1),
        ]);

        $this->artisan('daemon:install', ['--status' => true])
            ->assertSuccessful();
    });

    it('shows knowledge timers when present', function (): void {
        Process::fake([
            'systemctl list-timers --no-pager 2>/dev/null' => Process::result(
                output: "NEXT                        LEFT          LAST                        PASSED       UNIT                        ACTIVATES\nThu 2026-03-06 18:00:00 UTC 2h left       Thu 2026-03-06 12:00:00 UTC 3h ago       knowledge-reindex.timer     knowledge-reindex.service\n",
                exitCode: 0,
            ),
        ]);

        $this->artisan('daemon:install', ['--status' => true])
            ->assertSuccessful();
    });

    it('shows warning when no timers found', function (): void {
        Process::fake([
            'systemctl list-timers --no-pager 2>/dev/null' => Process::result(
                output: "NEXT LEFT LAST PASSED UNIT ACTIVATES\n0 timers listed.\n",
                exitCode: 0,
            ),
        ]);

        $this->artisan('daemon:install', ['--status' => true])
            ->assertSuccessful();
    });
});
