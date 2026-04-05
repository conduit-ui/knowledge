<?php

declare(strict_types=1);

namespace App\Commands;

use App\Integrations\Qdrant\QdrantConnector;
use App\Integrations\Qdrant\Requests\CreateCollection;
use App\Integrations\Qdrant\Requests\GetCollectionInfo;
use App\Integrations\Qdrant\Requests\ScrollPoints;
use App\Integrations\Qdrant\Requests\UpsertPoints;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class AnthropicImportCommand extends Command
{
    protected $signature = 'anthropic:import
                            {file : Path to Anthropic data export zip}
                            {--dry-run : Show what would be imported without importing}
                            {--collection=anthropic_conversations : Qdrant collection name}
                            {--batch-size=4 : Texts per embedding request}
                            {--concurrency=2 : Concurrent embedding requests per server}';

    protected $description = 'Import Anthropic conversation data into Qdrant with deduplication';

    private const VECTOR_SIZE = 1024;

    private const MAX_CHUNK_CHARS = 800;

    private const EMBED_PORTS = [8001, 8003, 8004, 8005];

    private QdrantConnector $qdrant;

    /** @var list<string> */
    private array $embedServers = [];

    private int $serverIndex = 0;

    public function handle(): int
    {
        // Anthropic exports can be 300MB+ JSON — need headroom
        ini_set('memory_limit', '1G');

        $file = $this->argument('file');
        $collection = $this->option('collection');

        if (! file_exists($file)) {
            error("File not found: {$file}");

            return self::FAILURE;
        }

        $this->qdrant = new QdrantConnector(
            host: config('search.qdrant.host', 'localhost'),
            port: (int) config('search.qdrant.port', 6333),
            apiKey: config('search.qdrant.api_key'),
        );

        // Discover embedding servers
        if (! $this->option('dry-run')) {
            $this->embedServers = $this->discoverServers();
            if (empty($this->embedServers)) {
                error('No embedding servers found on ports '.implode(', ', self::EMBED_PORTS));

                return self::FAILURE;
            }
            info(count($this->embedServers).' embedding server(s) discovered');
        }

        // Ensure collection
        $this->ensureCollection($collection);

        // Extract and parse
        $conversations = spin(
            fn () => $this->extractConversations($file),
            'Extracting conversations...'
        );

        info(count($conversations).' conversations in dump');

        // Deduplicate
        $existingUuids = spin(
            fn () => $this->getExistingConversationUuids($collection),
            'Checking for existing conversations...'
        );

        $newConversations = array_filter(
            $conversations,
            fn (array $c) => ! isset($existingUuids[$c['uuid'] ?? ''])
                && ! empty($c['chat_messages'])
        );
        $newConversations = array_values($newConversations);

        $skipped = count($conversations) - count($newConversations);
        info(count($newConversations)." new conversations ({$skipped} already imported)");

        if (empty($newConversations)) {
            info('Nothing new to import!');

            return self::SUCCESS;
        }

        // Prepare chunks
        $chunks = $this->prepareChunks($newConversations);
        info(count($chunks).' chunks prepared');

        if ($this->option('dry-run')) {
            $this->showDryRun($newConversations, $chunks);

            return self::SUCCESS;
        }

        // Embed and ingest
        $this->ingest($chunks, $collection);

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function discoverServers(): array
    {
        $servers = [];
        $client = new Client(['timeout' => 5, 'connect_timeout' => 2, 'http_errors' => false]);

        foreach (self::EMBED_PORTS as $port) {
            try {
                $response = $client->post("http://localhost:{$port}/embed", [
                    'json' => ['texts' => ['test']],
                ]);

                if ($response->getStatusCode() === 200) {
                    $data = json_decode((string) $response->getBody(), true);
                    $dim = count($data['embeddings'][0] ?? []);
                    if ($dim === self::VECTOR_SIZE) {
                        $servers[] = "http://localhost:{$port}";
                    } else {
                        warning("Port {$port}: wrong dimension ({$dim})");
                    }
                }
            } catch (\Throwable) {
                // Server not available
            }
        }

        return $servers;
    }

    private function nextServer(): string
    {
        $server = $this->embedServers[$this->serverIndex % count($this->embedServers)];
        $this->serverIndex++;

        return $server;
    }

    private function ensureCollection(string $collection): void
    {
        $response = $this->qdrant->send(new GetCollectionInfo($collection));

        if ($response->successful()) {
            return;
        }

        $this->qdrant->send(new CreateCollection($collection, self::VECTOR_SIZE));
        info("Created collection '{$collection}'");
    }

    /**
     * @return array<string, true>
     */
    private function getExistingConversationUuids(string $collection): array
    {
        $existing = [];
        $offset = null;

        while (true) {
            $response = $this->qdrant->send(
                new ScrollPoints($collection, 250, null, $offset)
            );

            if (! $response->successful()) {
                break;
            }

            $data = $response->json();
            $points = $data['result']['points'] ?? [];

            if (empty($points)) {
                break;
            }

            foreach ($points as $point) {
                $uuid = $point['payload']['conversation_uuid'] ?? '';
                if ($uuid !== '') {
                    $existing[$uuid] = true;
                }
            }

            $offset = $data['result']['next_page_offset'] ?? null;
            if ($offset === null) {
                break;
            }
        }

        return $existing;
    }

    /**
     * @return list<array{uuid: string, name: string, chat_messages: list<mixed>}>
     */
    private function extractConversations(string $zipPath): array
    {
        $tmpDir = sys_get_temp_dir().'/anthropic-import-'.Str::random(8);
        mkdir($tmpDir, 0755, true);

        $zip = new \ZipArchive;
        $zip->open($zipPath);
        $zip->extractTo($tmpDir);
        $zip->close();

        $convFile = $tmpDir.'/conversations.json';
        if (! file_exists($convFile)) {
            error('conversations.json not found in zip');

            return [];
        }

        $conversations = json_decode(file_get_contents($convFile), true);

        // Cleanup
        array_map('unlink', glob($tmpDir.'/*'));
        rmdir($tmpDir);

        return $conversations;
    }

    /**
     * @return list<array{text: string, payload: array<string, mixed>}>
     */
    private function prepareChunks(array $conversations): array
    {
        $chunks = [];

        foreach ($conversations as $conv) {
            $convUuid = $conv['uuid'] ?? '';
            $convName = $conv['name'] ?? 'Untitled';
            $convCreated = $conv['created_at'] ?? '';

            foreach ($conv['chat_messages'] ?? [] as $mi => $msg) {
                $text = $this->extractText($msg);
                if (strlen(trim($text)) < 20) {
                    continue;
                }

                foreach ($this->chunkText($text) as $ci => $chunk) {
                    $chunks[] = [
                        'text' => $chunk,
                        'payload' => [
                            'conversation_uuid' => $convUuid,
                            'conversation_name' => $convName,
                            'conversation_created_at' => $convCreated,
                            'sender' => $msg['sender'] ?? 'unknown',
                            'turn_index' => $mi,
                            'chunk_index' => $ci,
                            'message_created_at' => $msg['created_at'] ?? '',
                            'text' => $chunk,
                        ],
                    ];
                }
            }
        }

        return $chunks;
    }

    private function extractText(array $message): string
    {
        if (! empty($message['text'])) {
            return $message['text'];
        }

        $parts = [];
        foreach ($message['content'] ?? [] as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $parts[] = $block['text'] ?? '';
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @return list<string>
     */
    private function chunkText(string $text): array
    {
        if ($text === '' || strlen($text) <= self::MAX_CHUNK_CHARS) {
            return $text !== '' ? [$text] : [];
        }

        $chunks = [];
        $remaining = $text;

        while ($remaining !== '') {
            if (strlen($remaining) <= self::MAX_CHUNK_CHARS) {
                $chunks[] = $remaining;
                break;
            }

            $splitAt = self::MAX_CHUNK_CHARS;
            foreach (["\n\n", "\n", '. ', ' '] as $sep) {
                $idx = strrpos(substr($remaining, 0, self::MAX_CHUNK_CHARS), $sep);
                if ($idx !== false && $idx > self::MAX_CHUNK_CHARS / 3) {
                    $splitAt = $idx + strlen($sep);
                    break;
                }
            }

            $chunk = trim(substr($remaining, 0, $splitAt));
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
            $remaining = trim(substr($remaining, $splitAt));
        }

        return $chunks;
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>|null>
     */
    private function embedBatch(array $texts, string $server): array
    {
        $client = new Client(['timeout' => 60, 'connect_timeout' => 5, 'http_errors' => false]);

        try {
            $response = $client->post("{$server}/embed", [
                'json' => ['texts' => $texts],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode((string) $response->getBody(), true);

                return $data['embeddings'] ?? array_fill(0, count($texts), null);
            }
        } catch (\Throwable) {
            // Fall through
        }

        // Fallback: try other servers
        foreach ($this->embedServers as $fallback) {
            if ($fallback === $server) {
                continue;
            }

            try {
                $response = $client->post("{$fallback}/embed", [
                    'json' => ['texts' => $texts],
                ]);

                if ($response->getStatusCode() === 200) {
                    return json_decode((string) $response->getBody(), true)['embeddings'];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return array_fill(0, count($texts), null);
    }

    private function ingest(array $chunks, string $collection): void
    {
        $batchSize = (int) $this->option('batch-size');
        $concurrency = (int) $this->option('concurrency');
        $totalWorkers = $concurrency * count($this->embedServers);

        info("Embedding with {$totalWorkers} workers across ".count($this->embedServers).' servers...');

        $batches = array_chunk($chunks, $batchSize);
        $pointsBuffer = [];
        $totalPoints = 0;
        $failed = 0;

        $progressBar = $this->output->createProgressBar(count($chunks));
        $progressBar->start();

        // Process batches sequentially with round-robin across servers
        // Using pools of $totalWorkers concurrent requests
        $batchQueue = $batches;
        while (! empty($batchQueue)) {
            $currentBatch = array_splice($batchQueue, 0, $totalWorkers);
            $results = [];

            // Embed each sub-batch
            foreach ($currentBatch as $batch) {
                $texts = array_column($batch, 'text');
                $server = $this->nextServer();
                $embeddings = $this->embedBatch($texts, $server);

                foreach ($batch as $i => $chunk) {
                    $vector = $embeddings[$i] ?? null;
                    if ($vector !== null && count($vector) === self::VECTOR_SIZE) {
                        $pointsBuffer[] = [
                            'id' => (string) Str::uuid(),
                            'vector' => $vector,
                            'payload' => $chunk['payload'],
                        ];
                        $totalPoints++;
                    } else {
                        $failed++;
                    }
                    $progressBar->advance();
                }
            }

            // Flush to Qdrant in batches of 200
            if (count($pointsBuffer) >= 200) {
                $this->qdrant->send(new UpsertPoints($collection, $pointsBuffer));
                $pointsBuffer = [];
            }
        }

        // Flush remaining
        if (! empty($pointsBuffer)) {
            $this->qdrant->send(new UpsertPoints($collection, $pointsBuffer));
        }

        $progressBar->finish();
        $this->newLine(2);

        info("Ingested {$totalPoints} points from ".count($chunks)." chunks");
        if ($failed > 0) {
            warning("{$failed} chunks failed to embed");
        }
    }

    private function showDryRun(array $conversations, array $chunks): void
    {
        $this->newLine();
        info('Dry run summary:');
        $this->line("  Conversations: ".count($conversations));
        $this->line("  Chunks: ".count($chunks));
        $this->newLine();

        $rows = [];
        foreach (array_slice($conversations, 0, 15) as $c) {
            $rows[] = [
                Str::limit($c['name'] ?? 'Untitled', 50),
                count($c['chat_messages'] ?? []),
                $c['created_at'] ?? '',
            ];
        }

        if (! empty($rows)) {
            table(['Conversation', 'Messages', 'Created'], $rows);
        }

        if (count($conversations) > 15) {
            $this->line('  ...and '.(count($conversations) - 15).' more');
        }
    }
}
