<?php

declare(strict_types=1);

namespace App\Commands;

use App\Contracts\EmbeddingServiceInterface;
use App\Integrations\Qdrant\QdrantConnector;
use App\Integrations\Qdrant\Requests\SearchPoints;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class AnthropicSearchCommand extends Command
{
    protected $signature = 'anthropic:search
                            {query : Search term}
                            {--limit=10 : Maximum results}
                            {--sender= : Filter by sender (human/assistant)}
                            {--collection=anthropic_conversations : Qdrant collection name}';

    protected $description = 'Search Anthropic conversation history via semantic search';

    public function handle(EmbeddingServiceInterface $embedding): int
    {
        $query = $this->argument('query');
        $limit = (int) $this->option('limit');
        $collection = $this->option('collection');
        $sender = $this->option('sender');

        $vector = $embedding->generate($query);
        if (empty($vector)) {
            warning('Failed to generate embedding for query.');

            return self::FAILURE;
        }

        $qdrant = new QdrantConnector(
            host: config('search.qdrant.host', 'localhost'),
            port: (int) config('search.qdrant.port', 6333),
            apiKey: config('search.qdrant.api_key'),
        );

        $response = $qdrant->send(new SearchPoints($collection, $vector, $limit, 0.0));

        if (! $response->successful()) {
            warning('Search failed.');

            return self::FAILURE;
        }

        $results = collect($response->json('result') ?? []);

        if ($results->isEmpty()) {
            warning('No results found.');

            return self::SUCCESS;
        }

        // Filter by sender if specified
        if ($sender) {
            $results = $results->filter(fn (array $r) => ($r['payload']['sender'] ?? '') === $sender);
        }

        info($results->count()." results for \"{$query}\":\n");

        foreach ($results as $i => $result) {
            $payload = $result['payload'];
            $score = round($result['score'], 3);
            $name = $payload['conversation_name'] ?? 'Untitled';
            $role = $payload['sender'] ?? '?';
            $date = substr($payload['conversation_created_at'] ?? '', 0, 10);
            $text = $payload['text'] ?? '';

            // Truncate text for display
            if (strlen($text) > 300) {
                $text = substr($text, 0, 300).'...';
            }

            $this->line("<fg=yellow>[{$score}]</> <fg=cyan>{$name}</> <fg=gray>({$role}, {$date})</>");
            $this->line("  {$text}");
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
