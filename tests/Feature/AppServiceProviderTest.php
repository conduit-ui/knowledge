<?php

declare(strict_types=1);

use App\Contracts\ChromaDBClientInterface;
use App\Contracts\EmbeddingServiceInterface;
use App\Contracts\FullTextSearchInterface;
use App\Services\ChromaDBClient;
use App\Services\ChromaDBEmbeddingService;
use App\Services\ChromaDBIndexService;
use App\Services\DatabaseInitializer;
use App\Services\KnowledgePathService;
use App\Services\SemanticSearchService;
use App\Services\SQLiteFtsService;
use App\Services\StubEmbeddingService;
use App\Services\StubFtsService;

describe('AppServiceProvider', function () {
    it('registers KnowledgePathService', function () {
        $service = app(KnowledgePathService::class);

        expect($service)->toBeInstanceOf(KnowledgePathService::class);
    });

    it('registers DatabaseInitializer', function () {
        $service = app(DatabaseInitializer::class);

        expect($service)->toBeInstanceOf(DatabaseInitializer::class);
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

    it('registers ChromaDBIndexService', function () {
        $service = app(ChromaDBIndexService::class);

        expect($service)->toBeInstanceOf(ChromaDBIndexService::class);
    });

    it('registers SemanticSearchService without ChromaDB when disabled', function () {
        config(['search.chromadb.enabled' => false]);

        // Force rebinding
        app()->forgetInstance(SemanticSearchService::class);

        $service = app(SemanticSearchService::class);

        expect($service)->toBeInstanceOf(SemanticSearchService::class)
            ->and($service->hasChromaDBSupport())->toBeFalse();
    });

    it('registers SemanticSearchService with ChromaDB when enabled', function () {
        config(['search.chromadb.enabled' => true, 'search.semantic_enabled' => true]);

        // Force rebinding
        app()->forgetInstance(SemanticSearchService::class);

        $service = app(SemanticSearchService::class);

        expect($service)->toBeInstanceOf(SemanticSearchService::class);
    });

    it('registers SQLiteFtsService by default', function () {
        config(['search.fts_provider' => 'sqlite']);

        // Force rebinding
        app()->forgetInstance(FullTextSearchInterface::class);

        $service = app(FullTextSearchInterface::class);

        expect($service)->toBeInstanceOf(SQLiteFtsService::class);
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
});
