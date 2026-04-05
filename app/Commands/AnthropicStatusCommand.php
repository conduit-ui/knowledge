<?php

declare(strict_types=1);

namespace App\Commands;

use App\Integrations\Qdrant\QdrantConnector;
use App\Integrations\Qdrant\Requests\GetCollectionInfo;
use App\Integrations\Qdrant\Requests\ScrollPoints;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class AnthropicStatusCommand extends Command
{
    protected $signature = 'anthropic:status
                            {--collection=anthropic_conversations : Qdrant collection name}';

    protected $description = 'Show status of imported Anthropic conversation data';

    public function handle(): int
    {
        $collection = $this->option('collection');

        $qdrant = new QdrantConnector(
            host: config('search.qdrant.host', 'localhost'),
            port: (int) config('search.qdrant.port', 6333),
            apiKey: config('search.qdrant.api_key'),
        );

        $response = $qdrant->send(new GetCollectionInfo($collection));

        if (! $response->successful()) {
            warning("Collection '{$collection}' does not exist");

            return self::FAILURE;
        }

        $result = $response->json('result');
        $pointsCount = $result['points_count'] ?? 0;
        $indexedVectors = $result['indexed_vectors_count'] ?? 0;
        $status = $result['status'] ?? 'unknown';

        // Count unique conversations
        $conversations = [];
        $offset = null;
        while (true) {
            $scrollResponse = $qdrant->send(new ScrollPoints($collection, 250, null, $offset));
            if (! $scrollResponse->successful()) {
                break;
            }

            $data = $scrollResponse->json();
            $points = $data['result']['points'] ?? [];
            if (empty($points)) {
                break;
            }

            foreach ($points as $point) {
                $uuid = $point['payload']['conversation_uuid'] ?? '';
                if ($uuid !== '') {
                    $conversations[$uuid] = $point['payload']['conversation_name'] ?? 'Untitled';
                }
            }

            $offset = $data['result']['next_page_offset'] ?? null;
            if ($offset === null) {
                break;
            }
        }

        $this->newLine();
        table(
            ['Metric', 'Value'],
            [
                ['Collection', $collection],
                ['Status', $status],
                ['Total Points', number_format($pointsCount)],
                ['Indexed Vectors', number_format($indexedVectors)],
                ['Conversations', number_format(count($conversations))],
            ]
        );

        return self::SUCCESS;
    }
}
