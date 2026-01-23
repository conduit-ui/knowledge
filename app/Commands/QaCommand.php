<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\OllamaService;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class QaCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'qa
                            {--fix : Auto-fix code style issues with Pint}
                            {--all : Show all issues instead of just the first}
                            {--raw : Show raw error without AI processing}
                            {--level=9 : PHPStan level (default: 9)}
                            {--skip-tests : Skip running tests}
                            {--skip-phpstan : Skip PHPStan analysis}
                            {--skip-pint : Skip Pint analysis}
                            {--no-ai : Skip Ollama suggestion}
                            {--timeout=300 : Timeout in seconds for each check}';

    /**
     * @var string
     */
    protected $description = 'Run QA checks and show first issue with AI-suggested fix';

    public function handle(): int
    {
        $timeout = (int) $this->option('timeout');

        // 1. Try Pint first
        if ($this->option('skip-pint') !== true) {
            $result = $this->runCheck('pint', $timeout);
            if ($result !== null) {
                return $this->reportIssue('pint', $result);
            }
        }

        // 2. Try Tests
        if ($this->option('skip-tests') !== true) {
            $result = $this->runCheck('test', $timeout);
            if ($result !== null) {
                return $this->reportIssue('test', $result);
            }
        }

        // 3. Try PHPStan
        if ($this->option('skip-phpstan') !== true) {
            $result = $this->runCheck('phpstan', $timeout);
            if ($result !== null) {
                return $this->reportIssue('phpstan', $result);
            }
        }

        $this->info('All QA checks passed.');

        return self::SUCCESS;
    }

    private function runCheck(string $type, int $timeout): ?string
    {
        $label = match ($type) {
            'pint' => 'Code style',
            'test' => 'Tests',
            'phpstan' => 'Static analysis',
            default => $type,
        };

        $issue = null;

        $this->task($label, function () use ($type, $timeout, &$issue): bool {
            $process = $this->buildProcess($type);
            $process->setTimeout($timeout);

            try {
                $process->run();

                if (! $process->isSuccessful()) {
                    $output = $process->getOutput();
                    $issue = $output !== '' ? $output : $process->getErrorOutput();

                    return false;
                }

                return true;
            } catch (\Throwable $e) {
                $issue = $e->getMessage();

                return false;
            }
        });

        return $issue;
    }

    private function buildProcess(string $type): Process
    {
        $projectRoot = base_path();

        return match ($type) {
            'pint' => new Process([
                $projectRoot.'/vendor/bin/pint',
                '--test',
                '--config='.$projectRoot.'/pint.json',
                $projectRoot.'/app',
                $projectRoot.'/config',
                $projectRoot.'/tests',
            ], $projectRoot),
            'test' => new Process([$projectRoot.'/vendor/bin/pest', '--parallel'], $projectRoot),
            'phpstan' => $this->buildPhpstanProcess(),
            default => throw new \InvalidArgumentException("Unknown check type: {$type}"),
        };
    }

    private function buildPhpstanProcess(): Process
    {
        /** @var int|string $level */
        $level = $this->option('level');
        $level = is_numeric($level) ? (int) $level : 9;

        $args = [
            'vendor/bin/phpstan',
            'analyse',
            '--level='.$level,
            '--no-progress',
            '--error-format=raw',
        ];

        if (file_exists(base_path('phpstan.neon'))) {
            $args[] = '--configuration='.base_path('phpstan.neon');
        }

        return new Process($args, base_path());
    }

    private function reportIssue(string $type, string $issue): int
    {
        $this->newLine();

        // --all: Show everything
        if ($this->option('all') === true) {
            $this->line($issue);

            return self::FAILURE;
        }

        // --raw: Show raw issue
        if ($this->option('raw') === true) {
            $this->line($issue);

            return self::FAILURE;
        }

        // Extract first meaningful issue
        $firstIssue = $this->extractFirstIssue($type, $issue);

        // --no-ai: Show extracted issue without AI
        if ($this->option('no-ai') === true) {
            $this->line($firstIssue);

            return self::FAILURE;
        }

        // Default: Get AI suggestion
        $this->task('AI suggestion', function () use ($type, $firstIssue, &$suggestion): bool {
            $suggestion = $this->getAiSuggestion($type, $firstIssue);

            return $suggestion !== '';
        });

        $this->newLine();
        $this->line($suggestion ?? $firstIssue);

        return self::FAILURE;
    }

    private function extractFirstIssue(string $type, string $output): string
    {
        $lines = array_values(array_filter(
            explode("\n", $output),
            fn ($l): bool => trim($l) !== ''
        ));

        return match ($type) {
            'pint' => $this->extractPintIssue($lines),
            'test' => $this->extractTestIssue($lines),
            'phpstan' => $this->extractPhpstanIssue($lines),
            default => $lines[0] ?? $output,
        };
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function extractPintIssue(array $lines): string
    {
        foreach ($lines as $line) {
            if (str_contains($line, '.php')) {
                return trim($line);
            }
        }

        return implode("\n", array_slice($lines, 0, 5));
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function extractTestIssue(array $lines): string
    {
        $capture = false;
        $issue = [];

        foreach ($lines as $line) {
            if (str_contains($line, 'FAILED') || str_contains($line, 'Error')) {
                $capture = true;
            }
            if ($capture) {
                $issue[] = $line;
                if (count($issue) >= 10) {
                    break;
                }
            }
        }

        return $issue !== [] ? implode("\n", $issue) : implode("\n", array_slice($lines, 0, 10));
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function extractPhpstanIssue(array $lines): string
    {
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '' && ! str_contains($trimmed, 'Ignored error pattern')) {
                return $trimmed;
            }
        }

        return $lines[0] ?? '';
    }

    private function getAiSuggestion(string $type, string $issue): string
    {
        /** @var array<string, mixed> $config */
        $config = config('search.ollama', []);

        $ollama = new OllamaService(
            host: is_string($config['host'] ?? null) ? $config['host'] : 'localhost',
            port: is_int($config['port'] ?? null) ? $config['port'] : 11434,
            model: is_string($config['model'] ?? null) ? $config['model'] : 'llama3.2:3b',
            timeout: is_int($config['timeout'] ?? null) ? $config['timeout'] : 30,
        );

        $typeLabel = match ($type) {
            'pint' => 'code style',
            'test' => 'test failure',
            'phpstan' => 'static analysis',
            default => $type,
        };

        $prompt = <<<PROMPT
You are a PHP/Laravel expert. Analyze this {$typeLabel} issue and provide a concise fix.

Issue:
{$issue}

Respond with ONLY:
1. One line explaining the problem
2. The exact code change needed (if applicable)

Be direct and actionable. No explanations beyond what's needed.
PROMPT;

        return $ollama->generate($prompt);
    }
}
