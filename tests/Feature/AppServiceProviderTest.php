<?php

declare(strict_types=1);

use App\Contracts\EmbeddingServiceInterface;
use App\Services\EmbeddingService;
use App\Services\KnowledgePathService;
use App\Services\QdrantService;
use App\Services\RuntimeEnvironment;
use App\Services\StubEmbeddingService;

describe('AppServiceProvider', function (): void {
    it('registers RuntimeEnvironment', function (): void {
        $service = app(RuntimeEnvironment::class);

        expect($service)->toBeInstanceOf(RuntimeEnvironment::class);
    });

    it('registers KnowledgePathService', function (): void {
        $service = app(KnowledgePathService::class);

        expect($service)->toBeInstanceOf(KnowledgePathService::class);
    });

    it('registers StubEmbeddingService by default', function (): void {
        config(['search.embedding_provider' => 'none']);

        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(StubEmbeddingService::class);
    });

    it('registers EmbeddingService when provider is chromadb', function (): void {
        config(['search.embedding_provider' => 'chromadb']);

        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(EmbeddingService::class);
    });

    it('registers EmbeddingService when provider is qdrant', function (): void {
        config(['search.embedding_provider' => 'qdrant']);

        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(EmbeddingService::class);
    });

    it('registers QdrantService with mocked embedding service', function (): void {
        $mockEmbedding = Mockery::mock(EmbeddingServiceInterface::class);
        app()->instance(EmbeddingServiceInterface::class, $mockEmbedding);

        config([
            'search.embedding_dimension' => 384,
            'search.minimum_similarity' => 0.7,
            'search.qdrant.cache_ttl' => 604800,
            'search.qdrant.secure' => false,
        ]);

        app()->forgetInstance(QdrantService::class);

        $service = app(QdrantService::class);

        expect($service)->toBeInstanceOf(QdrantService::class);
    });

    it('registers QdrantService with secure connection configuration', function (): void {
        $mockEmbedding = Mockery::mock(EmbeddingServiceInterface::class);
        app()->instance(EmbeddingServiceInterface::class, $mockEmbedding);

        config([
            'search.embedding_dimension' => 1536,
            'search.minimum_similarity' => 0.8,
            'search.qdrant.cache_ttl' => 86400,
            'search.qdrant.secure' => true,
        ]);

        app()->forgetInstance(QdrantService::class);

        $service = app(QdrantService::class);

        expect($service)->toBeInstanceOf(QdrantService::class);
    });

    it('uses custom embedding server configuration for qdrant provider', function (): void {
        config([
            'search.embedding_provider' => 'qdrant',
            'search.qdrant.embedding_server' => 'http://custom-server:8001',
            'search.qdrant.model' => 'custom-model',
        ]);

        app()->forgetInstance(EmbeddingServiceInterface::class);

        $service = app(EmbeddingServiceInterface::class);

        expect($service)->toBeInstanceOf(EmbeddingService::class);
    });
});

afterEach(function (): void {
    Mockery::close();
});
