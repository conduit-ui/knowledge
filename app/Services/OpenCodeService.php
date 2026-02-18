<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class OpenCodeService
{
    protected ?Client $client = null;

    public function __construct(
        private readonly ?string $url = null,
        private readonly ?string $token = null,
        private readonly int $timeout = 20,
    ) {}

    public function enabled(): bool
    {
        return config('opencode.enabled', false) === true
            && is_string($this->url ?? config('opencode.url'))
            && ($this->url ?? config('opencode.url')) !== ''
            && is_string($this->token ?? config('opencode.token'))
            && ($this->token ?? config('opencode.token')) !== '';
    }

    /**
     * Attempt to enrich an entry via OpenCode. Falls back silently on failure.
     *
     * @param  array{id: string|int, title: string, content: string, tags?: array<string>}  $entry
     * @return array{id: string|int, title: string, content: string, tags?: array<string>}
     */
    public function enrich(array $entry): array
    {
        if (! $this->enabled()) {
            return $entry;
        }

        $url = rtrim((string) ($this->url ?? config('opencode.url')), '/').'/api/enrich';
        $token = (string) ($this->token ?? config('opencode.token'));

        try {
            $response = $this->getClient()->post($url, [
                'json' => [
                    'title' => $entry['title'],
                    'content' => $entry['content'],
                    'tags' => $entry['tags'] ?? [],
                    'source' => 'github',
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/json',
                ],
                'timeout' => $this->timeout,
            ]);

            if ($response->getStatusCode() !== 200) {
                return $entry;
            }

            /** @var array<string, mixed>|null $json */
            $json = json_decode((string) $response->getBody(), true);
            if (! is_array($json)) {
                return $entry;
            }

            $newTags = $this->mergeTags($entry['tags'] ?? [], Arr::get($json, 'tags', []));
            $summary = Arr::get($json, 'summary');

            if (is_string($summary) && trim($summary) !== '') {
                $entry['content'] = $entry['content']."\n\n---\nOpenCode Summary:\n".Str::limit(trim($summary), 1200);
            }

            if ($newTags !== []) {
                $entry['tags'] = $newTags;
            }

            return $entry;
        } catch (Throwable) {
            return $entry;
        }
    }

    protected function getClient(): Client
    {
        return $this->client ??= new Client;
    }

    /**
     * @param  array<string>  $existing
     * @return array<string>
     */
    private function mergeTags(array $existing, mixed $incoming): array
    {
        $incomingTags = [];
        if (is_array($incoming)) {
            foreach ($incoming as $tag) {
                if (is_string($tag) && $tag !== '') {
                    $incomingTags[] = $tag;
                }
            }
        }

        return array_values(array_unique([...$existing, ...$incomingTags]));
    }
}
