<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\OllamaService;
use App\Services\QdrantService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeCaptureCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'capture
                            {--context= : Conversation context to analyze}
                            {--file= : File containing conversation context}
                            {--project= : Project name for tagging}
                            {--session= : Session ID for tracking}
                            {--dry-run : Show what would be captured without persisting}';

    /**
     * @var string
     */
    protected $description = 'Automatically capture meaningful insights from conversation context';

    /**
     * Pattern definitions for different knowledge types.
     *
     * @var array<string, array{patterns: array<string>, category: string, priority: string}>
     */
    private array $detectionPatterns = [
        'insight' => [
            'patterns' => [
                'figured out',
                'the issue was',
                'root cause',
                'solution:',
                'turns out',
                'realized that',
                'the fix is',
                'problem was',
                'got it working',
                'the answer is',
            ],
            'category' => 'debugging',
            'priority' => 'medium',
        ],
        'blocker' => [
            'patterns' => [
                'blocked by',
                'waiting on',
                "can't proceed",
                'stuck on',
                'need help with',
                "don't understand",
                'failing because',
                'dependency issue',
                'permission denied',
            ],
            'category' => 'blocker',
            'priority' => 'high',
        ],
        'decision' => [
            'patterns' => [
                'decided to',
                'going with',
                'chosen approach',
                'will use',
                'opted for',
                'strategy:',
                'trade-off:',
                'because we need',
            ],
            'category' => 'architecture',
            'priority' => 'medium',
        ],
        'milestone' => [
            'patterns' => [
                'milestone:',
                'completed',
                'shipped',
                'deployed',
                'finished',
                'released',
                'merged',
                'feature complete',
                'ready for production',
            ],
            'category' => 'milestone',
            'priority' => 'high',
        ],
    ];

    /**
     * Noise patterns to filter out.
     *
     * @var array<string>
     */
    private array $noisePatterns = [
        'let me check',
        'running command',
        "here's the output",
        'let me read',
        'let me search',
        'i will',
        "i'll",
        'maybe',
        'might',
        'could try',
        'not sure',
    ];

    public function handle(QdrantService $qdrant): int
    {
        $context = $this->getContext();

        if ($context === '') {
            // Silent fail - this is expected in hook context when no meaningful content
            return self::SUCCESS;
        }

        // Check for noise patterns first
        if ($this->isNoise($context)) {
            return self::SUCCESS;
        }

        // Detect what type of knowledge this might be
        $detection = $this->detectPatterns($context);

        if ($detection === null) {
            // No meaningful patterns detected
            return self::SUCCESS;
        }

        // Extract structured insight using Ollama
        $extracted = $this->extractInsight($context, $detection);

        if ($extracted === null) {
            return self::SUCCESS;
        }

        // Check for duplicates
        if ($this->isDuplicate($qdrant, $extracted['title'], $extracted['content'])) {
            if ($this->option('dry-run') === true) {
                $this->warn('Skipped (duplicate): '.$extracted['title']);
            }

            return self::SUCCESS;
        }

        // Dry run - show what would be captured
        if ($this->option('dry-run') === true) {
            $this->info('Would capture:');
            $this->line('  Title: '.$extracted['title']);
            $this->line('  Category: '.$extracted['category']);
            $this->line('  Priority: '.$extracted['priority']);
            $this->line('  Tags: '.implode(', ', $extracted['tags']));
            $this->line('  Content: '.$extracted['content']);

            return self::SUCCESS;
        }

        // Persist the knowledge entry
        $project = $this->option('project');
        $session = $this->option('session');
        $tags = $extracted['tags'];

        if (is_string($session) && $session !== '') {
            $tags[] = 'session:'.$session;
        }

        /** @var array{id: string, title: string, content: string, tags: array<string>, category: string, priority: string, source: string, confidence: int, module?: string} $entry */
        $entry = [
            'id' => (string) uuid_create(),
            'title' => $extracted['title'],
            'content' => $extracted['content'],
            'category' => $extracted['category'],
            'priority' => $extracted['priority'],
            'tags' => $tags,
            'source' => 'claude-session',
            'confidence' => 70,
        ];

        if (is_string($project) && $project !== '') {
            $entry['module'] = $project;
        }

        $qdrant->upsert($entry);

        return self::SUCCESS;
    }

    /**
     * Get context from option or stdin.
     */
    private function getContext(): string
    {
        $context = $this->option('context');
        if (is_string($context) && $context !== '') {
            return $context;
        }

        $file = $this->option('file');
        if (is_string($file) && $file !== '' && file_exists($file)) {
            $content = file_get_contents($file);

            return is_string($content) ? $content : '';
        }

        // Try reading from stdin (non-blocking)
        $stdin = '';
        stream_set_blocking(STDIN, false);
        while (($line = fgets(STDIN)) !== false) {
            $stdin .= $line;
        }

        return trim($stdin);
    }

    /**
     * Check if content is just noise.
     */
    private function isNoise(string $context): bool
    {
        $lower = strtolower($context);

        foreach ($this->noisePatterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        // Too short to be meaningful
        if (strlen($context) < 50) {
            return true;
        }

        return false;
    }

    /**
     * Detect which type of knowledge this represents.
     *
     * @return array{type: string, category: string, priority: string}|null
     */
    private function detectPatterns(string $context): ?array
    {
        $lower = strtolower($context);

        foreach ($this->detectionPatterns as $type => $config) {
            foreach ($config['patterns'] as $pattern) {
                if (str_contains($lower, $pattern)) {
                    return [
                        'type' => $type,
                        'category' => $config['category'],
                        'priority' => $config['priority'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Extract structured insight using Ollama.
     *
     * @param  array{type: string, category: string, priority: string}  $detection
     * @return array{title: string, content: string, category: string, priority: string, tags: array<string>}|null
     */
    private function extractInsight(string $context, array $detection): ?array
    {
        /** @var array<string, mixed> $config */
        $config = config('search.ollama', []);

        $ollama = new OllamaService(
            host: is_string($config['host'] ?? null) ? $config['host'] : 'localhost',
            port: is_int($config['port'] ?? null) ? $config['port'] : 11434,
            model: is_string($config['model'] ?? null) ? $config['model'] : 'mistral:7b',
            timeout: is_int($config['timeout'] ?? null) ? $config['timeout'] : 30,
        );

        $prompt = <<<PROMPT
Extract a knowledge entry from this conversation context. The detected type is: {$detection['type']}

Context:
{$context}

Respond with ONLY a JSON object (no markdown, no explanation):
{
  "title": "concise title (max 80 chars)",
  "content": "1-2 sentence summary of the insight/decision/blocker",
  "tags": ["tag1", "tag2"]
}

If the context is not meaningful or actionable, respond with: {"skip": true}
PROMPT;

        $response = $ollama->generate($prompt);

        if ($response === '') {
            // Ollama unavailable - fall back to simple extraction
            return $this->simpleExtract($context, $detection);
        }

        // Parse JSON response
        $json = $this->parseJson($response);

        if ($json === null || isset($json['skip'])) {
            return null;
        }

        $title = is_string($json['title'] ?? null) ? $json['title'] : '';
        $content = is_string($json['content'] ?? null) ? $json['content'] : '';

        if ($title === '' || $content === '') {
            return null;
        }

        /** @var array<string> $tags */
        $tags = is_array($json['tags'] ?? null) ? array_filter($json['tags'], 'is_string') : [];

        return [
            'title' => $title,
            'content' => $content,
            'category' => $detection['category'],
            'priority' => $detection['priority'],
            'tags' => $tags,
        ];
    }

    /**
     * Simple extraction fallback when Ollama is unavailable.
     *
     * @param  array{type: string, category: string, priority: string}  $detection
     * @return array{title: string, content: string, category: string, priority: string, tags: array<string>}
     */
    private function simpleExtract(string $context, array $detection): array
    {
        // Take first sentence as title
        $sentences = preg_split('/[.!?]+/', $context, 2);
        $title = is_array($sentences) && isset($sentences[0]) ? trim($sentences[0]) : $context;
        $title = substr($title, 0, 80);

        // Content is the full context, trimmed
        $content = substr(trim($context), 0, 500);

        return [
            'title' => $title,
            'content' => $content,
            'category' => $detection['category'],
            'priority' => $detection['priority'],
            'tags' => [$detection['type']],
        ];
    }

    /**
     * Parse JSON from potentially messy LLM output.
     *
     * @return array<string, mixed>|null
     */
    private function parseJson(string $response): ?array
    {
        // Try direct parse first
        $json = json_decode($response, true);
        if (is_array($json)) {
            return $json;
        }

        // Try to extract JSON from markdown code block
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $response, $matches) === 1) {
            $json = json_decode($matches[1], true);
            if (is_array($json)) {
                return $json;
            }
        }

        // Try to find JSON object in response
        if (preg_match('/\{[^{}]*\}/s', $response, $matches) === 1) {
            $json = json_decode($matches[0], true);
            if (is_array($json)) {
                return $json;
            }
        }

        return null;
    }

    /**
     * Check if this insight already exists (semantic deduplication).
     */
    private function isDuplicate(QdrantService $qdrant, string $title, string $content): bool
    {
        // Search for similar entries
        $searchText = $title.' '.$content;
        $results = $qdrant->search($searchText, [], 3);

        foreach ($results as $result) {
            $score = is_float($result['score'] ?? null) ? $result['score'] : 0.0;
            // High similarity threshold for deduplication
            if ($score > 0.85) {
                return true;
            }
        }

        return false;
    }
}
