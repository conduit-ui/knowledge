<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

class DaemonInstallCommand extends Command
{
    protected $signature = 'daemon:install
                            {--uninstall : Remove all knowledge timers}
                            {--status : Show timer status}';

    protected $description = 'Install or manage systemd timers for knowledge daemons';

    /** @var array<string, array{description: string, command: string, interval: string, boot_delay: string, timeout: int, start_timeout: int}> */
    private const UNITS = [
        'knowledge-enhance' => [
            'description' => 'Knowledge enhancement worker (Ollama auto-tagging)',
            'command' => 'enhance:worker',
            'interval' => '15min',
            'boot_delay' => '2min',
            'timeout' => 30,
            'start_timeout' => 300,
        ],
        'knowledge-sync' => [
            'description' => 'Knowledge remote sync (push and pull)',
            'command' => 'sync:remote',
            'interval' => '30min',
            'boot_delay' => '5min',
            'timeout' => 60,
            'start_timeout' => 300,
        ],
        'knowledge-reindex' => [
            'description' => 'Knowledge code re-indexing and vectorization',
            'command' => 'reindex:all',
            'interval' => '6h',
            'boot_delay' => '10min',
            'timeout' => 120,
            'start_timeout' => 1800,
        ],
    ];

    public function handle(): int
    {
        if ((bool) $this->option('status')) {
            return $this->showStatus();
        }

        if ((bool) $this->option('uninstall')) {
            return $this->uninstall();
        }

        return $this->install();
    }

    private function install(): int
    {
        $user = get_current_user();
        $home = getenv('HOME') !== false ? (string) getenv('HOME') : '/tmp';
        $workDir = base_path();
        $php = PHP_BINARY;

        info("Installing knowledge systemd units for {$user}...");

        foreach (self::UNITS as $name => $unit) {
            $service = $this->buildService($name, $unit, $user, $home, $workDir, $php);
            $timer = $this->buildTimer($name, $unit);

            $tmpService = tempnam(sys_get_temp_dir(), 'kd_');
            $tmpTimer = tempnam(sys_get_temp_dir(), 'kd_');

            // @codeCoverageIgnoreStart
            if ($tmpService === false || $tmpTimer === false) {
                error('Failed to create temp files');

                return self::FAILURE;
            }
            // @codeCoverageIgnoreEnd

            file_put_contents($tmpService, $service);
            file_put_contents($tmpTimer, $timer);

            $servicePath = "/etc/systemd/system/{$name}.service";
            $timerPath = "/etc/systemd/system/{$name}.timer";

            $result = Process::run("sudo cp {$tmpService} {$servicePath} && sudo cp {$tmpTimer} {$timerPath}");

            @unlink($tmpService);
            @unlink($tmpTimer);

            // @codeCoverageIgnoreStart
            if (! $result->successful()) {
                error("Failed to install {$name}: ".$result->errorOutput());

                return self::FAILURE;
            }
            // @codeCoverageIgnoreEnd
        }

        Process::run('sudo systemctl daemon-reload');

        foreach (array_keys(self::UNITS) as $name) {
            Process::run("sudo systemctl enable {$name}.timer");
            Process::run("sudo systemctl start {$name}.timer");
            note("  Started {$name}.timer");
        }

        info('All timers installed and started.');

        return $this->showStatus();
    }

    private function uninstall(): int
    {
        info('Removing knowledge systemd units...');

        foreach (array_keys(self::UNITS) as $name) {
            Process::run("sudo systemctl stop {$name}.timer");
            Process::run("sudo systemctl disable {$name}.timer");
            Process::run("sudo rm -f /etc/systemd/system/{$name}.service /etc/systemd/system/{$name}.timer");
            note("  Removed {$name}");
        }

        Process::run('sudo systemctl daemon-reload');
        info('All knowledge timers removed.');

        return self::SUCCESS;
    }

    private function showStatus(): int
    {
        $result = Process::run('systemctl list-timers --no-pager 2>/dev/null');

        if (! $result->successful()) {
            warning('Could not query systemd timers (not on a systemd system?).');

            return self::SUCCESS;
        }

        $lines = explode("\n", $result->output());
        $relevant = array_filter(
            $lines,
            fn (string $line): bool => str_contains($line, 'knowledge')
                || str_contains($line, 'NEXT')
                || str_contains($line, '---'),
        );

        if ($relevant === []) {
            warning('No knowledge timers found. Run `know daemon:install` to set them up.');

            return self::SUCCESS;
        }

        info('Knowledge timers:');
        foreach ($relevant as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{description: string, command: string, interval: string, boot_delay: string, timeout: int, start_timeout: int}  $unit
     */
    private function buildService(
        string $name,
        array $unit,
        string $user,
        string $home,
        string $workDir,
        string $php,
    ): string {
        return <<<UNIT
        [Unit]
        Description={$unit['description']}
        After=network.target

        [Service]
        Type=oneshot
        User={$user}
        WorkingDirectory={$workDir}
        ExecStart={$php} know {$unit['command']}
        TimeoutStopSec={$unit['timeout']}
        TimeoutStartSec={$unit['start_timeout']}
        Environment=HOME={$home}

        [Install]
        WantedBy=multi-user.target
        UNIT;
    }

    /**
     * @param  array{description: string, command: string, interval: string, boot_delay: string, timeout: int, start_timeout: int}  $unit
     */
    private function buildTimer(string $name, array $unit): string
    {
        return <<<UNIT
        [Unit]
        Description=Run {$name} every {$unit['interval']}

        [Timer]
        OnBootSec={$unit['boot_delay']}
        OnUnitActiveSec={$unit['interval']}
        RandomizedDelaySec=60

        [Install]
        WantedBy=timers.target
        UNIT;
    }
}
