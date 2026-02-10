<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class OllamaService
{
    protected ?Client $client = null;

    /**
     * Check if Ollama is available and responding.
     */
    public function isAvailable(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $response = $this->getClient()->get('/api/tags', [
                'timeout' => 5,
            ]);

            return $response->getStatusCode() === 200;
        } catch (GuzzleException) {
            return false;
        }
    }

    /**
     * Check if Ollama is enabled in configuration.
     */
    public function isEnabled(): bool
    {
        return (bool) config('search.ollama.enabled', true);
    }

    /**
     * Enhance an entry with AI-generated tags, category, concepts, and summary.
     *
     * @param  array{title: string, content: string, category?: string|null, tags?: array<string>}  $entry
     * @return array{tags: array<string>, category: string|null, concepts: array<string>, summary: string}
     */
    public function enhance(array $entry): array
    {
        $prompt = $this->buildEnhancementPrompt($entry);

        $response = $this->generate($prompt);

        return $this->parseEnhancementResponse($response, $entry);
    }

    /**
     * Send a prompt to Ollama and get a response.
     */
    public function generate(string $prompt): string
    {
        $model = config('search.ollama.model', 'llama3.2:3b');
        $timeout = (int) config('search.ollama.timeout', 30);

        try {
            $response = $this->getClient()->post('/api/generate', [
                'json' => [
                    'model' => $model,
                    'prompt' => $prompt,
                    'stream' => false,
                ],
                'timeout' => $timeout,
            ]);

            if ($response->getStatusCode() !== 200) {
                return '';
            }

            $data = json_decode((string) $response->getBody(), true);

            if (! is_array($data) || ! isset($data['response'])) {
                return '';
            }

            return (string) $data['response'];
        } catch (GuzzleException) {
            return '';
        }
    }

    /**
     * Build the enhancement prompt for an entry.
     *
     * @param  array{title: string, content: string, category?: string|null, tags?: array<string>}  $entry
     */
    private function buildEnhancementPrompt(array $entry): string
    {
        $title = $entry['title'];
        $content = $entry['content'];

        return <<<PROMPT
Analyze this knowledge entry and provide structured metadata.

Title: {$title}
Content: {$content}

Respond ONLY with valid JSON in this exact format:
{
  "tags": ["tag1", "tag2", "tag3"],
  "category": "one of: debugging, architecture, testing, deployment, security",
  "concepts": ["concept1", "concept2"],
  "summary": "A one-sentence summary of this entry"
}

Rules:
- tags: 3-5 relevant lowercase tags
- category: MUST be exactly one of: debugging, architecture, testing, deployment, security
- concepts: 2-4 key concepts or themes
- summary: One concise sentence

Respond with ONLY the JSON, no other text.
PROMPT;
    }

    /**
     * Parse the enhancement response from Ollama.
     *
     * @param  array{title: string, content: string, category?: string|null, tags?: array<string>}  $entry
     * @return array{tags: array<string>, category: string|null, concepts: array<string>, summary: string}
     */
    private function parseEnhancementResponse(string $response, array $entry): array
    {
        $default = [
            'tags' => [],
            'category' => null,
            'concepts' => [],
            'summary' => '',
        ];

        if ($response === '') {
            return $default;
        }

        // Extract JSON from response (may contain extra text)
        $jsonMatch = [];
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $response, $jsonMatch) !== 1) {
            return $default;
        }

        $data = json_decode($jsonMatch[0], true);
        if (! is_array($data)) {
            return $default;
        }

        $validCategories = ['debugging', 'architecture', 'testing', 'deployment', 'security'];

        $tags = isset($data['tags']) && is_array($data['tags'])
            ? array_values(array_filter($data['tags'], 'is_string'))
            : [];

        $category = isset($data['category']) && is_string($data['category']) && in_array($data['category'], $validCategories, true)
            ? $data['category']
            : ($entry['category'] ?? null);

        $concepts = isset($data['concepts']) && is_array($data['concepts'])
            ? array_values(array_filter($data['concepts'], 'is_string'))
            : [];

        $summary = isset($data['summary']) && is_string($data['summary'])
            ? $data['summary']
            : '';

        return [
            'tags' => $tags,
            'category' => $category,
            'concepts' => $concepts,
            'summary' => $summary,
        ];
    }

    /**
     * Get or create HTTP client.
     */
    protected function getClient(): Client
    {
        if (! $this->client instanceof Client) {
            $this->client = app()->bound(Client::class)
                ? app(Client::class)
                : $this->createClient();
        }

        return $this->client;
    }

    /**
     * Create a new HTTP client instance.
     *
     * @codeCoverageIgnore HTTP client factory - tested via integration
     */
    protected function createClient(): Client
    {
        $host = config('search.ollama.host', 'localhost');
        $port = (int) config('search.ollama.port', 11434);

        return new Client([
            'base_uri' => "http://{$host}:{$port}",
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }
}
