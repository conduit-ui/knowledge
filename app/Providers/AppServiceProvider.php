<?php

namespace App\Providers;

use App\Contracts\EmbeddingServiceInterface;
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

        // Qdrant vector database service
        $this->app->singleton(QdrantService::class, fn ($app): \App\Services\QdrantService => new QdrantService(
            $app->make(EmbeddingServiceInterface::class),
            (int) config('search.embedding_dimension', 1024),
            (float) config('search.minimum_similarity', 0.7),
            (int) config('search.qdrant.cache_ttl', 604800),
            (bool) config('search.qdrant.secure', false)
        ));
    }
}
