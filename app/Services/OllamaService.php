<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;

class OllamaService
{
    private Client $client;

    private string $model;

    public function __construct(
        string $host = 'localhost',
        int $port = 11434,
        string $model = 'llama3.2:3b',
        int $timeout = 30,
    ) {
        $this->model = $model;
        $this->client = new Client([
            'base_uri' => "http://{$host}:{$port}",
            'timeout' => $timeout,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);
    }

    /**
     * Summarize search results into actionable context.
     *
     * @param  array<int, array<string, mixed>>  $results
     */
    public function summarizeResults(array $results, string $context = 'blockers'): string
    {
        if ($results === []) {
            return '';
        }

        $content = $this->formatResultsForPrompt($results);

        $prompt = match ($context) {
            'blockers' => "Summarize these blockers into 2-3 actionable bullet points. Focus on WHAT is blocked and WHY. Be concise:\n\n{$content}",
            default => "Summarize these knowledge entries into 2-3 concise bullet points:\n\n{$content}",
        };

        return $this->generate($prompt);
    }

    /**
     * Generate text using Ollama.
     */
    public function generate(string $prompt): string
    {
        try {
            $response = $this->client->post('/api/generate', [
                'json' => [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.3,
                        'num_predict' => 200,
                    ],
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return '';
            }

            $data = json_decode((string) $response->getBody(), true);

            return is_array($data) && isset($data['response'])
                ? trim((string) $data['response'])
                : '';
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function formatResultsForPrompt(array $results): string
    {
        $lines = [];
        foreach ($results as $result) {
            $title = is_string($result['title'] ?? null) ? $result['title'] : '';
            $content = is_string($result['content'] ?? null) ? $result['content'] : '';
            $lines[] = "- {$title}: {$content}";
        }

        return implode("\n", $lines);
    }
}
