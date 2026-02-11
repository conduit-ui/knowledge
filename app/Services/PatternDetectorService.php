<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Detects recurring patterns and topics in knowledge entries.
 *
 * Identifies:
 * - Frequently mentioned topics
 * - Recurring problems/blockers
 * - Common project associations
 * - Temporal patterns (daily/weekly trends)
 */
class PatternDetectorService
{
    /**
     * Minimum occurrences to be considered a pattern.
     */
    private const MIN_OCCURRENCES = 3;

    /**
     * Stop words to ignore in pattern detection.
     *
     * @var array<string>
     */
    private const STOP_WORDS = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
        'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
        'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need',
        'this', 'that', 'these', 'those', 'it', 'its', 'i', 'we', 'you',
        'they', 'he', 'she', 'my', 'your', 'his', 'her', 'our', 'their',
        'what', 'which', 'who', 'when', 'where', 'why', 'how', 'all', 'each',
        'every', 'both', 'few', 'more', 'most', 'other', 'some', 'such', 'no',
        'not', 'only', 'same', 'so', 'than', 'too', 'very', 'just', 'also',
    ];

    /**
     * Detect patterns from a collection of entries.
     *
     * @param  Collection<int, array{id: string|int, title: string, content: string, tags?: array<string>, category?: string|null, created_at?: string}>  $entries
     * @return array{
     *     frequent_topics: array<string, int>,
     *     recurring_tags: array<string, int>,
     *     project_associations: array<string, int>,
     *     category_distribution: array<string, int>,
     *     insights: array<string>
     * }
     */
    public function detect(Collection $entries): array
    {
        $wordFrequency = [];
        $tagFrequency = [];
        $projectFrequency = [];
        $categoryFrequency = [];

        foreach ($entries as $entry) {
            // Extract words from title and content
            $text = ($entry['title'] ?? '').' '.($entry['content'] ?? '');
            $words = $this->extractSignificantWords($text);

            foreach ($words as $word) {
                $wordFrequency[$word] = ($wordFrequency[$word] ?? 0) + 1;
            }

            // Count tags
            foreach ($entry['tags'] ?? [] as $tag) {
                $tag = strtolower(trim($tag));
                if ($tag !== '' && ! $this->isDateTag($tag)) {
                    $tagFrequency[$tag] = ($tagFrequency[$tag] ?? 0) + 1;
                }
            }

            // Extract project mentions
            $projects = $this->extractProjects($text);
            foreach ($projects as $project) {
                $projectFrequency[$project] = ($projectFrequency[$project] ?? 0) + 1;
            }

            // Count categories
            $category = $entry['category'] ?? 'uncategorized';
            $categoryFrequency[$category] = ($categoryFrequency[$category] ?? 0) + 1;
        }

        // Filter to patterns (>= MIN_OCCURRENCES)
        $frequentTopics = $this->filterByFrequency($wordFrequency);
        $recurringTags = $this->filterByFrequency($tagFrequency);
        $projectAssociations = $this->filterByFrequency($projectFrequency);

        // Generate insights
        $insights = $this->generateInsights(
            $frequentTopics,
            $recurringTags,
            $projectAssociations,
            $categoryFrequency,
            $entries->count()
        );

        return [
            'frequent_topics' => $frequentTopics,
            'recurring_tags' => $recurringTags,
            'project_associations' => $projectAssociations,
            'category_distribution' => $categoryFrequency,
            'insights' => $insights,
        ];
    }

    /**
     * Find entries that match a detected pattern.
     *
     * @param  Collection<int, array{id: string|int, title: string, content: string}>  $entries
     * @return Collection<int, array{id: string|int, title: string, content: string}>
     */
    public function findEntriesMatchingPattern(Collection $entries, string $pattern): Collection
    {
        $pattern = strtolower($pattern);

        return $entries->filter(function (array $entry) use ($pattern): bool {
            $text = strtolower(($entry['title'] ?? '').' '.($entry['content'] ?? ''));

            return str_contains($text, $pattern);
        });
    }

    /**
     * Extract significant words (nouns, verbs) from text.
     *
     * @return array<string>
     */
    private function extractSignificantWords(string $text): array
    {
        // Normalize text
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s\-]/', ' ', $text) ?? $text;

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Filter stop words and short words
        return array_filter($words, fn (string $word): bool => strlen($word) >= 4
            && ! in_array($word, self::STOP_WORDS, true)
            && ! is_numeric($word));
    }

    /**
     * Extract project/repo names from text.
     *
     * @return array<string>
     */
    private function extractProjects(string $text): array
    {
        $projects = [];

        // Match project patterns from config, with fallback generic patterns
        /** @var array<string> $configPatterns */
        $configPatterns = config('search.project_patterns', []);

        $patterns = $configPatterns !== [] ? $configPatterns : [
            '/\b([\w]+-[\w]+(?:-[\w]+)*)\b/',  // hyphenated project names (e.g. my-project)
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $projects[] = strtolower($match);
                }
            }
        }

        return array_unique($projects);
    }

    /**
     * Check if a tag is a date tag (YYYY-MM-DD format).
     */
    private function isDateTag(string $tag): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $tag);
    }

    /**
     * Filter array to only include items meeting minimum frequency.
     *
     * @param  array<string, int>  $frequencies
     * @return array<string, int>
     */
    private function filterByFrequency(array $frequencies): array
    {
        $filtered = array_filter(
            $frequencies,
            fn (int $count): bool => $count >= self::MIN_OCCURRENCES
        );

        arsort($filtered);

        return array_slice($filtered, 0, 20, true);
    }

    /**
     * Generate human-readable insights from patterns.
     *
     * @param  array<string, int>  $topics
     * @param  array<string, int>  $tags
     * @param  array<string, int>  $projects
     * @param  array<string, int>  $categories
     * @return array<string>
     */
    private function generateInsights(
        array $topics,
        array $tags,
        array $projects,
        array $categories,
        int $totalEntries
    ): array {
        $insights = [];

        // Top topic insight
        if ($topics !== []) {
            $topTopic = array_key_first($topics);
            $count = $topics[$topTopic];
            $percent = round(($count / $totalEntries) * 100);
            $insights[] = "Most discussed topic: '{$topTopic}' appears in {$percent}% of entries ({$count} mentions)";
        }

        // Blocker pattern detection
        if (isset($tags['blocker']) && $tags['blocker'] >= 3) {
            $insights[] = "⚠️ {$tags['blocker']} blocker entries detected - consider reviewing active blockers";
        }

        // Project focus insight
        if ($projects !== []) {
            $topProjects = array_slice(array_keys($projects), 0, 3);
            $insights[] = 'Most active projects: '.implode(', ', $topProjects);
        }

        // Category imbalance detection
        $maxCategory = max($categories);
        $minCategory = min($categories);
        if ($maxCategory > $minCategory * 3 && $totalEntries > 10) {
            $dominant = array_search($maxCategory, $categories, true);
            $insights[] = "Knowledge is heavily skewed toward '{$dominant}' category";
        }

        // Decision tracking
        if (isset($tags['decision']) && $tags['decision'] >= 2) {
            $insights[] = "📋 {$tags['decision']} decisions recorded - good decision tracking!";
        }

        // Milestone tracking
        if (isset($tags['milestone']) && $tags['milestone'] >= 2) {
            $insights[] = "🎯 {$tags['milestone']} milestones captured";
        }

        return $insights;
    }
}
