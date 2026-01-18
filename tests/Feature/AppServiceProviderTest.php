<?php

declare(strict_types=1);

use App\Contracts\DockerServiceInterface;
use App\Contracts\EmbeddingServiceInterface;
use App\Contracts\HealthCheckInterface;
use App\Services\DockerService;
use App\Services\EmbeddingService;
use App\Services\HealthCheckService;
use App\Services\IssueAnalyzerService;
use App\Services\KnowledgePathService;
use App\Services\OllamaService;
use App\Services\PullRequestService;
use App\Services\QdrantService;
use App\Services\QualityGateService;
use App\Services\RuntimeEnvironment;
use App\Services\StubEmbeddingService;

describe('AppServiceProvider', function () {
    it('registers RuntimeEnvironment', function () {
        $service = app(RuntimeEnvironment::class);

        expect($service)->toBeInstanceOf(RuntimeEnvironment::class);
    });

    it('registers KnowledgePathService', function () {
        $service = app(KnowledgePathService::class);

        expect($service)->toBeInstanceOf(KnowledgePathService::class);
    });

    it('registers DockerService', function () {
        $service = app(DockerServiceInterface::class);

        expect($service)->toBeInstanceOf(DockerService::class);
    });

    it('registers StubEmbeddingService when provider is none', function () {
        config(['search.embedding_provider' => 'none']);

        // Force rebinding
        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(StubEmbeddingService::class);
    });

    it('registers EmbeddingService for other providers', function () {
        config(['search.embedding_provider' => 'qdrant']);

        // Force rebinding
        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(EmbeddingService::class);
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

    it('registers OllamaService', function () {
        $service = app(OllamaService::class);

        expect($service)->toBeInstanceOf(OllamaService::class);
    });

    it('registers IssueAnalyzerService', function () {
        $service = app(IssueAnalyzerService::class);

        expect($service)->toBeInstanceOf(IssueAnalyzerService::class);
    });

    it('registers QualityGateService', function () {
        $service = app(QualityGateService::class);

        expect($service)->toBeInstanceOf(QualityGateService::class);
    });

    it('registers PullRequestService', function () {
        $service = app(PullRequestService::class);

        expect($service)->toBeInstanceOf(PullRequestService::class);
    });

    it('registers HealthCheckService', function () {
        $service = app(HealthCheckInterface::class);

        expect($service)->toBeInstanceOf(HealthCheckService::class);
    });

    it('uses custom embedding server configuration', function () {
        config([
            'search.embedding_provider' => 'qdrant',
            'search.qdrant.embedding_server' => 'http://custom:8001',
        ]);

        // Force rebinding
        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(EmbeddingService::class);
    });
});
