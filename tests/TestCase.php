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
        // but real SQLite FTS for observation search tests
        config([
            'search.semantic_enabled' => false,
            'search.embedding_provider' => null,
            'search.chromadb.enabled' => false,
            'search.fts_provider' => 'sqlite', // Use SQLite FTS for tests
        ]);
    }
}
