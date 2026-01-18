<?php

namespace App\Providers;

use App\Contracts\DockerServiceInterface;
use App\Contracts\EmbeddingServiceInterface;
use App\Contracts\HealthCheckInterface;
use App\Services\DockerService;
use App\Services\HealthCheckService;
use App\Services\IssueAnalyzerService;
use App\Services\KnowledgePathService;
use App\Services\OllamaService;
use App\Services\PullRequestService;
use App\Services\QdrantService;
use App\Services\QualityGateService;
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
        $this->app->singleton(RuntimeEnvironment::class, fn () => new RuntimeEnvironment);

        // Knowledge path service
        $this->app->singleton(KnowledgePathService::class, fn ($app) => new KnowledgePathService(
            $app->make(RuntimeEnvironment::class)
        ));

        // Docker service
        // @codeCoverageIgnoreStart
        $this->app->singleton(DockerServiceInterface::class, fn () => new DockerService);
        // @codeCoverageIgnoreEnd

        // Embedding service
        $this->app->singleton(EmbeddingServiceInterface::class, function () {
            if (config('search.embedding_provider') === 'none') {
                return new StubEmbeddingService;
            }

            return new \App\Services\EmbeddingService(
                config('search.qdrant.embedding_server', 'http://localhost:8001')
            );
        });

        // Qdrant vector database service
        $this->app->singleton(QdrantService::class, fn ($app) => new QdrantService(
            $app->make(EmbeddingServiceInterface::class),
            (int) config('search.embedding_dimension', 1024),
            (float) config('search.minimum_similarity', 0.7),
            (int) config('search.qdrant.cache_ttl', 604800),
            (bool) config('search.qdrant.secure', false)
        ));

        // Ollama service for AI analysis
        $this->app->singleton(OllamaService::class, fn () => new OllamaService);

        // Issue analyzer service
        $this->app->singleton(IssueAnalyzerService::class, fn ($app) => new IssueAnalyzerService(
            $app->make(OllamaService::class)
        ));

        // Quality gate service
        $this->app->singleton(QualityGateService::class, fn () => new QualityGateService);

        // Pull request service
        $this->app->singleton(PullRequestService::class, fn () => new PullRequestService);

        // Health check service
        $this->app->singleton(HealthCheckInterface::class, fn () => new HealthCheckService);
    }
}
