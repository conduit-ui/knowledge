<?php

namespace Tests;

use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset search configuration to test defaults
        // This ensures tests use stubs for embedding services
        // Qdrant handles all vector search (migrated from SQLite)
        config([
            'search.semantic_enabled' => false,
            'search.embedding_provider' => 'stub',
            'search.chromadb.enabled' => false,
            'search.fts_provider' => 'stub', // Use stub since we migrated to Qdrant
        ]);
    }
}
