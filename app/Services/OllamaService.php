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

        return in_array($category, $categories, true) ? $category : 'other';
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            throw new \RuntimeException('Ollama request failed');
        }

        if (! is_string($response)) {
            throw new \RuntimeException('Ollama response is not a string');
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
     * Analyze a GitHub issue and recommend files to modify.
     */
    public function analyzeIssue(array $issue, array $codebaseContext = []): array
    {
        $prompt = $this->buildIssueAnalysisPrompt($issue, $codebaseContext);

        $response = $this->generate($prompt, true);

        return $this->parseIssueAnalysisResponse($response);
    }

    /**
     * Suggest code changes for a specific file based on issue context.
     */
    public function suggestCodeChanges(string $filePath, string $currentCode, array $issue): array
    {
        $prompt = <<<PROMPT
Analyze this code file and suggest specific changes needed to implement the issue.

File: {$filePath}
Issue: {$issue['title']}
Description: {$issue['body']}

Current Code:
{$currentCode}

Return JSON with this structure:
{
  "changes": [
    {
      "line_start": 45,
      "line_end": 50,
      "description": "Add validation for password field",
      "suggested_code": "suggested code here",
      "reason": "Issue requires password validation"
    }
  ],
  "confidence": 85,
  "requires_refactor": false
}
PROMPT;

        $response = $this->generate($prompt, true);

        return $this->parseJsonResponse($response, [
            'changes' => [],
            'confidence' => 0,
            'requires_refactor' => false,
        ]);
    }

    /**
     * Analyze test failure and suggest fixes.
     */
    public function analyzeTestFailure(string $testOutput, string $testFile, string $codeFile): array
    {
        $prompt = <<<PROMPT
Analyze this test failure and suggest how to fix it.

Test Output:
{$testOutput}

Test File: {$testFile}
Code File: {$codeFile}

Return JSON with this structure:
{
  "root_cause": "Brief explanation of why test failed",
  "suggested_fix": "Specific code change needed",
  "file_to_modify": "path/to/file.php",
  "confidence": 85
}
PROMPT;

        $response = $this->generate($prompt, true);

        return $this->parseJsonResponse($response, [
            'root_cause' => 'Unknown',
            'suggested_fix' => '',
            'file_to_modify' => '',
            'confidence' => 0,
        ]);
    }

    /**
     * Build issue analysis prompt.
     */
    private function buildIssueAnalysisPrompt(array $issue, array $codebaseContext): string
    {
        $labels = isset($issue['labels']) ? implode(', ', array_column($issue['labels'], 'name')) : '';
        $contextFiles = count($codebaseContext) > 0 ? "\n\nCodebase Context:\n".implode("\n", $codebaseContext) : '';

        return <<<PROMPT
Analyze this GitHub issue and identify which files need to be modified.

Issue Title: {$issue['title']}
Issue Body: {$issue['body']}
Labels: {$labels}{$contextFiles}

Based on the issue description, identify:
1. Files that need modification
2. Type of change for each file (add feature, fix bug, refactor, add tests, etc.)
3. Confidence level (0-100) for each file
4. Overall implementation approach

Return JSON with this structure:
{
  "files": [
    {
      "path": "app/Services/UserService.php",
      "change_type": "add validation",
      "confidence": 85,
      "reason": "Issue mentions user validation"
    }
  ],
  "approach": "Brief description of implementation strategy",
  "estimated_complexity": "low|medium|high",
  "confidence": 75,
  "requires_architecture_change": false
}
PROMPT;
    }

    /**
     * Parse issue analysis response.
     */
    private function parseIssueAnalysisResponse(string $response): array
    {
        $data = $this->parseJsonResponse($response, []);

        return [
            'files' => $data['files'] ?? [],
            'approach' => $data['approach'] ?? '',
            'complexity' => $data['estimated_complexity'] ?? 'medium',
            'confidence' => $data['confidence'] ?? 0,
            'requires_architecture_change' => $data['requires_architecture_change'] ?? false,
        ];
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
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200 && $response !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
