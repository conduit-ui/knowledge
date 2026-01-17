<?php

declare(strict_types=1);

use App\Contracts\ChromaDBClientInterface;
use App\Contracts\EmbeddingServiceInterface;
use App\Contracts\FullTextSearchInterface;
use App\Contracts\HealthCheckInterface;
use App\Services\ChromaDBClient;
use App\Services\ChromaDBEmbeddingService;
use App\Services\HealthCheckService;
use App\Services\IssueAnalyzerService;
use App\Services\KnowledgePathService;
use App\Services\OllamaService;
use App\Services\PullRequestService;
use App\Services\QdrantService;
use App\Services\QualityGateService;
use App\Services\RuntimeEnvironment;
use App\Services\StubEmbeddingService;
use App\Services\StubFtsService;
use App\Services\TestExecutorService;
use App\Services\TodoExecutorService;

describe('AppServiceProvider', function () {
    it('registers RuntimeEnvironment', function () {
        $service = app(RuntimeEnvironment::class);

        expect($service)->toBeInstanceOf(RuntimeEnvironment::class);
    });

    it('registers KnowledgePathService', function () {
        $service = app(KnowledgePathService::class);

        expect($service)->toBeInstanceOf(KnowledgePathService::class);
    });

    it('registers ChromaDBClient', function () {
        $client = app(ChromaDBClientInterface::class);

        expect($client)->toBeInstanceOf(ChromaDBClient::class);
    });

    it('registers StubEmbeddingService by default', function () {
        config(['search.embedding_provider' => 'none']);

        // Force rebinding
        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(StubEmbeddingService::class);
    });

    it('registers ChromaDBEmbeddingService when provider is chromadb', function () {
        config(['search.embedding_provider' => 'chromadb']);

        // Force rebinding
        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(ChromaDBEmbeddingService::class);
    });

    it('registers ChromaDBEmbeddingService when provider is qdrant', function () {
        config(['search.embedding_provider' => 'qdrant']);

        // Force rebinding
        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(ChromaDBEmbeddingService::class);
    });

    it('registers QdrantService with all configuration options', function () {
        config([
            'search.embedding_dimension' => 384,
            'search.minimum_similarity' => 0.7,
            'search.qdrant.cache_ttl' => 604800,
            'search.qdrant.secure' => false,
        ]);

        // Force rebinding
        app()->forgetInstance(QdrantService::class);

        $service = app(QdrantService::class);

        expect($service)->toBeInstanceOf(QdrantService::class);
    });

    it('registers QdrantService with secure connection', function () {
        config([
            'search.embedding_dimension' => 1536,
            'search.minimum_similarity' => 0.8,
            'search.qdrant.cache_ttl' => 86400,
            'search.qdrant.secure' => true,
        ]);

        // Force rebinding
        app()->forgetInstance(QdrantService::class);

        $service = app(QdrantService::class);

        expect($service)->toBeInstanceOf(QdrantService::class);
    });

    it('registers StubFtsService (Qdrant handles vector search)', function () {
        // Force rebinding
        app()->forgetInstance(FullTextSearchInterface::class);

        $service = app(FullTextSearchInterface::class);

        expect($service)->toBeInstanceOf(StubFtsService::class);
    });

    it('registers StubFtsService when provider is stub', function () {
        config(['search.fts_provider' => 'stub']);

        // Force rebinding
        app()->forgetInstance(FullTextSearchInterface::class);

        $service = app(FullTextSearchInterface::class);

        expect($service)->toBeInstanceOf(StubFtsService::class);
    });

    it('registers StubFtsService for unknown provider', function () {
        config(['search.fts_provider' => 'unknown']);

        // Force rebinding
        app()->forgetInstance(FullTextSearchInterface::class);

        $service = app(FullTextSearchInterface::class);

        expect($service)->toBeInstanceOf(StubFtsService::class);
    });

    it('registers OllamaService', function () {
        $service = app(OllamaService::class);

        expect($service)->toBeInstanceOf(OllamaService::class);
    });

    it('registers IssueAnalyzerService', function () {
        $service = app(IssueAnalyzerService::class);

        expect($service)->toBeInstanceOf(IssueAnalyzerService::class);
    });

    it('registers TestExecutorService', function () {
        $service = app(TestExecutorService::class);

        expect($service)->toBeInstanceOf(TestExecutorService::class);
    });

    it('registers QualityGateService', function () {
        $service = app(QualityGateService::class);

        expect($service)->toBeInstanceOf(QualityGateService::class);
    });

    it('registers TodoExecutorService', function () {
        $service = app(TodoExecutorService::class);

        expect($service)->toBeInstanceOf(TodoExecutorService::class);
    });

    it('registers PullRequestService', function () {
        $service = app(PullRequestService::class);

        expect($service)->toBeInstanceOf(PullRequestService::class);
    });

    it('registers HealthCheckService', function () {
        $service = app(HealthCheckInterface::class);

        expect($service)->toBeInstanceOf(HealthCheckService::class);
    });

    it('uses custom ChromaDB host and port configuration', function () {
        config([
            'search.chromadb.host' => 'custom-host',
            'search.chromadb.port' => 9000,
        ]);

        // Force rebinding
        app()->forgetInstance(ChromaDBClientInterface::class);

        $client = app(ChromaDBClientInterface::class);

        expect($client)->toBeInstanceOf(ChromaDBClient::class);
    });

    it('uses custom embedding server and model for chromadb provider', function () {
        config([
            'search.embedding_provider' => 'chromadb',
            'search.chromadb.embedding_server' => 'http://custom:8001',
            'search.chromadb.model' => 'custom-model',
        ]);

        // Force rebinding
        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(ChromaDBEmbeddingService::class);
    });

    it('uses custom embedding server and model for qdrant provider', function () {
        config([
            'search.embedding_provider' => 'qdrant',
            'search.qdrant.embedding_server' => 'http://qdrant-custom:8001',
            'search.qdrant.model' => 'qdrant-model',
        ]);

        // Force rebinding
        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(ChromaDBEmbeddingService::class);
    });
});
