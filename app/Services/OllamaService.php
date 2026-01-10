<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class OllamaService
{
    private string $baseUrl;

    private string $model;

    private int $timeout;

    public function __construct()
    {
        $host = config('search.ollama.host', 'localhost');
        $port = config('search.ollama.port', 11434);
        $this->baseUrl = "http://{$host}:{$port}";
        $this->model = config('search.ollama.model', 'llama3.2:3b');
        $this->timeout = config('search.ollama.timeout', 30);
    }

    /**
     * Enhance a raw knowledge entry with structured metadata.
     */
    public function enhanceEntry(string $title, string $content): array
    {
        $cacheKey = 'ollama:enhance:'.md5($title.$content);

        return Cache::remember($cacheKey, 3600, function () use ($title, $content) {
            $prompt = $this->buildEnhancementPrompt($title, $content);

            $response = $this->generate($prompt, true);

            return $this->parseEnhancementResponse($response);
        });
    }

    /**
     * Extract relevant tags from content.
     */
    public function extractTags(string $content, int $maxTags = 5): array
    {
        $prompt = <<<PROMPT
Extract {$maxTags} relevant tags from this text. Return ONLY a JSON array of strings, nothing else.

Text: {$content}

Example output: ["laravel", "api", "authentication"]
PROMPT;

        $response = $this->generate($prompt, true);

        return $this->parseJsonResponse($response, []);
    }

    /**
     * Categorize content into a category.
     */
    public function categorize(string $content): ?string
    {
        $categories = [
            'feature', 'bug-fix', 'refactor', 'documentation',
            'testing', 'infrastructure', 'security', 'performance',
            'api', 'ui', 'database', 'other',
        ];

        $categoriesList = implode(', ', $categories);

        $prompt = <<<PROMPT
Categorize this text into ONE of these categories: {$categoriesList}

Return ONLY the category name, nothing else.

Text: {$content}
PROMPT;

        $response = $this->generate($prompt);

        $category = trim(strtolower($response));

        return in_array($category, $categories) ? $category : 'other';
    }

    /**
     * Extract key concepts from content.
     */
    public function extractConcepts(string $content, int $maxConcepts = 3): array
    {
        $prompt = <<<PROMPT
Extract {$maxConcepts} key technical concepts from this text.
Return ONLY a JSON array of strings, nothing else.

Text: {$content}

Example output: ["dependency injection", "REST API", "caching strategy"]
PROMPT;

        $response = $this->generate($prompt, true);

        return $this->parseJsonResponse($response, []);
    }

    /**
     * Expand a search query with related terms.
     */
    public function expandQuery(string $query): array
    {
        $cacheKey = 'ollama:query:'.md5($query);

        return Cache::remember($cacheKey, 3600, function () use ($query) {
            $prompt = <<<PROMPT
Given this search query, suggest 3-5 related terms or synonyms that would help find relevant content.
Return ONLY a JSON array of strings, nothing else.

Query: {$query}

Example output: ["redis", "cache", "key-value store", "in-memory database"]
PROMPT;

            $response = $this->generate($prompt, true);

            return $this->parseJsonResponse($response, [$query]);
        });
    }

    /**
     * Suggest a better title for content.
     */
    public function suggestTitle(string $content): string
    {
        $prompt = <<<PROMPT
Create a clear, concise title (max 60 chars) for this content.
Return ONLY the title, nothing else.

Content: {$content}
PROMPT;

        return trim($this->generate($prompt));
    }

    /**
     * Generate completion using Ollama.
     */
    private function generate(string $prompt, bool $jsonFormat = false): string
    {
        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
        ];

        if ($jsonFormat) {
            $payload['format'] = 'json';
        }

        $ch = curl_init("{$this->baseUrl}/api/generate");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            throw new \RuntimeException('Ollama request failed');
        }

        $data = json_decode($response, true);

        return $data['response'] ?? '';
    }

    /**
     * Build enhancement prompt for entry processing.
     */
    private function buildEnhancementPrompt(string $title, string $content): string
    {
        return <<<PROMPT
Analyze this knowledge entry and return structured metadata as JSON.

Title: {$title}
Content: {$content}

Return JSON with this structure:
{
  "improved_title": "A clear, concise title (max 60 chars)",
  "category": "one of: feature, bug-fix, refactor, documentation, testing, infrastructure, security, performance, api, ui, database, other",
  "tags": ["tag1", "tag2", "tag3"],
  "priority": "one of: critical, high, medium, low",
  "confidence": 85,
  "summary": "One sentence summary of the entry",
  "concepts": ["concept1", "concept2"]
}
PROMPT;
    }

    /**
     * Parse enhancement response into structured array.
     */
    private function parseEnhancementResponse(string $response): array
    {
        $data = $this->parseJsonResponse($response, []);

        return [
            'title' => $data['improved_title'] ?? null,
            'category' => $data['category'] ?? null,
            'tags' => $data['tags'] ?? [],
            'priority' => $data['priority'] ?? 'medium',
            'confidence' => $data['confidence'] ?? 50,
            'summary' => $data['summary'] ?? null,
            'concepts' => $data['concepts'] ?? [],
        ];
    }

    /**
     * Parse JSON response with fallback.
     */
    private function parseJsonResponse(string $response, mixed $default): mixed
    {
        try {
            return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $default;
        }
    }

    /**
     * Check if Ollama is available.
     */
    public function isAvailable(): bool
    {
        try {
            $ch = curl_init("{$this->baseUrl}/api/tags");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200 && $response !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
