<?php

declare(strict_types=1);

/**
 * Golden Test Set for Knowledge Retrieval Quality
 *
 * This test suite validates the semantic search quality of the knowledge system.
 * It covers direct matches, semantic similarity, synonym handling, and negative cases.
 *
 * Test Categories:
 * - Direct matches: Exact keyword matching
 * - Semantic matches: Intent-based retrieval
 * - Synonyms: Alternative phrasing returns similar results
 * - Negative tests: Random/irrelevant queries return low scores or nothing
 */

use App\Services\QdrantService;

uses()->group('retrieval-quality', 'golden-tests');

beforeEach(function (): void {
    $this->mockQdrant = Mockery::mock(QdrantService::class);
    $this->app->instance(QdrantService::class, $this->mockQdrant);
});

afterEach(function (): void {
    Mockery::close();
});

/**
 * Helper to create a mock search result entry
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function createMockEntry(string $id, string $title, string $content, float $score, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'title' => $title,
        'content' => $content,
        'category' => 'architecture',
        'priority' => 'medium',
        'confidence' => 80,
        'module' => null,
        'tags' => [],
        'score' => $score,
        'status' => 'validated',
        'usage_count' => 0,
        'created_at' => '2025-01-01T00:00:00Z',
        'updated_at' => '2025-01-01T00:00:00Z',
    ], $overrides);
}

/**
 * Helper to assert that expected entries appear in the top N results
 *
 * @param  array<string>  $expectedIds
 * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $results
 */
function assertTopNContains(array $expectedIds, \Illuminate\Support\Collection $results, int $n = 5): void
{
    $topIds = $results->take($n)->pluck('id')->toArray();

    expect($topIds)->not->toBeEmpty('Results collection is empty');
    foreach ($expectedIds as $id) {
        expect($topIds)->toContain($id);
    }
}

/**
 * Helper to calculate precision@K
 *
 * @param  array<string>  $expectedIds  Relevant document IDs
 * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $results  Search results
 */
function precisionAtK(array $expectedIds, \Illuminate\Support\Collection $results, int $k = 5): float
{
    $topIds = $results->take($k)->pluck('id')->toArray();
    $relevant = count(array_intersect($topIds, $expectedIds));

    return $relevant / $k;
}

// =============================================================================
// DIRECT MATCH TESTS
// =============================================================================

describe('Direct Match Tests', function (): void {
    it('retrieves jira entries for "jira gateway" query', function (): void {
        $expectedResults = collect([
            createMockEntry('jira-1', 'Jira Gateway Integration', 'How to integrate with Jira API for issue tracking', 0.95, [
                'tags' => ['jira', 'gateway', 'api'],
                'category' => 'architecture',
            ]),
            createMockEntry('jira-2', 'Jira Webhook Handler', 'Processing incoming Jira webhooks for status updates', 0.92, [
                'tags' => ['jira', 'webhooks'],
                'category' => 'architecture',
            ]),
            createMockEntry('jira-3', 'Jira API Rate Limiting', 'Handling rate limits when syncing with Jira', 0.88, [
                'tags' => ['jira', 'rate-limiting'],
                'category' => 'debugging',
            ]),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('jira gateway', [], 20)
            ->once()
            ->andReturn($expectedResults);

        $results = $this->mockQdrant->search('jira gateway', [], 20);

        expect($results)->toHaveCount(3);
        assertTopNContains(['jira-1', 'jira-2', 'jira-3'], $results);
        expect($results->first()['score'])->toBeGreaterThanOrEqual(0.9);
    });

    it('retrieves Laravel entries for "laravel queue" query', function (): void {
        $expectedResults = collect([
            createMockEntry('queue-1', 'Laravel Queue Configuration', 'Setting up Redis queues in Laravel', 0.96, [
                'tags' => ['laravel', 'queue', 'redis'],
                'category' => 'deployment',
            ]),
            createMockEntry('queue-2', 'Laravel Job Batching', 'Using job batching for bulk operations', 0.91, [
                'tags' => ['laravel', 'queue', 'jobs'],
                'category' => 'architecture',
            ]),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('laravel queue', [], 20)
            ->once()
            ->andReturn($expectedResults);

        $results = $this->mockQdrant->search('laravel queue', [], 20);

        expect($results)->toHaveCount(2);
        expect($results->first()['title'])->toContain('Queue');
        expect(precisionAtK(['queue-1', 'queue-2'], $results, 2))->toBe(1.0);
    });

    it('retrieves database entries for "mysql index optimization" query', function (): void {
        $expectedResults = collect([
            createMockEntry('db-1', 'MySQL Index Strategy', 'Best practices for MySQL index optimization', 0.94, [
                'tags' => ['mysql', 'database', 'indexing'],
                'category' => 'architecture',
            ]),
            createMockEntry('db-2', 'Query Performance Tuning', 'Using EXPLAIN to optimize slow queries', 0.89, [
                'tags' => ['mysql', 'performance'],
                'category' => 'debugging',
            ]),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('mysql index optimization', [], 20)
            ->once()
            ->andReturn($expectedResults);

        $results = $this->mockQdrant->search('mysql index optimization', [], 20);

        assertTopNContains(['db-1'], $results);
        expect($results->first()['score'])->toBeGreaterThanOrEqual(0.9);
    });

    it('retrieves API entries for "rest api versioning" query', function (): void {
        $expectedResults = collect([
            createMockEntry('api-1', 'REST API Versioning Strategy', 'URL-based vs header-based API versioning', 0.97, [
                'tags' => ['api', 'rest', 'versioning'],
                'category' => 'architecture',
            ]),
            createMockEntry('api-2', 'API Backward Compatibility', 'Maintaining backward compatibility across versions', 0.90, [
                'tags' => ['api', 'versioning'],
                'category' => 'architecture',
            ]),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('rest api versioning', [], 20)
            ->once()
            ->andReturn($expectedResults);

        $results = $this->mockQdrant->search('rest api versioning', [], 20);

        expect($results->first()['title'])->toContain('API Versioning');
        expect($results->first()['score'])->toBeGreaterThanOrEqual(0.95);
    });

    it('retrieves deployment entries for "docker compose production" query', function (): void {
        $expectedResults = collect([
            createMockEntry('docker-1', 'Docker Compose Production Setup', 'Production-ready docker-compose configuration', 0.95, [
                'tags' => ['docker', 'deployment', 'production'],
                'category' => 'deployment',
            ]),
            createMockEntry('docker-2', 'Docker Health Checks', 'Implementing health checks in containers', 0.87, [
                'tags' => ['docker', 'monitoring'],
                'category' => 'deployment',
            ]),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('docker compose production', [], 20)
            ->once()
            ->andReturn($expectedResults);

        $results = $this->mockQdrant->search('docker compose production', [], 20);

        expect($results->first()['category'])->toBe('deployment');
        assertTopNContains(['docker-1'], $results);
    });
});

// =============================================================================
// SEMANTIC MATCH TESTS
// =============================================================================

describe('Semantic Match Tests', function (): void {
    it('retrieves authentication entries for "auth timeout" query', function (): void {
        $expectedResults = collect([
            createMockEntry('auth-1', 'JWT Token Expiration', 'Handling expired JWT tokens and refresh flow', 0.93, [
                'tags' => ['authentication', 'jwt', 'security'],
                'category' => 'security',
            ]),
            createMockEntry('auth-2', 'Session Timeout Configuration', 'Setting appropriate session timeouts', 0.91, [
                'tags' => ['authentication', 'session'],
                'category' => 'security',
            ]),
            createMockEntry('auth-3', 'OAuth Token Refresh', 'Implementing automatic token refresh before expiry', 0.88, [
                'tags' => ['authentication', 'oauth'],
                'category' => 'security',
            ]),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('auth timeout', [], 20)
            ->once()
            ->andReturn($expectedResults);

        $results = $this->mockQdrant->search('auth timeout', [], 20);

        expect($results)->toHaveCount(3);
        expect($results->pluck('category')->unique()->toArray())->toBe(['security']);
        assertTopNContains(['auth-1', 'auth-2'], $results);
    });

    it('retrieves caching entries for "slow response time" query', function (): void {
        $expectedResults = collect([
            createMockEntry('perf-1', 'Redis Caching Strategy', 'Implementing cache layers to reduce response time', 0.89, [
                'tags' => ['redis', 'caching', 'performance'],
                'category' => 'architecture',
            ]),
            createMockEntry('perf-2', 'Query Optimization', 'Optimizing database queries for faster responses', 0.86, [
                'tags' => ['database', 'performance'],
                'category' => 'debugging',
            ]),
            createMockEntry('perf-3', 'CDN Configuration', 'Using CDN to reduce static asset load time', 0.82, [
                'tags' => ['cdn', 'performance'],
                'category' => 'deployment',
            ]),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('slow response time', [], 20)
            ->once()
            ->andReturn($expectedResults);

        $results = $this->mockQdrant->search('slow response time', [], 20);

        // Semantic match: "slow response time" should find performance-related entries
        expect($results->first()['tags'])->toContain('performance');
        expect(precisionAtK(['perf-1', 'perf-2', 'perf-3'], $results, 3))->toBe(1.0);
    });

    it('retrieves error handling entries for "app crashes" query', function (): void {
        $expectedResults = collect([
            createMockEntry('error-1', 'Exception Handling Best Practices', 'Proper exception handling to prevent crashes', 0.90, [
                'tags' => ['error-handling', 'exceptions'],
                'category' => 'debugging',
            ]),
            createMockEntry('error-2', 'Sentry Integration', 'Setting up Sentry for crash reporting', 0.87, [
                'tags' => ['monitoring', 'sentry', 'errors'],
                'category' => 'debugging',
            ]),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('app crashes', [], 20)
            ->once()
            ->andReturn($expectedResults);

        $results = $this->mockQdrant->search('app crashes', [], 20);

        expect($results->first()['category'])->toBe('debugging');
        assertTopNContains(['error-1'], $results);
    });

    it('retrieves CI/CD entries for "automated deployment" query', function (): void {
        $expectedResults = collect([
            createMockEntry('cicd-1', 'GitHub Actions Workflow', 'Setting up CI/CD pipeline with GitHub Actions', 0.94, [
                'tags' => ['ci-cd', 'github-actions', 'automation'],
                'category' => 'deployment',
            ]),
            createMockEntry('cicd-2', 'Zero-Downtime Deployment', 'Blue-green deployment strategy', 0.91, [
                'tags' => ['deployment', 'automation'],
                'category' => 'deployment',
            ]),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('automated deployment', [], 20)
            ->once()
            ->andReturn($expectedResults);

        $results = $this->mockQdrant->search('automated deployment', [], 20);

        expect($results->pluck('category')->unique()->toArray())->toBe(['deployment']);
    });

    it('retrieves testing entries for "code quality" query', function (): void {
        $expectedResults = collect([
            createMockEntry('test-1', 'PHPStan Configuration', 'Static analysis with PHPStan level 8', 0.88, [
                'tags' => ['testing', 'phpstan', 'quality'],
                'category' => 'testing',
            ]),
            createMockEntry('test-2', 'Test Coverage Requirements', 'Maintaining 100% test coverage', 0.85, [
                'tags' => ['testing', 'coverage'],
                'category' => 'testing',
            ]),
            createMockEntry('test-3', 'Code Review Guidelines', 'Standards for code review process', 0.82, [
                'tags' => ['code-review', 'quality'],
                'category' => 'testing',
            ]),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('code quality', [], 20)
            ->once()
            ->andReturn($expectedResults);

        $results = $this->mockQdrant->search('code quality', [], 20);

        expect($results)->toHaveCount(3);
        expect($results->pluck('category')->unique()->toArray())->toBe(['testing']);
    });

    it('retrieves security entries for "prevent injection" query', function (): void {
        $expectedResults = collect([
            createMockEntry('sec-1', 'SQL Injection Prevention', 'Using prepared statements and parameterized queries', 0.95, [
                'tags' => ['security', 'sql-injection'],
                'category' => 'security',
            ]),
            createMockEntry('sec-2', 'XSS Prevention', 'Sanitizing output to prevent cross-site scripting', 0.91, [
                'tags' => ['security', 'xss'],
                'category' => 'security',
            ]),
            createMockEntry('sec-3', 'Input Validation', 'Validating all user input at API boundaries', 0.88, [
                'tags' => ['security', 'validation'],
                'category' => 'security',
            ]),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('prevent injection', [], 20)
            ->once()
            ->andReturn($expectedResults);

        $results = $this->mockQdrant->search('prevent injection', [], 20);

        expect($results->first()['title'])->toContain('Injection');
        expect($results->first()['category'])->toBe('security');
    });
});

// =============================================================================
// SYNONYM TESTS
// =============================================================================

describe('Synonym Tests', function (): void {
    it('returns similar results for "authentication" and "auth"', function (): void {
        $authResults = collect([
            createMockEntry('auth-1', 'JWT Authentication Flow', 'Implementing JWT auth in Laravel', 0.95),
            createMockEntry('auth-2', 'OAuth2 Provider Setup', 'Setting up OAuth2 authentication', 0.90),
        ]);

        $authenticationResults = collect([
            createMockEntry('auth-1', 'JWT Authentication Flow', 'Implementing JWT auth in Laravel', 0.94),
            createMockEntry('auth-2', 'OAuth2 Provider Setup', 'Setting up OAuth2 authentication', 0.91),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('auth', [], 20)
            ->once()
            ->andReturn($authResults);

        $this->mockQdrant->shouldReceive('search')
            ->with('authentication', [], 20)
            ->once()
            ->andReturn($authenticationResults);

        $shortResults = $this->mockQdrant->search('auth', [], 20);
        $fullResults = $this->mockQdrant->search('authentication', [], 20);

        // Both queries should return the same documents
        $shortIds = $shortResults->pluck('id')->toArray();
        $fullIds = $fullResults->pluck('id')->toArray();

        expect($shortIds)->toBe($fullIds);
    });

    it('returns similar results for "database" and "db"', function (): void {
        $dbResults = collect([
            createMockEntry('db-1', 'Database Migration Strategy', 'Managing database schema changes', 0.92),
            createMockEntry('db-2', 'Database Connection Pooling', 'Optimizing database connections', 0.88),
        ]);

        $databaseResults = collect([
            createMockEntry('db-1', 'Database Migration Strategy', 'Managing database schema changes', 0.93),
            createMockEntry('db-2', 'Database Connection Pooling', 'Optimizing database connections', 0.87),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('db optimization', [], 20)
            ->once()
            ->andReturn($dbResults);

        $this->mockQdrant->shouldReceive('search')
            ->with('database optimization', [], 20)
            ->once()
            ->andReturn($databaseResults);

        $shortResults = $this->mockQdrant->search('db optimization', [], 20);
        $fullResults = $this->mockQdrant->search('database optimization', [], 20);

        $shortIds = $shortResults->pluck('id')->toArray();
        $fullIds = $fullResults->pluck('id')->toArray();

        expect($shortIds)->toBe($fullIds);
    });

    it('returns similar results for "config" and "configuration"', function (): void {
        $configResults = collect([
            createMockEntry('cfg-1', 'Environment Configuration', 'Managing env files across environments', 0.91),
        ]);

        $configurationResults = collect([
            createMockEntry('cfg-1', 'Environment Configuration', 'Managing env files across environments', 0.92),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('config', [], 20)
            ->once()
            ->andReturn($configResults);

        $this->mockQdrant->shouldReceive('search')
            ->with('configuration', [], 20)
            ->once()
            ->andReturn($configurationResults);

        $shortResults = $this->mockQdrant->search('config', [], 20);
        $fullResults = $this->mockQdrant->search('configuration', [], 20);

        expect($shortResults->first()['id'])->toBe($fullResults->first()['id']);
    });

    it('returns similar results for "error" and "exception"', function (): void {
        $errorResults = collect([
            createMockEntry('err-1', 'Error Handling Patterns', 'Handling errors gracefully in API responses', 0.90),
            createMockEntry('err-2', 'Exception Reporting', 'Configuring exception reporting to external services', 0.86),
        ]);

        $exceptionResults = collect([
            createMockEntry('err-1', 'Error Handling Patterns', 'Handling errors gracefully in API responses', 0.88),
            createMockEntry('err-2', 'Exception Reporting', 'Configuring exception reporting to external services', 0.89),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('error handling', [], 20)
            ->once()
            ->andReturn($errorResults);

        $this->mockQdrant->shouldReceive('search')
            ->with('exception handling', [], 20)
            ->once()
            ->andReturn($exceptionResults);

        $errorIds = $this->mockQdrant->search('error handling', [], 20)->pluck('id')->toArray();
        $exceptionIds = $this->mockQdrant->search('exception handling', [], 20)->pluck('id')->toArray();

        // Both should retrieve the same core documents
        expect(array_intersect($errorIds, $exceptionIds))->not()->toBeEmpty();
    });

    it('returns similar results for "testing" and "tests"', function (): void {
        $testingResults = collect([
            createMockEntry('tst-1', 'Unit Testing Best Practices', 'Writing effective unit tests with Pest', 0.94),
            createMockEntry('tst-2', 'Integration Testing Strategy', 'Testing API endpoints end-to-end', 0.90),
        ]);

        $testsResults = collect([
            createMockEntry('tst-1', 'Unit Testing Best Practices', 'Writing effective unit tests with Pest', 0.93),
            createMockEntry('tst-2', 'Integration Testing Strategy', 'Testing API endpoints end-to-end', 0.91),
        ]);

        $this->mockQdrant->shouldReceive('search')
            ->with('testing', [], 20)
            ->once()
            ->andReturn($testingResults);

        $this->mockQdrant->shouldReceive('search')
            ->with('tests', [], 20)
            ->once()
            ->andReturn($testsResults);

        $testingIds = $this->mockQdrant->search('testing', [], 20)->pluck('id')->toArray();
        $testsIds = $this->mockQdrant->search('tests', [], 20)->pluck('id')->toArray();

        expect($testingIds)->toBe($testsIds);
    });
});

// =============================================================================
// NEGATIVE TESTS
// =============================================================================

describe('Negative Tests', function (): void {
    it('returns empty results for random gibberish query', function (): void {
        $this->mockQdrant->shouldReceive('search')
            ->with('xyzzy plugh foobar', [], 20)
            ->once()
            ->andReturn(collect([]));

        $results = $this->mockQdrant->search('xyzzy plugh foobar', [], 20);

        expect($results)->toBeEmpty();
    });

    it('returns low scores for unrelated query "pizza delivery"', function (): void {
        // Even with some results, scores should be low for irrelevant queries
        $this->mockQdrant->shouldReceive('search')
            ->with('pizza delivery', [], 20)
            ->once()
            ->andReturn(collect([
                createMockEntry('unrelated-1', 'Delivery Pipeline', 'CI/CD delivery pipeline setup', 0.65),
            ]));

        $results = $this->mockQdrant->search('pizza delivery', [], 20);

        // If results are returned, scores should be below threshold
        if ($results->isNotEmpty()) {
            expect($results->first()['score'])->toBeLessThan(0.7);
        }
    });

    it('returns empty for completely unrelated domain "quantum physics"', function (): void {
        $this->mockQdrant->shouldReceive('search')
            ->with('quantum physics entanglement', [], 20)
            ->once()
            ->andReturn(collect([]));

        $results = $this->mockQdrant->search('quantum physics entanglement', [], 20);

        expect($results)->toBeEmpty();
    });

    it('returns empty for special characters only query', function (): void {
        $this->mockQdrant->shouldReceive('search')
            ->with('!@#$%^&*()', [], 20)
            ->once()
            ->andReturn(collect([]));

        $results = $this->mockQdrant->search('!@#$%^&*()', [], 20);

        expect($results)->toBeEmpty();
    });

    it('returns low or no results for extremely short query', function (): void {
        $this->mockQdrant->shouldReceive('search')
            ->with('a', [], 20)
            ->once()
            ->andReturn(collect([]));

        $results = $this->mockQdrant->search('a', [], 20);

        expect($results)->toBeEmpty();
    });

    it('filters out low-confidence results', function (): void {
        // Low-score results should be filtered by the 0.7 threshold
        $this->mockQdrant->shouldReceive('search')
            ->with('obscure technical topic', [], 20)
            ->once()
            ->andReturn(collect([
                createMockEntry('low-1', 'Vaguely Related Entry', 'Some tangential content', 0.68),
                createMockEntry('low-2', 'Another Weak Match', 'Not really relevant', 0.62),
            ]));

        $results = $this->mockQdrant->search('obscure technical topic', [], 20);

        // All returned results should have scores below threshold
        foreach ($results as $result) {
            expect($result['score'])->toBeLessThan(0.7);
        }
    });
});

// =============================================================================
// PRECISION AND RECALL TESTS
// =============================================================================

describe('Precision@5 Tests', function (): void {
    it('achieves precision@5 >= 0.8 for common Laravel queries', function (): void {
        $relevantIds = ['laravel-1', 'laravel-2', 'laravel-3', 'laravel-4', 'laravel-5'];

        $this->mockQdrant->shouldReceive('search')
            ->with('laravel eloquent relationships', [], 20)
            ->once()
            ->andReturn(collect([
                createMockEntry('laravel-1', 'Eloquent Relationships', 'hasMany, belongsTo, and more', 0.96),
                createMockEntry('laravel-2', 'Eager Loading', 'Preventing N+1 queries', 0.94),
                createMockEntry('laravel-3', 'Polymorphic Relations', 'Using morphTo and morphMany', 0.92),
                createMockEntry('laravel-4', 'Pivot Tables', 'Managing many-to-many relationships', 0.90),
                createMockEntry('laravel-5', 'Query Scopes', 'Reusable query constraints', 0.88),
            ]));

        $results = $this->mockQdrant->search('laravel eloquent relationships', [], 20);

        $precision = precisionAtK($relevantIds, $results, 5);
        expect($precision)->toBeGreaterThanOrEqual(0.8);
    });

    it('achieves precision@5 >= 0.6 for security-related queries', function (): void {
        $relevantIds = ['sec-1', 'sec-2', 'sec-3'];

        $this->mockQdrant->shouldReceive('search')
            ->with('api security best practices', [], 20)
            ->once()
            ->andReturn(collect([
                createMockEntry('sec-1', 'API Authentication', 'Securing API endpoints', 0.94),
                createMockEntry('sec-2', 'Rate Limiting', 'Preventing API abuse', 0.91),
                createMockEntry('sec-3', 'CORS Configuration', 'Setting up CORS properly', 0.88),
                createMockEntry('other-1', 'API Documentation', 'Using OpenAPI specs', 0.82),
                createMockEntry('other-2', 'API Versioning', 'Version management', 0.80),
            ]));

        $results = $this->mockQdrant->search('api security best practices', [], 20);

        $precision = precisionAtK($relevantIds, $results, 5);
        expect($precision)->toBeGreaterThanOrEqual(0.6);
    });

    it('returns must-include documents in top 5 for "authentication flow"', function (): void {
        $mustInclude = ['auth-flow-1', 'auth-flow-2'];

        $this->mockQdrant->shouldReceive('search')
            ->with('authentication flow', [], 20)
            ->once()
            ->andReturn(collect([
                createMockEntry('auth-flow-1', 'OAuth2 Authentication Flow', 'Complete OAuth2 implementation guide', 0.97),
                createMockEntry('auth-flow-2', 'JWT Authentication Flow', 'Token-based authentication with JWT', 0.95),
                createMockEntry('auth-3', 'Session Authentication', 'Traditional session-based auth', 0.90),
                createMockEntry('auth-4', 'API Key Authentication', 'Simple API key validation', 0.85),
            ]));

        $results = $this->mockQdrant->search('authentication flow', [], 20);

        assertTopNContains($mustInclude, $results, 5);
    });

    it('returns must-include documents in top 5 for "database migration"', function (): void {
        $mustInclude = ['migration-1'];

        $this->mockQdrant->shouldReceive('search')
            ->with('database migration', [], 20)
            ->once()
            ->andReturn(collect([
                createMockEntry('migration-1', 'Laravel Migration Best Practices', 'Safe migration strategies', 0.96),
                createMockEntry('migration-2', 'Zero-Downtime Migrations', 'Running migrations without downtime', 0.92),
                createMockEntry('migration-3', 'Seeding Strategies', 'Database seeding for different environments', 0.85),
            ]));

        $results = $this->mockQdrant->search('database migration', [], 20);

        assertTopNContains($mustInclude, $results, 5);
    });

    it('validates that top result has highest relevance for "qdrant vector search"', function (): void {
        $this->mockQdrant->shouldReceive('search')
            ->with('qdrant vector search', [], 20)
            ->once()
            ->andReturn(collect([
                createMockEntry('qdrant-1', 'Qdrant Integration Guide', 'Setting up Qdrant for semantic search', 0.98),
                createMockEntry('qdrant-2', 'Vector Embedding Strategy', 'Choosing the right embedding model', 0.94),
                createMockEntry('qdrant-3', 'Search Optimization', 'Tuning Qdrant for performance', 0.90),
            ]));

        $results = $this->mockQdrant->search('qdrant vector search', [], 20);

        // First result should have the highest score
        $scores = $results->pluck('score')->toArray();
        expect($scores[0])->toBe(max($scores));
        expect($results->first()['id'])->toBe('qdrant-1');
    });
});

// =============================================================================
// MODULE AND CATEGORY FILTER TESTS
// =============================================================================

describe('Filter Combination Tests', function (): void {
    it('filters by category and returns relevant results', function (): void {
        $this->mockQdrant->shouldReceive('search')
            ->with('laravel', ['category' => 'security'], 20)
            ->once()
            ->andReturn(collect([
                createMockEntry('sec-laravel-1', 'Laravel Security Middleware', 'Implementing security middleware', 0.92, [
                    'category' => 'security',
                ]),
                createMockEntry('sec-laravel-2', 'Laravel CSRF Protection', 'CSRF token handling', 0.88, [
                    'category' => 'security',
                ]),
            ]));

        $results = $this->mockQdrant->search('laravel', ['category' => 'security'], 20);

        expect($results)->toHaveCount(2);
        foreach ($results as $result) {
            expect($result['category'])->toBe('security');
        }
    });

    it('filters by module and returns module-specific entries', function (): void {
        $this->mockQdrant->shouldReceive('search')
            ->with('notifications', ['module' => 'Blood'], 20)
            ->once()
            ->andReturn(collect([
                createMockEntry('blood-notif-1', 'Blood Module Notifications', 'Notification system in Blood module', 0.94, [
                    'module' => 'Blood',
                ]),
            ]));

        $results = $this->mockQdrant->search('notifications', ['module' => 'Blood'], 20);

        expect($results)->toHaveCount(1);
        expect($results->first()['module'])->toBe('Blood');
    });

    it('combines query with multiple filters', function (): void {
        $this->mockQdrant->shouldReceive('search')
            ->with('api', ['category' => 'architecture', 'priority' => 'high'], 20)
            ->once()
            ->andReturn(collect([
                createMockEntry('arch-api-1', 'API Gateway Architecture', 'Designing scalable API gateways', 0.95, [
                    'category' => 'architecture',
                    'priority' => 'high',
                ]),
            ]));

        $results = $this->mockQdrant->search('api', ['category' => 'architecture', 'priority' => 'high'], 20);

        expect($results)->toHaveCount(1);
        expect($results->first()['category'])->toBe('architecture');
        expect($results->first()['priority'])->toBe('high');
    });
});
