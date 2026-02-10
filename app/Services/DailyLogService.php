<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class DailyLogService
{
    private const VALID_SECTIONS = ['Decisions', 'Corrections', 'Commitments', 'Notes'];

    private const DEFAULT_RETENTION_DAYS = 7;

    private const AUTO_PROMOTE_CONFIDENCE_THRESHOLD = 80;

    private const VALID_CATEGORIES = ['debugging', 'architecture', 'testing', 'deployment', 'security'];

    public function __construct(
        private readonly KnowledgePathService $pathService,
    ) {}

    /**
     * Get the staging directory path.
     */
    public function getStagingDirectory(): string
    {
        return $this->pathService->getKnowledgeDirectory().'/staging';
    }

    /**
     * Get the path for a specific day's log file.
     */
    public function getDailyLogPath(string $date): string
    {
        return $this->getStagingDirectory().'/'.$date.'.md';
    }

    /**
     * Stage an entry into today's daily log.
     *
     * @param  array{title: string, content: string, section?: string, category?: string|null, tags?: array<string>, priority?: string, confidence?: int, source?: string|null, ticket?: string|null, author?: string|null}  $entry
     */
    public function stage(array $entry): string
    {
        $date = Carbon::now()->format('Y-m-d');
        $logPath = $this->getDailyLogPath($date);

        $this->pathService->ensureDirectoryExists($this->getStagingDirectory());

        $section = $entry['section'] ?? 'Notes';
        if (! in_array($section, self::VALID_SECTIONS, true)) {
            $section = 'Notes';
        }

        $id = Str::uuid()->toString();
        $timestamp = Carbon::now()->format('H:i:s');

        $entryBlock = $this->formatEntry($id, $timestamp, $entry);

        if (! file_exists($logPath)) {
            $content = $this->createDailyLog($date, $section, $entryBlock);
        } else {
            $content = (string) file_get_contents($logPath);
            $content = $this->appendToSection($content, $section, $entryBlock);
        }

        file_put_contents($logPath, $content);

        return $id;
    }

    /**
     * Read all entries from a daily log file.
     *
     * @return array<int, array{id: string, timestamp: string, title: string, content: string, section: string, category: ?string, tags: array<string>, priority: string, confidence: int}>
     */
    public function readDailyLog(string $date): array
    {
        $logPath = $this->getDailyLogPath($date);

        if (! file_exists($logPath)) {
            return [];
        }

        $content = (string) file_get_contents($logPath);

        return $this->parseEntries($content);
    }

    /**
     * Get all daily log files in staging.
     *
     * @return array<string>
     */
    public function listDailyLogs(): array
    {
        $stagingDir = $this->getStagingDirectory();

        if (! is_dir($stagingDir)) {
            return [];
        }

        $files = scandir($stagingDir);
        if ($files === false) {
            return [];
        }

        $logs = [];
        foreach ($files as $file) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}\.md$/', $file)) {
                $logs[] = str_replace('.md', '', $file);
            }
        }

        sort($logs);

        return $logs;
    }

    /**
     * Get entries that are ready for promotion (older than retention period).
     *
     * @return array<int, array{id: string, timestamp: string, title: string, content: string, section: string, category: ?string, tags: array<string>, priority: string, confidence: int, date: string}>
     */
    public function getPromotableEntries(int $retentionDays = self::DEFAULT_RETENTION_DAYS): array
    {
        $cutoffDate = Carbon::now()->subDays($retentionDays)->format('Y-m-d');
        $promotable = [];

        foreach ($this->listDailyLogs() as $date) {
            if ($date <= $cutoffDate) {
                $entries = $this->readDailyLog($date);
                foreach ($entries as $entry) {
                    $entry['date'] = $date;
                    $promotable[] = $entry;
                }
            }
        }

        return $promotable;
    }

    /**
     * Get entries eligible for auto-promotion (high confidence + matching category).
     *
     * @return array<int, array{id: string, timestamp: string, title: string, content: string, section: string, category: ?string, tags: array<string>, priority: string, confidence: int, date: string}>
     */
    public function getAutoPromotableEntries(): array
    {
        $all = [];

        foreach ($this->listDailyLogs() as $date) {
            $entries = $this->readDailyLog($date);
            foreach ($entries as $entry) {
                $entry['date'] = $date;
                if ($this->isAutoPromotable($entry)) {
                    $all[] = $entry;
                }
            }
        }

        return $all;
    }

    /**
     * Remove a specific entry from its daily log file.
     */
    public function removeEntry(string $date, string $id): bool
    {
        $logPath = $this->getDailyLogPath($date);

        if (! file_exists($logPath)) {
            return false;
        }

        $content = (string) file_get_contents($logPath);
        $pattern = '/<!-- entry:'.$this->escapeRegex($id).' -->.*?<!-- \/entry -->\n*/s';
        $newContent = preg_replace($pattern, '', $content);

        if ($newContent === null || $newContent === $content) {
            return false;
        }

        // If the file is now essentially empty (just header), remove it
        if ($this->isLogEmpty($newContent)) {
            unlink($logPath);

            return true;
        }

        file_put_contents($logPath, $newContent);

        return true;
    }

    /**
     * Remove an entire daily log file.
     */
    public function removeDailyLog(string $date): bool
    {
        $logPath = $this->getDailyLogPath($date);

        if (! file_exists($logPath)) {
            return false;
        }

        return unlink($logPath);
    }

    /**
     * Get the configured retention days.
     */
    public function getRetentionDays(): int
    {
        $days = config('staging.retention_days');

        return is_numeric($days) ? (int) $days : self::DEFAULT_RETENTION_DAYS;
    }

    /**
     * Check if an entry qualifies for auto-promotion.
     *
     * @param  array{confidence: int, category: ?string}  $entry
     */
    private function isAutoPromotable(array $entry): bool
    {
        return $entry['confidence'] >= self::AUTO_PROMOTE_CONFIDENCE_THRESHOLD
            && $entry['category'] !== null
            && in_array($entry['category'], self::VALID_CATEGORIES, true);
    }

    /**
     * Create a new daily log file with the first entry.
     */
    private function createDailyLog(string $date, string $section, string $entryBlock): string
    {
        $lines = ["# Daily Log: {$date}", ''];

        foreach (self::VALID_SECTIONS as $validSection) {
            $lines[] = "## {$validSection}";
            $lines[] = '';
            if ($validSection === $section) {
                $lines[] = $entryBlock;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Append an entry to the correct section of an existing log.
     */
    private function appendToSection(string $content, string $section, string $entryBlock): string
    {
        $sectionHeader = "## {$section}";

        // Find the section and insert after it
        $pos = strpos($content, $sectionHeader);
        if ($pos === false) {
            // Section doesn't exist, append it
            return $content."\n{$sectionHeader}\n\n{$entryBlock}\n";
        }

        // Find the next section or end of file
        $afterSection = $pos + strlen($sectionHeader);
        $nextSectionPos = PHP_INT_MAX;

        foreach (self::VALID_SECTIONS as $validSection) {
            if ($validSection === $section) {
                continue;
            }
            $nextPos = strpos($content, "## {$validSection}", $afterSection);
            if ($nextPos !== false && $nextPos < $nextSectionPos) {
                $nextSectionPos = $nextPos;
            }
        }

        if ($nextSectionPos === PHP_INT_MAX) {
            // This is the last section, append to end
            return rtrim($content)."\n\n{$entryBlock}\n";
        }

        // Insert before the next section
        $before = rtrim(substr($content, 0, $nextSectionPos));
        $after = substr($content, $nextSectionPos);

        return $before."\n\n{$entryBlock}\n\n".$after;
    }

    /**
     * Format an entry as markdown.
     *
     * @param  array{title: string, content: string, category?: string|null, tags?: array<string>, priority?: string, confidence?: int, source?: string|null, ticket?: string|null, author?: string|null}  $entry
     */
    private function formatEntry(string $id, string $timestamp, array $entry): string
    {
        $lines = [];
        $lines[] = "<!-- entry:{$id} -->";
        $lines[] = "### [{$timestamp}] {$entry['title']}";
        $lines[] = '';
        $lines[] = $entry['content'];
        $lines[] = '';

        $meta = [];
        if (isset($entry['category']) && $entry['category'] !== '') {
            $meta[] = "**Category:** {$entry['category']}";
        }
        if (isset($entry['tags']) && is_array($entry['tags']) && $entry['tags'] !== []) {
            $meta[] = '**Tags:** '.implode(', ', $entry['tags']);
        }
        $meta[] = '**Priority:** '.($entry['priority'] ?? 'medium');
        $meta[] = '**Confidence:** '.($entry['confidence'] ?? 50).'%';

        if (isset($entry['source']) && $entry['source'] !== '') {
            $meta[] = "**Source:** {$entry['source']}";
        }
        if (isset($entry['ticket']) && $entry['ticket'] !== '') {
            $meta[] = "**Ticket:** {$entry['ticket']}";
        }
        if (isset($entry['author']) && $entry['author'] !== '') {
            $meta[] = "**Author:** {$entry['author']}";
        }

        $lines[] = implode(' | ', $meta);
        $lines[] = '<!-- /entry -->';

        return implode("\n", $lines);
    }

    /**
     * Parse all entries from a daily log markdown file.
     *
     * @return array<int, array{id: string, timestamp: string, title: string, content: string, section: string, category: ?string, tags: array<string>, priority: string, confidence: int}>
     */
    private function parseEntries(string $content): array
    {
        $entries = [];
        $currentSection = 'Notes';

        // Track which section each entry belongs to
        $lines = explode("\n", $content);
        $sectionMap = [];

        foreach ($lines as $line) {
            if (preg_match('/^## (.+)$/', $line, $matches)) {
                $currentSection = trim($matches[1]);
            }
            if (preg_match('/<!-- entry:(.+?) -->/', $line, $matches)) {
                $sectionMap[$matches[1]] = $currentSection;
            }
        }

        // Parse entry blocks
        preg_match_all('/<!-- entry:(.+?) -->\n### \[(\d{2}:\d{2}:\d{2})\] (.+?)\n\n(.*?)\n\n(.*?)\n<!-- \/entry -->/s', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $id = $match[1];
            $metaLine = $match[5];

            $entries[] = [
                'id' => $id,
                'timestamp' => $match[2],
                'title' => $match[3],
                'content' => $match[4],
                'section' => $sectionMap[$id] ?? 'Notes',
                'category' => $this->extractMeta($metaLine, 'Category'),
                'tags' => $this->extractTags($metaLine),
                'priority' => $this->extractMeta($metaLine, 'Priority') ?? 'medium',
                'confidence' => (int) ($this->extractMeta($metaLine, 'Confidence') ?? '50'),
            ];
        }

        return $entries;
    }

    /**
     * Extract a metadata value from the meta line.
     */
    private function extractMeta(string $metaLine, string $key): ?string
    {
        if (preg_match('/\*\*'.$key.':\*\*\s*([^|*]+)/', $metaLine, $match)) {
            $value = trim($match[1]);
            // Strip % from confidence values
            $value = rtrim($value, '%');

            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * Extract tags from the meta line.
     *
     * @return array<string>
     */
    private function extractTags(string $metaLine): array
    {
        if (preg_match('/\*\*Tags:\*\*\s*([^|*]+)/', $metaLine, $match)) {
            return array_map('trim', explode(',', trim($match[1])));
        }

        return [];
    }

    /**
     * Check if a daily log has no entries left.
     */
    private function isLogEmpty(string $content): bool
    {
        return ! str_contains($content, '<!-- entry:');
    }

    /**
     * Escape a string for use in regex.
     */
    private function escapeRegex(string $value): string
    {
        return preg_quote($value, '/');
    }
}
