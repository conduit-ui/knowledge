<?php

namespace App\Providers;

use App\Contracts\ChromaDBClientInterface;
use App\Contracts\DockerServiceInterface;
use App\Contracts\EmbeddingServiceInterface;
use App\Contracts\FullTextSearchInterface;
use App\Services\ChromaDBClient;
use App\Services\DockerService;
use App\Services\ChromaDBEmbeddingService;
use App\Services\ChromaDBIndexService;
use App\Services\DatabaseInitializer;
use App\Services\KnowledgePathService;
use App\Services\SemanticSearchService;
use App\Services\SQLiteFtsService;
use App\Services\StubEmbeddingService;
use App\Services\StubFtsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure view paths and caching
        $viewPath = resource_path('views');
        $cachePath = storage_path('framework/views');

        // @codeCoverageIgnoreStart
        // Defensive mkdir - only executes when cache directory doesn't exist
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
        // Register knowledge path service
        $this->app->singleton(KnowledgePathService::class, function () {
            return new KnowledgePathService;
        });

        // Register Docker service
        // @codeCoverageIgnoreStart
        $this->app->singleton(DockerServiceInterface::class, function () {
            return new DockerService;
        });
        // @codeCoverageIgnoreEnd

        // Register database initializer
        $this->app->singleton(DatabaseInitializer::class, function ($app) {
            return new DatabaseInitializer($app->make(KnowledgePathService::class));
        });

        // Register ChromaDB client
        $this->app->singleton(ChromaDBClientInterface::class, function () {
            $host = config('search.chromadb.host', 'localhost');
            $port = config('search.chromadb.port', 8000);
            $baseUrl = "http://{$host}:{$port}";

            return new ChromaDBClient($baseUrl);
        });

        // Register embedding service
        $this->app->singleton(EmbeddingServiceInterface::class, function ($app) {
            $provider = config('search.embedding_provider', 'none');

            return match ($provider) {
                'chromadb' => new ChromaDBEmbeddingService(
                    config('search.chromadb.embedding_server', 'http://localhost:8001'),
                    config('search.chromadb.model', 'all-MiniLM-L6-v2')
                ),
                default => new StubEmbeddingService,
            };
        });

        // Register ChromaDB index service
        $this->app->singleton(ChromaDBIndexService::class, function ($app) {
            return new ChromaDBIndexService(
                $app->make(ChromaDBClientInterface::class),
                $app->make(EmbeddingServiceInterface::class)
            );
        });

        // Register semantic search service
        $this->app->singleton(SemanticSearchService::class, function ($app) {
            $chromaDBEnabled = (bool) config('search.chromadb.enabled', false);

            return new SemanticSearchService(
                $app->make(EmbeddingServiceInterface::class),
                (bool) config('search.semantic_enabled', false),
                $chromaDBEnabled ? $app->make(ChromaDBClientInterface::class) : null,
                $chromaDBEnabled
            );
        });

        // Register full-text search service
        $this->app->singleton(FullTextSearchInterface::class, function () {
            $provider = config('search.fts_provider', 'sqlite');

            return match ($provider) {
                'sqlite' => new SQLiteFtsService,
                default => new StubFtsService,
            };
        });
    }
}
