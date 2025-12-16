<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Semantic Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure semantic search capabilities for the knowledge base.
    | Currently supports stub implementation with future ChromaDB integration.
    |
    */

    'semantic_enabled' => env('SEMANTIC_SEARCH_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Embedding Provider
    |--------------------------------------------------------------------------
    |
    | The embedding provider to use for generating text embeddings.
    | Supported: "none", "openai" (future), "chromadb" (future)
    |
    */

    'embedding_provider' => env('EMBEDDING_PROVIDER', 'none'),

    /*
    |--------------------------------------------------------------------------
    | Embedding Dimension
    |--------------------------------------------------------------------------
    |
    | The dimension of the embedding vectors.
    | OpenAI text-embedding-ada-002: 1536
    | OpenAI text-embedding-3-small: 1536
    | OpenAI text-embedding-3-large: 3072
    |
    */

    'embedding_dimension' => env('EMBEDDING_DIMENSION', 1536),

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure search behavior and thresholds.
    |
    */

    'minimum_similarity' => env('SEARCH_MIN_SIMILARITY', 0.7),
    'max_results' => env('SEARCH_MAX_RESULTS', 20),
];
