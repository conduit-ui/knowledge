<?php

namespace App\Providers;

use App\Contracts\ChromaDBClientInterface;
use App\Contracts\DockerServiceInterface;
use App\Contracts\EmbeddingServiceInterface;
use App\Contracts\FullTextSearchInterface;
use App\Services\ChromaDBClient;
use App\Services\ChromaDBEmbeddingService;
use App\Services\ChromaDBIndexService;
use App\Services\DatabaseInitializer;
use App\Services\QdrantService;
use App\Services\DockerService;
use App\Services\IssueAnalyzerService;
use App\Services\KnowledgePathService;
use App\Services\OllamaService;
use App\Services\PullRequestService;
use App\Services\QualityGateService;
use App\Services\RuntimeEnvironment;
use App\Services\SemanticSearchService;
use App\Services\SQLiteFtsService;
use App\Services\StubEmbeddingService;
use App\Services\StubFtsService;
use App\Services\TestExecutorService;
use App\Services\TodoExecutorService;
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

        // Use RuntimeEnvironment for cache path resolution
        $runtime = $this->app->make(RuntimeEnvironment::class);
        $cachePath = $runtime->cachePath('views');

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
        // Register runtime environment (must be first)
        $this->app->singleton(RuntimeEnvironment::class, function () {
            return new RuntimeEnvironment;
        });

        // Register knowledge path service
        $this->app->singleton(KnowledgePathService::class, function ($app) {
            return new KnowledgePathService($app->make(RuntimeEnvironment::class));
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
            $provider = config('search.embedding_provider', 'qdrant');

            return match ($provider) {
                'chromadb' => new ChromaDBEmbeddingService(
                    config('search.chromadb.embedding_server', 'http://localhost:8001'),
                    config('search.chromadb.model', 'all-MiniLM-L6-v2')
                ),
                'qdrant' => new ChromaDBEmbeddingService(
                    config('search.qdrant.embedding_server', 'http://localhost:8001'),
                    config('search.qdrant.model', 'all-MiniLM-L6-v2')
                ),
                default => new StubEmbeddingService,
            };
        });

        // Register ChromaDB index service
        // @codeCoverageIgnoreStart
        // Service class does not exist - planned for future implementation
        $this->app->singleton(ChromaDBIndexService::class, function ($app) {
            return new ChromaDBIndexService(
                $app->make(ChromaDBClientInterface::class),
                $app->make(EmbeddingServiceInterface::class)
            );
        });
        // @codeCoverageIgnoreEnd

        // Register semantic search service
        // @codeCoverageIgnoreStart
        // Service class does not exist - planned for future implementation
        $this->app->singleton(SemanticSearchService::class, function ($app) {
            $chromaDBEnabled = (bool) config('search.chromadb.enabled', false);

            return new SemanticSearchService(
                $app->make(EmbeddingServiceInterface::class),
                (bool) config('search.semantic_enabled', false),
                $chromaDBEnabled ? $app->make(ChromaDBClientInterface::class) : null,
                $chromaDBEnabled
            );
        });
        // @codeCoverageIgnoreEnd

        // Register Qdrant service
        $this->app->singleton(QdrantService::class, function ($app) {
            return new QdrantService(
                $app->make(EmbeddingServiceInterface::class),
                (int) config('search.embedding_dimension', 384),
                (float) config('search.minimum_similarity', 0.7),
                (int) config('search.qdrant.cache_ttl', 604800),
                (bool) config('search.qdrant.secure', false)
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

        // Register Ollama service for AI analysis
        $this->app->singleton(OllamaService::class, function () {
            return new OllamaService;
        });

        // Register issue analyzer service
        $this->app->singleton(IssueAnalyzerService::class, function ($app) {
            return new IssueAnalyzerService(
                $app->make(OllamaService::class)
            );
        });

        // Register test executor service
        $this->app->singleton(TestExecutorService::class, function ($app) {
            return new TestExecutorService(
                $app->make(OllamaService::class)
            );
        });

        // Register quality gate service
        $this->app->singleton(QualityGateService::class, function () {
            return new QualityGateService;
        });

        // Register todo executor service
        $this->app->singleton(TodoExecutorService::class, function ($app) {
            return new TodoExecutorService(
                $app->make(OllamaService::class),
                $app->make(TestExecutorService::class),
                $app->make(QualityGateService::class)
            );
        });

        // Register pull request service
        $this->app->singleton(PullRequestService::class, function () {
            return new PullRequestService;
        });
    }
}
