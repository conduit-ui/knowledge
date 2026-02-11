<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Classifies knowledge entries into strategic themes.
 *
 * Themes are based on Jordan's vision strategic priorities:
 * - Quality Automation (78%) - automated code review, testing, certification
 * - Developer Experience (65%) - GitHub CLI, natural language interfaces
 * - Context Continuity (35%) - knowledge management, session memory
 * - Integrated Infrastructure (10%) - physical systems, property, homelab
 */
class ThemeClassifierService
{
    /**
     * Theme definitions with keywords and semantic markers.
     *
     * @var array<string, array{keywords: array<string>, weight: float}>
     */
    private const THEMES = [
        'quality-automation' => [
            'keywords' => [
                'test', 'testing', 'phpstan', 'pint', 'pest', 'coverage',
                'sentinel', 'gate', 'certification', 'certified', 'quality',
                'review', 'pr review', 'code review', 'static analysis',
                'phpunit', 'lint', 'linting', 'ci', 'ci/cd', 'pipeline',
                'automated', 'automation', 'synapse', 'pre-commit', 'hook',
            ],
            'weight' => 0.78,
        ],
        'developer-experience' => [
            'keywords' => [
                'github', 'cli', 'command', 'conduit', 'issue', 'pr',
                'pull request', 'natural language', 'developer', 'dx',
                'interface', 'api', 'endpoint', 'saloon', 'http',
                'connector', 'package', 'composer', 'npm', 'workflow',
            ],
            'weight' => 0.65,
        ],
        'context-continuity' => [
            'keywords' => [
                'knowledge', 'memory', 'session', 'context', 'capture',
                'synthesis', 'synthesize', 'digest', 'checkpoint',
                'qdrant', 'vector', 'embedding', 'semantic', 'search',
                'continuity', 'remember', 'recall', 'history', 'whisper',
            ],
            'weight' => 0.35,
        ],
        'integrated-infrastructure' => [
            'keywords' => [
                'infrastructure', 'server', 'homelab', 'docker',
                'podman', 'container', 'deploy', 'deployment', 'hosting',
                'property', 'physical', 'hardware', 'network', 'tailscale',
                'nginx', 'redis', 'database', 'postgres', 'mysql',
            ],
            'weight' => 0.10,
        ],
    ];

    /**
     * Classify an entry into themes.
     *
     * @param  array{title: string, content: string, tags?: array<string>, category?: string|null}  $entry
     * @return array{theme: string|null, confidence: float, all_scores: array<string, float>}
     */
    public function classify(array $entry): array
    {
        $text = strtolower(
            ($entry['title'] ?? '').' '.
            ($entry['content'] ?? '').' '.
            implode(' ', $entry['tags'] ?? []).' '.
            ($entry['category'] ?? '')
        );

        $scores = [];

        foreach (self::THEMES as $theme => $config) {
            $score = $this->calculateScore($text, $config['keywords']);
            $scores[$theme] = $score;
        }

        // Find the best match
        $bestTheme = null;
        $bestScore = 0.0;

        foreach ($scores as $theme => $score) {
            if ($score > $bestScore && $score >= 0.1) { // Minimum threshold
                $bestScore = $score;
                $bestTheme = $theme;
            }
        }

        return [
            'theme' => $bestTheme,
            'confidence' => round($bestScore, 3),
            'all_scores' => array_map(fn ($s): float => round($s, 3), $scores),
        ];
    }

    /**
     * Classify multiple entries and return theme distribution.
     *
     * @param  iterable<array{title: string, content: string, tags?: array<string>, category?: string|null}>  $entries
     * @return array{distribution: array<string, int>, unclassified: int, total: int}
     */
    public function classifyBatch(iterable $entries): array
    {
        $distribution = [
            'quality-automation' => 0,
            'developer-experience' => 0,
            'context-continuity' => 0,
            'integrated-infrastructure' => 0,
        ];
        $unclassified = 0;
        $total = 0;

        foreach ($entries as $entry) {
            $result = $this->classify($entry);
            $total++;

            if ($result['theme'] !== null) {
                $distribution[$result['theme']]++;
            } else {
                $unclassified++;
            }
        }

        return [
            'distribution' => $distribution,
            'unclassified' => $unclassified,
            'total' => $total,
        ];
    }

    /**
     * Get theme progress based on vision targets.
     *
     * @return array<string, array{target: float, description: string}>
     */
    public function getThemeTargets(): array
    {
        return [
            'quality-automation' => [
                'target' => 0.78,
                'description' => 'Automated code review and quality certification',
            ],
            'developer-experience' => [
                'target' => 0.65,
                'description' => 'Natural language GitHub interface',
            ],
            'context-continuity' => [
                'target' => 0.35,
                'description' => 'Agent memory and session continuity',
            ],
            'integrated-infrastructure' => [
                'target' => 0.10,
                'description' => 'Physical and digital infrastructure integration',
            ],
        ];
    }

    /**
     * Calculate keyword match score.
     *
     * @param  array<string>  $keywords
     */
    private function calculateScore(string $text, array $keywords): float
    {
        $matches = 0;
        $totalKeywords = count($keywords);

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                $matches++;

                // Bonus for exact word boundaries
                if (preg_match('/\b'.preg_quote($keyword, '/').'\b/i', $text)) {
                    $matches += 0.5;
                }
            }
        }

        // Normalize: more matches = higher score, max around 1.0
        return min(1.0, $matches / ($totalKeywords * 0.3));
    }
}
