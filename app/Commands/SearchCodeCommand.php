<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\CodeIndexerService;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;

class SearchCodeCommand extends Command
{
    protected $signature = 'search-code
                            {query : The semantic search query}
                            {--limit=10 : Maximum number of results}
                            {--repo= : Filter by repository name}
                            {--language= : Filter by language (php, python, javascript, typescript)}
                            {--show-content : Show code content in results}';

    protected $description = 'Search code files semantically';

    public function handle(CodeIndexerService $indexer): int
    {
        $queryArg = $this->argument('query');
        $query = is_string($queryArg) ? $queryArg : '';
        /** @var int $limit */
        $limit = (int) $this->option('limit');
        /** @var string|null $repo */
        $repo = is_string($this->option('repo')) ? $this->option('repo') : null;
        /** @var string|null $language */
        $language = is_string($this->option('language')) ? $this->option('language') : null;
        $showContent = (bool) $this->option('show-content');

        if (trim($query) === '') {
            error('Query cannot be empty.');

            return self::FAILURE;
        }

        $filters = [];
        if ($repo !== null) {
            $filters['repo'] = $repo;
        }
        if ($language !== null) {
            $filters['language'] = $language;
        }

        /** @var array<array{filepath: string, repo: string, language: string, content: string, score: float, functions: array<string>, start_line: int, end_line: int}> $results */
        $results = spin(
            fn (): array => $indexer->search($query, $limit, $filters),
            'Searching...'
        );

        if ($results === []) {
            info('No results found.');

            return self::SUCCESS;
        }

        info(count($results).' results found:');

        foreach ($results as $i => $result) {
            $num = $i + 1;
            $score = round($result['score'] * 100, 1);
            $lines = $result['start_line'].'-'.$result['end_line'];

            note("[{$num}] {$result['filepath']}");
            note("    Repo: {$result['repo']} | Lang: {$result['language']} | Score: {$score}% | Lines: {$lines}");

            if ($result['functions'] !== []) {
                $funcs = implode(', ', array_slice($result['functions'], 0, 5));
                note("    Functions: {$funcs}");
            }

            if ($showContent) {
                $this->line('');
                $this->line('    '.str_repeat('-', 60));
                $contentLines = explode("\n", $result['content']);
                $preview = array_slice($contentLines, 0, 15);
                foreach ($preview as $line) {
                    $this->line('    '.$line);
                }
                if (count($contentLines) > 15) {
                    $this->line('    ... ('.(count($contentLines) - 15).' more lines)');
                }
                $this->line('    '.str_repeat('-', 60));
            }

            $this->line('');
        }

        return self::SUCCESS;
    }
}
