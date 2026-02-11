<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\ResolvesProject;
use App\Services\PatternDetectorService;
use App\Services\QdrantService;
use App\Services\ThemeClassifierService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class InsightsCommand extends Command
{
    use ResolvesProject;

    protected $signature = 'insights
                            {--themes : Show theme classification analysis}
                            {--patterns : Show pattern detection analysis}
                            {--classify-entry= : Classify a specific entry by ID}
                            {--limit=100 : Number of entries to analyze}
                            {--project= : Override project namespace}
                            {--global : Search across all projects}';

    protected $description = 'Analyze knowledge base for themes, patterns, and insights';

    public function handle(
        QdrantService $qdrant,
        ThemeClassifierService $themeClassifier,
        PatternDetectorService $patternDetector
    ): int {
        $themes = (bool) $this->option('themes');
        $patterns = (bool) $this->option('patterns');
        $classifyEntry = $this->option('classify-entry');
        $limit = (int) ($this->option('limit') ?? 100);

        // If classifying a specific entry
        if (is_string($classifyEntry) && $classifyEntry !== '') {
            return $this->classifySingleEntry($qdrant, $themeClassifier, $classifyEntry);
        }

        // Default: run both analyses
        $runAll = ! $themes && ! $patterns;

        // Fetch entries
        $entries = spin(
            fn (): \Illuminate\Support\Collection => $qdrant->scroll([], $limit),
            'Loading knowledge entries...'
        );

        if ($entries->isEmpty()) {
            warning('No entries found in knowledge base.');

            return self::SUCCESS;
        }

        info("Analyzing {$entries->count()} entries...\n");

        if ($runAll || $themes) {
            $this->showThemeAnalysis($entries, $themeClassifier);
        }

        if ($runAll || $patterns) {
            $this->showPatternAnalysis($entries, $patternDetector);
        }

        return self::SUCCESS;
    }

    /**
     * Classify a single entry and show results.
     */
    private function classifySingleEntry(
        QdrantService $qdrant,
        ThemeClassifierService $classifier,
        string $id
    ): int {
        $entry = $qdrant->getById($id);

        if ($entry === null) {
            warning("Entry not found: {$id}");

            return self::FAILURE;
        }

        $result = $classifier->classify($entry);

        info("Classification for: {$entry['title']}\n");

        // Show all theme scores
        $rows = [];
        foreach ($result['all_scores'] as $theme => $score) {
            $bar = str_repeat('█', (int) ($score * 20));
            $isSelected = $theme === $result['theme'] ? ' ✓' : '';
            $rows[] = [$theme.$isSelected, $bar.' '.round($score * 100).'%'];
        }

        table(['Theme', 'Score'], $rows);

        if ($result['theme'] !== null) {
            note("Best match: {$result['theme']} ({$result['confidence']} confidence)");
        } else {
            warning('No strong theme match detected');
        }

        return self::SUCCESS;
    }

    /**
     * Show theme classification analysis.
     *
     * @param  \Illuminate\Support\Collection<int, array{title: string, content: string, tags?: array<string>, category?: string|null}>  $entries
     */
    private function showThemeAnalysis(
        \Illuminate\Support\Collection $entries,
        ThemeClassifierService $classifier
    ): void {
        $this->line('<fg=cyan>═══ Theme Analysis ═══</>');
        $this->line('');

        $result = spin(
            fn (): array => $classifier->classifyBatch($entries),
            'Classifying entries by theme...'
        );

        $targets = $classifier->getThemeTargets();
        $total = $result['total'];

        $rows = [];
        foreach ($result['distribution'] as $theme => $count) {
            $percent = $total > 0 ? round(($count / $total) * 100) : 0;
            $target = $targets[$theme]['target'] * 100;

            // Progress bar
            $filled = min(20, max(0, (int) ($percent / 5)));
            $bar = str_repeat('█', $filled);
            $bar .= str_repeat('░', max(0, 20 - $filled));

            // Status indicator
            $status = $percent >= $target ? '✓' : '○';

            $rows[] = [
                $theme,
                "{$count}",
                "[{$bar}] {$percent}%",
                "{$target}%",
                $status,
            ];
        }

        // Add unclassified row
        $unclassifiedPercent = $total > 0 ? round(($result['unclassified'] / $total) * 100) : 0;
        $rows[] = [
            '<fg=gray>unclassified</>',
            (string) $result['unclassified'],
            "{$unclassifiedPercent}%",
            '-',
            '',
        ];

        table(['Theme', 'Count', 'Coverage', 'Target', ''], $rows);

        $this->line('');
    }

    /**
     * Show pattern detection analysis.
     *
     * @param  \Illuminate\Support\Collection<int, array{id: string|int, title: string, content: string, tags?: array<string>, category?: string|null, created_at?: string}>  $entries
     */
    private function showPatternAnalysis(
        \Illuminate\Support\Collection $entries,
        PatternDetectorService $detector
    ): void {
        $this->line('<fg=cyan>═══ Pattern Analysis ═══</>');
        $this->line('');

        $patterns = spin(
            fn (): array => $detector->detect($entries),
            'Detecting patterns...'
        );

        // Frequent topics
        if (! empty($patterns['frequent_topics'])) {
            $this->line('<fg=yellow>Frequent Topics:</>');
            $rows = [];
            foreach (array_slice($patterns['frequent_topics'], 0, 10, true) as $topic => $count) {
                $bar = str_repeat('▓', min(20, $count));
                $rows[] = [$topic, $bar.' '.$count];
            }
            table(['Topic', 'Frequency'], $rows);
            $this->line('');
        }

        // Recurring tags
        if (! empty($patterns['recurring_tags'])) {
            $this->line('<fg=yellow>Recurring Tags:</>');
            $tagList = [];
            foreach (array_slice($patterns['recurring_tags'], 0, 10, true) as $tag => $count) {
                $tagList[] = "{$tag} ({$count})";
            }
            $this->line('  '.implode(', ', $tagList));
            $this->line('');
        }

        // Project associations
        if (! empty($patterns['project_associations'])) {
            $this->line('<fg=yellow>Active Projects:</>');
            $rows = [];
            foreach ($patterns['project_associations'] as $project => $count) {
                $bar = str_repeat('▓', min(15, $count));
                $rows[] = [$project, $bar.' '.$count];
            }
            table(['Project', 'Mentions'], $rows);
            $this->line('');
        }

        // Category distribution
        $this->line('<fg=yellow>Category Distribution:</>');
        $catRows = [];
        arsort($patterns['category_distribution']);
        foreach ($patterns['category_distribution'] as $cat => $count) {
            $catRows[] = [$cat ?? 'none', (string) $count];
        }
        table(['Category', 'Count'], $catRows);
        $this->line('');

        // Insights
        if (! empty($patterns['insights'])) {
            $this->line('<fg=cyan>═══ Insights ═══</>');
            foreach ($patterns['insights'] as $insight) {
                $this->line("  • {$insight}");
            }
            $this->line('');
        }
    }
}
