<?php

declare(strict_types=1);

namespace App\Services;

class IssueAnalyzerService
{
    public function __construct(
        private readonly OllamaService $ollama
    ) {}

    /**
     * Analyze GitHub issue and identify files to modify.
     */
    public function analyzeIssue(array $issue): array
    {
        // Get codebase context by scanning relevant directories
        $codebaseContext = $this->gatherCodebaseContext($issue);

        // Use Ollama to analyze the issue
        $analysis = $this->ollama->analyzeIssue($issue, $codebaseContext);

        // Enhance analysis with file existence checks
        return $this->validateAndEnhanceAnalysis($analysis);
    }

    /**
     * Build todo list from analysis.
     */
    public function buildTodoList(array $analysis): array
    {
        $todos = [];

        // Group files by change type for better organization
        $filesByType = $this->groupFilesByChangeType($analysis['files']);

        // Create todos for implementation
        foreach ($filesByType['implement'] ?? [] as $file) {
            $todos[] = [
                'content' => "Implement {$file['change_type']} in {$file['path']}",
                'type' => 'implementation',
                'file' => $file['path'],
                'confidence' => $file['confidence'],
            ];
        }

        // Create todos for tests
        foreach ($filesByType['test'] ?? [] as $file) {
            $todos[] = [
                'content' => "Add tests for {$file['path']}",
                'type' => 'test',
                'file' => $file['path'],
                'confidence' => $file['confidence'],
            ];
        }

        // Add quality gate todos
        $todos[] = [
            'content' => 'Run tests and verify coverage',
            'type' => 'quality',
            'file' => null,
            'confidence' => 100,
        ];

        $todos[] = [
            'content' => 'Run PHPStan analysis',
            'type' => 'quality',
            'file' => null,
            'confidence' => 100,
        ];

        $todos[] = [
            'content' => 'Apply Laravel Pint formatting',
            'type' => 'quality',
            'file' => null,
            'confidence' => 100,
        ];

        return $todos;
    }

    /**
     * Gather codebase context relevant to the issue.
     */
    private function gatherCodebaseContext(array $issue): array
    {
        $context = [];

        // Extract key terms from issue title and body
        $keywords = $this->extractKeywords($issue['title'].' '.($issue['body'] ?? ''));

        // Search for relevant files using grep
        foreach ($keywords as $keyword) {
            $files = $this->searchFiles($keyword);
            $context = array_merge($context, array_slice($files, 0, 5)); // Limit to top 5 matches
        }

        return array_unique($context);
    }

    /**
     * Extract keywords from issue text.
     */
    private function extractKeywords(string $text): array
    {
        // Simple keyword extraction - can be enhanced with Ollama
        $words = preg_split('/\s+/', strtolower($text));

        if ($words === false) {
            return [];
        }

        // Filter common words and short words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];

        return array_values(array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 3 && ! in_array($word, $stopWords, true);
        }));
    }

    /**
     * Search for files containing keyword.
     */
    private function searchFiles(string $keyword): array
    {
        $command = sprintf(
            'grep -r -l --include="*.php" "%s" app/ 2>/dev/null | head -10',
            escapeshellarg($keyword)
        );

        $output = shell_exec($command);

        if ($output === null || $output === false || $output === '') {
            return [];
        }

        return array_filter(explode("\n", trim($output)));
    }

    /**
     * Validate and enhance analysis results.
     */
    private function validateAndEnhanceAnalysis(array $analysis): array
    {
        $validatedFiles = [];

        foreach ($analysis['files'] as $file) {
            // Check if file exists
            $file['exists'] = file_exists($file['path']);

            // If file doesn't exist and it's not a new file creation, reduce confidence
            if (! $file['exists'] && ! str_contains(strtolower($file['change_type']), 'create')) {
                $file['confidence'] = max(0, $file['confidence'] - 30);
                $file['reason'] .= ' (file does not exist)';
            }

            $validatedFiles[] = $file;
        }

        $analysis['files'] = $validatedFiles;

        // Recalculate overall confidence
        if (count($validatedFiles) > 0) {
            $avgConfidence = array_sum(array_column($validatedFiles, 'confidence')) / count($validatedFiles);
            $analysis['confidence'] = (int) $avgConfidence;
        }

        return $analysis;
    }

    /**
     * Group files by change type.
     */
    private function groupFilesByChangeType(array $files): array
    {
        $grouped = [
            'implement' => [],
            'test' => [],
            'refactor' => [],
        ];

        foreach ($files as $file) {
            if (str_contains(strtolower($file['path']), 'test')) {
                $grouped['test'][] = $file;
            } elseif (str_contains(strtolower($file['change_type']), 'refactor')) {
                $grouped['refactor'][] = $file;
            } else {
                $grouped['implement'][] = $file;
            }
        }

        return $grouped;
    }
}
