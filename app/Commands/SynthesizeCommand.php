<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Concerns\ResolvesProject;
use App\Services\AiService;
use App\Services\QdrantService;
use App\Exceptions\Qdrant\DuplicateEntryException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class SynthesizeCommand extends Command
{
    use ResolvesProject;

    protected $signature = 'synthesize
                            {--dedupe : Find and merge duplicate entries}
                            {--digest : Generate a daily digest entry}
                            {--archive-stale : Archive old low-confidence entries}
                            {--dry-run : Show what would be done without making changes}
                            {--similarity=0.92 : Similarity threshold for deduplication (0.0-1.0)}
                            {--stale-days=30 : Days before low-confidence entries are considered stale}
                            {--confidence-floor=50 : Confidence threshold for stale detection}
                            {--project= : Override project namespace}
                            {--global : Search across all projects}';

    protected $description = 'Synthesize knowledge: dedupe, digest, and archive stale entries';

    private const DEDUPE_SIMILARITY_DEFAULT = 0.92;

    private const STALE_DAYS_DEFAULT = 30;

    private const CONFIDENCE_FLOOR_DEFAULT = 50;

    public function handle(QdrantService $qdrant, AiService $ai): int
    {
        $dedupe = (bool) $this->option('dedupe');
        $digest = (bool) $this->option('digest');
        $archiveStale = (bool) $this->option('archive-stale');
        $dryRun = (bool) $this->option('dry-run');

        // If no specific option, run all
        $runAll = ! $dedupe && ! $digest && ! $archiveStale;

        $stats = [
            'duplicates_found' => 0,
            'duplicates_merged' => 0,
            'digest_created' => false,
            'stale_archived' => 0,
        ];

        if ($runAll || $dedupe) {
            $stats = array_merge($stats, $this->runDedupe($qdrant, $dryRun));
        }

        if ($runAll || $digest) {
            $stats['digest_created'] = $this->runDigest($qdrant, $ai, $dryRun);
        }

        if ($runAll || $archiveStale) {
            $stats['stale_archived'] = $this->runArchiveStale($qdrant, $dryRun);
        }

        $this->displaySummary($stats, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Find and merge duplicate entries based on semantic similarity.
     *
     * @return array{duplicates_found: int, duplicates_merged: int}
     */
    private function runDedupe(QdrantService $qdrant, bool $dryRun): array
    {
        $similarity = (float) ($this->option('similarity') ?? self::DEDUPE_SIMILARITY_DEFAULT);

        info("Scanning for duplicates (similarity >= {$similarity})...");

        // Get all entries with low confidence (these are candidates for deduplication)
        $candidates = spin(
            fn (): \Illuminate\Support\Collection => $qdrant->scroll(['status' => 'draft'], 100),
            'Fetching draft entries...'
        );

        $duplicatesFound = 0;
        $duplicatesMerged = 0;
        $processed = [];

        foreach ($candidates as $candidate) {
            $id = (string) $candidate['id'];

            // Skip if already processed
            if (in_array($id, $processed, true)) {
                continue;
            }

            // Search for similar entries
            $similar = $qdrant->search(
                $candidate['title'].' '.$candidate['content'],
                [],
                10
            );

            // Find duplicates (high similarity, different ID, higher confidence)
            $duplicates = $similar->filter(fn (array $entry): bool => (string) $entry['id'] !== $id
                && $entry['score'] >= $similarity
                && $entry['confidence'] > $candidate['confidence']);

            if ($duplicates->isNotEmpty()) {
                $duplicatesFound++;
                $best = $duplicates->first();

                if (! $dryRun) {
                    // Merge: archive the low-confidence duplicate
                    $qdrant->updateFields($id, [
                        'status' => 'deprecated',
                        'content' => $candidate['content']."\n\n[Merged into: ".$best['id'].']',
                    ]);
                    $duplicatesMerged++;
                }

                $processed[] = $id;

                $this->line("  Found duplicate: <comment>{$candidate['title']}</comment>");
                $this->line("    -> Merges into: <info>{$best['title']}</info> (score: ".round($best['score'], 3).')');
            }
        }

        return [
            'duplicates_found' => $duplicatesFound,
            'duplicates_merged' => $duplicatesMerged,
        ];
    }

    /**
     * Generate a daily digest of recent high-value entries.
     */
    private function runDigest(QdrantService $qdrant, AiService $ai, bool $dryRun): bool
    {
        $today = Carbon::today()->format('Y-m-d');

        info("Generating digest for {$today}...");

        // Check if digest already exists for today
        $existing = $qdrant->search("Daily Synthesis - {$today}", ['tag' => 'daily-synthesis'], 1);

        if ($existing->isNotEmpty() && Str::contains($existing->first()['title'], $today)) {
            warning("Digest for {$today} already exists, skipping.");

            return false;
        }

        // Get recent validated/high-confidence entries from last 24 hours
        $recentEntries = spin(
            fn (): \Illuminate\Support\Collection => $this->getRecentHighValueEntries($qdrant),
            'Analyzing recent entries...'
        );

        if ($recentEntries->isEmpty()) {
            warning('No high-value entries found for digest.');

            return false;
        }

        // Build digest content (using AI if available)
        $digestContent = $this->buildDigestContent($recentEntries, $ai, $today);

        if ($dryRun) {
            $this->line('');
            $this->line('<comment>Would create digest:</comment>');
            $this->line($digestContent);

            return true;
        }

        // Create digest entry
        try {
            $qdrant->upsert([
                'id' => Str::uuid()->toString(),
                'title' => "Daily Synthesis - {$today}",
                'content' => $digestContent,
                'category' => 'architecture',
                'tags' => ['daily-synthesis', $today],
                'priority' => 'medium',
                'confidence' => 85,
                'status' => 'validated',
            ], 'default', false); // Allow upsert to handle vectors, catch duplicates if necessary
            
        } catch (DuplicateEntryException $e) {
            warning("Digest for {$today} already exists (duplicate detected).");
            return false;
        }

        info("Digest created for {$today}");

        return true;
    }

    /**
     * Archive stale low-confidence entries.
     */
    private function runArchiveStale(QdrantService $qdrant, bool $dryRun): int
    {
        $staleDays = (int) ($this->option('stale-days') ?? self::STALE_DAYS_DEFAULT);
        $confidenceFloor = (int) ($this->option('confidence-floor') ?? self::CONFIDENCE_FLOOR_DEFAULT);

        info("Archiving entries older than {$staleDays} days with confidence < {$confidenceFloor}%...");

        $cutoffDate = Carbon::now()->subDays($staleDays)->toIso8601String();

        // Get draft entries
        $candidates = spin(
            fn (): \Illuminate\Support\Collection => $qdrant->scroll(['status' => 'draft'], 200),
            'Scanning for stale entries...'
        );

        $archived = 0;

        foreach ($candidates as $entry) {
            // Check if entry is old and low confidence
            $isOld = isset($entry['created_at']) && $entry['created_at'] < $cutoffDate;
            $isLowConfidence = ($entry['confidence'] ?? 0) < $confidenceFloor;
            $isUnused = ($entry['usage_count'] ?? 0) === 0;

            if ($isOld && $isLowConfidence && $isUnused) {
                if (! $dryRun) {
                    $qdrant->updateFields($entry['id'], ['status' => 'deprecated']);
                }

                $archived++;
                $this->line("  Archived: <comment>{$entry['title']}</comment> (confidence: {$entry['confidence']}%)");
            }
        }

        return $archived;
    }

    /**
     * Get recent high-value entries for digest.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function getRecentHighValueEntries(QdrantService $qdrant): Collection
    {
        $yesterday = Carbon::yesterday()->toIso8601String();

        // Get validated entries
        $validated = $qdrant->scroll(['status' => 'validated'], 50);

        // Filter to recent and high confidence
        return $validated->filter(function (array $entry) use ($yesterday): bool {
            $isRecent = isset($entry['updated_at']) && $entry['updated_at'] >= $yesterday;
            $isHighConfidence = ($entry['confidence'] ?? 0) >= 70;

            return $isRecent || $isHighConfidence;
        })->take(10);
    }

    /**
     * Build digest content from entries using AI.
     *
     * @param  Collection<int, array<string, mixed>>  $entries
     */
    private function buildDigestContent(Collection $entries, AiService $ai, string $date): string
    {
        if (! $ai->isAvailable()) {
            return $this->buildFallbackDigestContent($entries);
        }

        $context = "Recent Knowledge Entries:\n";
        foreach ($entries as $entry) {
            $title = $entry['title'] ?? 'Untitled';
            $category = $entry['category'] ?? 'General';
            $content = $entry['content'] ?? '';
            $context .= "- [{$category}] {$title}: ".substr($content, 0, 200)."...\n";
        }

        $prompt = "You are synthesizing the daily knowledge activity into a concise daily digest.
        
DATE: {$date}

{$context}

Produce a daily synthesis with these sections:

## Key Insights & Decisions
- 3-5 bullet points of the most important things learned, decided, or discovered
- Be SPECIFIC: mention actual tools, files, concepts

## Connections
- 2-3 connections between topics
- How does today's work relate to ongoing projects?

## Tomorrow's Focus
- 2-3 suggested areas to focus on based on today's activity

Rules:
- Be concrete and specific
- Reference actual entry titles/tags
- Keep it concise";

        $summary = $ai->generate($prompt);

        if (empty($summary)) {
            return $this->buildFallbackDigestContent($entries);
        }

        return "**Daily Synthesis - {$date}**\n\n".$summary;
    }

    private function buildFallbackDigestContent(Collection $entries): string
    {
        $lines = ["**Daily Knowledge Synthesis**\n"];

        // Group by category
        $byCategory = $entries->groupBy(fn (array $e): string => (string) ($e['category'] ?? 'general'));

        foreach ($byCategory as $category => $categoryEntries) {
            $lines[] = "\n### ".ucfirst($category);

            foreach ($categoryEntries as $entry) {
                $confidence = $entry['confidence'] ?? 0;
                $title = $entry['title'] ?? 'Untitled';
                $lines[] = "- **{$title}** ({$confidence}% confidence)";

                // Add brief content preview
                $content = $entry['content'] ?? '';
                if (strlen((string) $content) > 100) {
                    $content = substr((string) $content, 0, 100).'...';
                }
                if ($content !== '') {
                    $lines[] = "  {$content}";
                }
            }
        }

        $lines[] = "\n---\n*Auto-generated by knowledge synthesize*";

        return implode("\n", $lines);
    }

    /**
     * Display summary of operations.
     *
     * @param  array{duplicates_found: int, duplicates_merged: int, digest_created: bool, stale_archived: int}  $stats
     */
    private function displaySummary(array $stats, bool $dryRun): void
    {
        $this->line('');

        $prefix = $dryRun ? '[DRY RUN] ' : '';

        table(
            ['Operation', 'Result'],
            [
                ['Duplicates Found', (string) $stats['duplicates_found']],
                [$prefix.'Duplicates Merged', (string) $stats['duplicates_merged']],
                [$prefix.'Digest Created', $stats['digest_created'] ? 'Yes' : 'No'],
                [$prefix.'Stale Archived', (string) $stats['stale_archived']],
            ]
        );

        if ($dryRun) {
            warning('Dry run - no changes made. Remove --dry-run to apply changes.');
        } else {
            info('Synthesis complete!');
        }
    }
}
