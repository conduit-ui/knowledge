<?php

namespace App\Providers;

use App\Contracts\EmbeddingServiceInterface;
use App\Services\SemanticSearchService;
use App\Services\StubEmbeddingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register embedding service
        $this->app->singleton(EmbeddingServiceInterface::class, function () {
            // In the future, this can be changed to instantiate different providers
            // based on config('search.embedding_provider')
            return new StubEmbeddingService;
        });

        // Register semantic search service
        $this->app->singleton(SemanticSearchService::class, function ($app) {
            return new SemanticSearchService(
                $app->make(EmbeddingServiceInterface::class),
                config('search.semantic_enabled', false)
            );
        });
    }
}
