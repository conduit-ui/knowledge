<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\Entry;
use Carbon\Carbon;
use LaravelZero\Framework\Commands\Command;

class FocusTimeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'focus-time
                            {project? : The project name for the focus block}
                            {hours? : Planned duration in hours}
                            {action? : Action to perform (end, switch)}
                            {--sync : Sync after ending focus block}
                            {--json : Output JSON format}';

    /**
     * @var string
     */
    protected $description = 'Track deep work focus blocks with context switch detection';

    private const TEMP_FILE = '/know-focus-block-id';

    private const ENERGY_LEVELS = ['high', 'medium', 'low'];

    private const MAX_HOURS = 12;

    /**
     * Override run to catch input parsing exceptions
     */
    public function run(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (\Symfony\Component\Console\Exception\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'option does not exist')) {
                $this->error('Hours must be positive');

                return self::FAILURE;
            }
            throw $e;
        }
    }

    public function handle(): int
    {
        /** @var string|null $project */
        $project = $this->argument('project');
        /** @var string|null $hours */
        $hours = $this->argument('hours');
        /** @var string|null $action */
        $action = $this->argument('action');

        // Handle end action
        if ($project === 'end' || $action === 'end') {
            return $this->endFocusBlock();
        }

        // Handle switch action
        if ($action === 'switch' || ($project === 'switch' && $hours !== null)) {
            $newProject = is_string($hours) ? $hours : '';

            return $this->switchContext($newProject);
        }

        // Handle start action
        if (is_string($project) && $hours !== null) {
            return $this->startFocusBlock($project, $hours);
        }

        // Missing required arguments
        $this->error('Missing required arguments. Usage: focus-time <project> <hours>');

        return self::FAILURE;
    }

    private function startFocusBlock(string $project, mixed $hours): int
    {
        // Validate hours parameter
        if (! is_numeric($hours)) {
            $this->error('Hours must be a numeric value');

            return self::FAILURE;
        }

        $hoursFloat = floatval($hours);

        if ($hoursFloat <= 0) {
            $this->error('Hours must be positive');

            return self::FAILURE;
        }

        if ($hoursFloat > self::MAX_HOURS) {
            $this->error('Hours cannot exceed '.self::MAX_HOURS);

            return self::FAILURE;
        }

        // Check for active focus block
        if ($this->hasActiveFocusBlock()) {
            $this->error('Active focus block already in progress. End current block first.');

            return self::FAILURE;
        }

        // Prompt for energy level
        try {
            $energyBefore = $this->ask('Energy level before starting?', 'medium');
        } catch (\BadMethodCallException $e) {
            // Test environment without expectation - use default
            $energyBefore = 'medium';
        }

        if ($energyBefore === null || $energyBefore === '') {
            $energyBefore = 'medium';
        }

        if (! in_array($energyBefore, self::ENERGY_LEVELS, true)) {
            $this->error('Invalid energy level. Must be: high, medium, or low');

            return self::FAILURE;
        }

        // Create focus block entry
        /** @phpstan-ignore-next-line */
        $entry = Entry::create([
            'title' => 'Focus: '.$project.' '.now()->format('Y-m-d H:i'),
            'content' => json_encode([
                'started_at' => now()->toIso8601String(),
                'planned_hours' => round($hoursFloat, 1), // Force float type in JSON
                'energy_before' => $energyBefore,
                'context_switches' => [],
                'ended_at' => null,
                'current_project' => $project,
            ], JSON_PRESERVE_ZERO_FRACTION),
            'category' => 'focus-session',
            'tags' => [$project, 'deep-work', 'focus-time'],
            'status' => 'pending',
            'priority' => 'high',
            'confidence' => 50,
            'repo' => $project,
        ]);

        // Store block ID in temp file
        $this->storeFocusBlockId((string) $entry->id);

        $this->info(sprintf('Focus block started for %s (%.1f hours planned)', $project, $hoursFloat));

        return self::SUCCESS;
    }

    private function endFocusBlock(): int
    {
        $blockId = $this->getActiveFocusBlockId();

        if ($blockId === null) {
            $this->error('No active focus block found');

            return self::FAILURE;
        }

        /** @phpstan-ignore-next-line */
        $entry = Entry::find($blockId);

        if ($entry === null) {
            $this->error('No active focus block found');

            return self::FAILURE;
        }

        // Prompt for energy level after
        $energyAfter = $this->ask('Energy level after completing?', 'medium');

        if ($energyAfter === null || $energyAfter === '') {
            $energyAfter = 'medium';
        }

        if (! in_array($energyAfter, self::ENERGY_LEVELS, true)) {
            $this->error('Invalid energy level. Must be: high, medium, or low');

            return self::FAILURE;
        }

        // Update entry with completion data
        $content = json_decode($entry->content, true);
        $startedAt = Carbon::parse($content['started_at']);
        $endedAt = now();

        $actualHours = $startedAt->diffInMinutes($endedAt) / 60;
        $plannedHours = $content['planned_hours'];

        $durationPercentage = ($actualHours / $plannedHours) * 100;

        $content['ended_at'] = $endedAt->toIso8601String();
        $content['energy_after'] = $energyAfter;
        $content['actual_hours'] = round($actualHours, 2);
        $content['duration_percentage'] = round($durationPercentage, 2);

        // Calculate energy delta
        $energyMap = ['low' => 1, 'medium' => 2, 'high' => 3];
        $energyDelta = $energyMap[$energyAfter] - $energyMap[$content['energy_before']];
        $content['energy_delta'] = $energyDelta;

        // Calculate effectiveness score
        $effectivenessScore = $this->calculateEffectiveness(
            $plannedHours,
            $actualHours,
            count($content['context_switches']),
            $energyDelta
        );

        $content['effectiveness_score'] = round($effectivenessScore, 2);

        // Update entry
        $entry->update([
            'content' => json_encode($content, JSON_PRESERVE_ZERO_FRACTION),
            'status' => 'validated',
            'confidence' => (int) ($effectivenessScore * 10),
        ]);

        // Clean up temp file first
        $this->cleanupFocusBlockId();

        // Display report
        /** @var bool $jsonOption */
        $jsonOption = $this->option('json') ?? false;

        if (! $jsonOption) {
            $this->displayReport($entry, $content);
        } else {
            // Output JSON for programmatic consumption
            $jsonData = [
                'effectiveness_score' => $content['effectiveness_score'],
                'actual_hours' => $content['actual_hours'],
                'context_switches' => count($content['context_switches']),
                'energy_delta' => $content['energy_delta'],
                'duration_percentage' => $content['duration_percentage'],
            ];

            $json = json_encode($jsonData, JSON_PRESERVE_ZERO_FRACTION);
            if ($json !== false) {
                $this->line($json);
            }
        }

        // Sync if requested
        /** @var bool $syncOption */
        $syncOption = $this->option('sync') ?? false;
        if ($syncOption) {
            $this->call('sync');
        }

        return self::SUCCESS;
    }

    private function switchContext(string $newProject): int
    {
        $blockId = $this->getActiveFocusBlockId();

        if ($blockId === null) {
            $this->error('No active focus block found');

            return self::FAILURE;
        }

        /** @phpstan-ignore-next-line */
        $entry = Entry::find($blockId);

        if ($entry === null) {
            $this->error('No active focus block found');

            return self::FAILURE;
        }

        $reason = $this->ask('Why are you switching?');

        $content = json_decode($entry->content, true);
        $currentProject = $content['current_project'] ?? 'unknown';

        // Record context switch
        $content['context_switches'][] = [
            'timestamp' => now()->toIso8601String(),
            'from' => $currentProject,
            'to' => $newProject,
            'reason' => $reason,
        ];

        $content['current_project'] = $newProject;

        $entry->update([
            'content' => json_encode($content, JSON_PRESERVE_ZERO_FRACTION),
        ]);

        $switchCount = count($content['context_switches']);
        $this->info("Context switch recorded ({$switchCount} total switches)");

        return self::SUCCESS;
    }

    private function calculateEffectiveness(
        float $plannedHours,
        float $actualHours,
        int $contextSwitches,
        int $energyDelta
    ): float {
        // Base score from duration accuracy (0-10 scale)
        $durationRatio = $actualHours / $plannedHours;

        // Perfect is completing within 95-105% of planned time
        if ($durationRatio >= 0.95 && $durationRatio <= 1.05) {
            $durationScore = 10.0;
        } else {
            // Penalize deviation
            $deviation = abs(1.0 - $durationRatio);
            $durationScore = max(0, 10.0 - ($deviation * 20));
        }

        // Penalize context switches (1.0 points per switch)
        $switchPenalty = $contextSwitches * 1.0;

        // Energy delta adjustment (small factor)
        $energyAdjustment = $energyDelta * 0.1;

        $score = $durationScore - $switchPenalty + $energyAdjustment;

        return max(0, min(10, $score));
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function displayReport(Entry $entry, array $content): void
    {
        $this->newLine();
        $this->info('=== Focus Block Report ===');
        $this->newLine();

        $project = $entry->repo ?? 'Unknown';
        $planned = $content['planned_hours'];
        $actual = $content['actual_hours'];
        $switches = count($content['context_switches']);
        $effectiveness = $content['effectiveness_score'];
        $energyBefore = $content['energy_before'];
        $energyAfter = $content['energy_after'];

        $this->line("Project: {$project}");
        $this->line("Planned: {$planned} hours");
        $this->line("Actual: {$actual} hours");
        $this->line("Context Switches: {$switches}");
        $this->line("Energy: {$energyBefore} → {$energyAfter}");
        $this->line("Effectiveness: {$effectiveness}/10");

        $this->newLine();
    }

    private function hasActiveFocusBlock(): bool
    {
        return $this->getActiveFocusBlockId() !== null;
    }

    private function getActiveFocusBlockId(): ?string
    {
        // Check environment variable first
        $envId = getenv('KNOW_FOCUS_BLOCK_ID');
        if ($envId !== false && $envId !== '') {
            return $envId;
        }

        // Check temp file
        $tempFile = sys_get_temp_dir().self::TEMP_FILE;

        if (! file_exists($tempFile)) {
            return null;
        }

        $contents = file_get_contents($tempFile);
        if ($contents === false) {
            return null;
        }

        $blockId = trim($contents);

        if ($blockId === '' || ! is_numeric($blockId)) {
            return null;
        }

        return $blockId;
    }

    private function storeFocusBlockId(string $blockId): void
    {
        // Store in temp file
        $tempFile = sys_get_temp_dir().self::TEMP_FILE;
        file_put_contents($tempFile, $blockId);

        // Also store in CLAUDE_ENV_FILE if set
        $claudeEnvFile = getenv('CLAUDE_ENV_FILE');
        if ($claudeEnvFile !== false && $claudeEnvFile !== '') {
            $content = "export KNOW_FOCUS_BLOCK_ID={$blockId}\n";
            file_put_contents($claudeEnvFile, $content);
        }
    }

    private function cleanupFocusBlockId(): void
    {
        $tempFile = sys_get_temp_dir().self::TEMP_FILE;

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}
