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

    it('uninstalls all knowledge timers with --uninstall flag', function (): void {
        Process::fake([
            'sudo systemctl stop knowledge-enhance.timer' => Process::result(exitCode: 0),
            'sudo systemctl disable knowledge-enhance.timer' => Process::result(exitCode: 0),
            'sudo rm -f /etc/systemd/system/knowledge-enhance.service /etc/systemd/system/knowledge-enhance.timer' => Process::result(exitCode: 0),
            'sudo systemctl stop knowledge-sync.timer' => Process::result(exitCode: 0),
            'sudo systemctl disable knowledge-sync.timer' => Process::result(exitCode: 0),
            'sudo rm -f /etc/systemd/system/knowledge-sync.service /etc/systemd/system/knowledge-sync.timer' => Process::result(exitCode: 0),
            'sudo systemctl stop knowledge-reindex.timer' => Process::result(exitCode: 0),
            'sudo systemctl disable knowledge-reindex.timer' => Process::result(exitCode: 0),
            'sudo rm -f /etc/systemd/system/knowledge-reindex.service /etc/systemd/system/knowledge-reindex.timer' => Process::result(exitCode: 0),
            'sudo systemctl daemon-reload' => Process::result(exitCode: 0),
        ]);

        $this->artisan('daemon:install', ['--uninstall' => true])
            ->assertSuccessful();
    });

    it('installs timers and runs daemon-reload', function (): void {
        Process::fake([
            '*' => Process::result(exitCode: 0),
            'systemctl list-timers --no-pager 2>/dev/null' => Process::result(
                output: "NEXT LEFT LAST PASSED UNIT ACTIVATES\nThu 2026-01-01 00:00:00 UTC 1h left Thu 2026-01-01 00:00:00 UTC 1h ago knowledge-enhance.timer knowledge-enhance.service\n",
                exitCode: 0,
            ),
        ]);

        $this->artisan('daemon:install')
            ->assertSuccessful();
    });
});
