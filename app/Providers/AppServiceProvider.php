<?php

namespace App\Providers;

use App\Contracts\EmbeddingServiceInterface;
use App\Services\DeletionTracker;
use App\Services\KnowledgeCacheService;
use App\Services\KnowledgePathService;
use App\Services\QdrantService;
use App\Services\RuntimeEnvironment;
use App\Services\StubEmbeddingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $viewPath = resource_path('views');
        $runtime = $this->app->make(RuntimeEnvironment::class);
        $cachePath = $runtime->cachePath('views');

        // @codeCoverageIgnoreStart
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }
        // @codeCoverageIgnoreEnd

        config(['view.paths' => [$viewPath]]);
        config(['view.compiled' => $cachePath]);

        // Load user config from ~/.knowledge/config.json and merge into Laravel config
        $this->loadUserConfig();
    }

    /**
     * Load user configuration from ~/.knowledge/config.json and merge into Laravel config.
     */
    private function loadUserConfig(): void
    {
        $pathService = $this->app->make(KnowledgePathService::class);
        $configPath = $pathService->getKnowledgeDirectory().'/config.json';

        if (! file_exists($configPath)) {
            return;
        }

        $content = file_get_contents($configPath);
        // @codeCoverageIgnoreStart
        // Defensive: file_get_contents only fails on read errors after file_exists passed
        if ($content === false) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $userConfig = json_decode($content, true);
        if (! is_array($userConfig)) {
            return;
        }

        // Map user config keys to Laravel config keys
        // qdrant.url -> parse to get host and port
        if (isset($userConfig['qdrant']['url']) && is_string($userConfig['qdrant']['url'])) {
            $parsedUrl = parse_url($userConfig['qdrant']['url']);
            if (is_array($parsedUrl)) {
                if (isset($parsedUrl['host'])) {
                    config(['search.qdrant.host' => $parsedUrl['host']]);
                }
                if (isset($parsedUrl['port'])) {
                    config(['search.qdrant.port' => $parsedUrl['port']]);
                }
                // Determine if secure based on scheme
                if (isset($parsedUrl['scheme'])) {
                    config(['search.qdrant.secure' => $parsedUrl['scheme'] === 'https']);
                }
            }
        }

        // qdrant.collection -> search.qdrant.collection
        if (isset($userConfig['qdrant']['collection']) && is_string($userConfig['qdrant']['collection'])) {
            config(['search.qdrant.collection' => $userConfig['qdrant']['collection']]);
        }

        // embeddings.url -> search.qdrant.embedding_server
        if (isset($userConfig['embeddings']['url']) && is_string($userConfig['embeddings']['url'])) {
            config(['search.qdrant.embedding_server' => $userConfig['embeddings']['url']]);
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Runtime environment (must be first)
        $this->app->singleton(RuntimeEnvironment::class, fn (): \App\Services\RuntimeEnvironment => new RuntimeEnvironment);

        // Knowledge path service
        $this->app->singleton(KnowledgePathService::class, fn ($app): \App\Services\KnowledgePathService => new KnowledgePathService(
            $app->make(RuntimeEnvironment::class)
        ));

        // Embedding service
        $this->app->singleton(EmbeddingServiceInterface::class, function (): \App\Services\StubEmbeddingService|\App\Services\EmbeddingService {
            if (config('search.embedding_provider') === 'none') {
                return new StubEmbeddingService;
            }

            return new \App\Services\EmbeddingService(
                config('search.qdrant.embedding_server', 'http://localhost:8001')
            );
        });

        // Knowledge cache service
        $this->app->singleton(KnowledgeCacheService::class, fn (): \App\Services\KnowledgeCacheService => new KnowledgeCacheService);

        // Deletion tracker service
        $this->app->singleton(DeletionTracker::class, fn ($app): \App\Services\DeletionTracker => new DeletionTracker(
            $app->make(KnowledgePathService::class)
        ));

        // Qdrant vector database service
        $this->app->singleton(QdrantService::class, fn ($app): \App\Services\QdrantService => new QdrantService(
            $app->make(EmbeddingServiceInterface::class),
            (int) config('search.embedding_dimension', 1024),
            (float) config('search.minimum_similarity', 0.7),
            (int) config('search.qdrant.cache_ttl', 604800),
            (bool) config('search.qdrant.secure', false),
            cacheService: $app->make(KnowledgeCacheService::class)
        ));
    }
}
