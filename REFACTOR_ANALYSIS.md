# Entry → Qdrant Refactor Analysis

**Generated:** 2026-01-09
**Scope:** Migrating 23+ command files from Entry (Eloquent/SQLite) to QdrantService (Vector DB)

---

## Executive Summary

This refactor involves removing the Entry Eloquent model and replacing ALL database operations with QdrantService for semantic search capabilities. The migration affects 23+ command files and introduces several architectural challenges.

### Key Findings
- **High Impact:** Relationships (tags, collections) stored in pivot tables are not directly supported by vector DB
- **Breaking Changes:** Integer auto-increment IDs → UUID strings
- **Missing Methods:** QdrantService needs 4 critical methods (getById, incrementUsage, updateFields, listAll)
- **Test Coverage:** Must maintain 100% coverage (enforced by Synapse Sentinel)

---

## 1. Entry Model Analysis

### Current Structure
```php
class Entry extends Model {
    // Fields
    - id (int, auto-increment)
    - title (string)
    - content (string)
    - category (string|null)
    - tags (array)
    - module (string|null)
    - priority (string: critical/high/medium/low)
    - confidence (int: 0-100)
    - status (string: draft/validated/deprecated)
    - source, ticket, files (metadata)
    - repo, branch, commit, author (git context)
    - usage_count (int)
    - last_used (datetime|null)
    - validation_date (datetime|null)

    // Methods
    - incrementUsage(): void

    // Relationships
    - normalizedTags(): BelongsToMany<Tag>
    - collections(): BelongsToMany<Collection>
    - outgoingRelationships(): HasMany<Relationship>
    - incomingRelationships(): HasMany<Relationship>
}
```

### Migration Challenges
1. **Integer IDs → UUID strings** for Qdrant compatibility
2. **incrementUsage()** requires fetch + update in vector DB (not atomic)
3. **Relationships** stored in pivot tables (tags, collections, relationships)
4. **Eloquent query builder** features (scopes, aggregates) not available
5. **Usage tracking** (usage_count, last_used) needs custom logic

---

## 2. Command Usage Patterns

### 23+ Commands Using Entry Model

| Command | Pattern | Challenge |
|---------|---------|-----------|
| **KnowledgeListCommand** | `Entry::query()->where()->orderBy()->limit()->get()` | No semantic search, pure filtering |
| **KnowledgeShowCommand** | `Entry::find($id) + $entry->incrementUsage()` | Need getById() + usage tracking |
| **KnowledgeAddCommand** | `Entry::create($data)` | Must generate UUID, already refactored ✓ |
| **KnowledgeStatsCommand** | `Entry::count(), sum(), avg(), groupBy()` | Aggregation queries |
| **KnowledgeLinkCommand** | `Entry::find($id)`, relationship creation | Relationships not in vector DB |
| **Collection/AddCommand** | `Entry::find($id)`, pivot table operations | Pivot tables not supported |

### Additional Commands (24 total)
- KnowledgeArchiveCommand
- KnowledgeConflictsCommand
- KnowledgeDeprecateCommand
- KnowledgeExportCommand, ExportAllCommand, ExportGraphCommand
- KnowledgeGitAuthorCommand, GitContextCommand, GitEntriesCommand
- KnowledgeGraphCommand
- KnowledgeMergeCommand
- KnowledgePruneCommand
- KnowledgePublishCommand
- KnowledgeRelatedCommand
- KnowledgeServeCommand
- KnowledgeStaleCommand
- KnowledgeUnlinkCommand
- KnowledgeValidateCommand
- KnowledgeDuplicatesCommand
- KnowledgeIndexCommand
- KnowledgeSearchStatusCommand
- KnowledgeSearchCommand
- SyncCommand
- MigrateToQdrantCommand
- Collection/RemoveCommand

---

## 3. Refactor Patterns

### Pattern 1: Create Entry
**Eloquent:**
```php
$entry = Entry::create([
    'title' => $title,
    'content' => $content,
    'category' => $category,
    'tags' => $tags,
    'priority' => $priority,
]);
$id = $entry->id; // Auto-increment integer
```

**Qdrant:**
```php
$id = Str::uuid()->toString(); // Generate UUID first
$success = $qdrant->upsert([
    'id' => $id,
    'title' => $title,
    'content' => $content,
    'category' => $category,
    'tags' => $tags,
    'priority' => $priority,
]);
// Returns bool, not model instance
```

**Notes:**
- Must generate UUID before upsert
- Qdrant auto-generates embeddings from title + content
- Returns bool instead of model instance

---

### Pattern 2: Find by ID
**Eloquent:**
```php
$entry = Entry::find($id);
if (!$entry) {
    $this->error('Entry not found');
    return self::FAILURE;
}
```

**Qdrant (PROBLEM - Method Missing):**
```php
// QdrantService has NO find-by-ID method!
// Workaround: use search with empty query
$results = $qdrant->search('', [], 1, 'default');
// But this doesn't filter by ID!

// NEED: QdrantService->getById($id)
$entry = $qdrant->getById($id);
if (!$entry) {
    $this->error('Entry not found');
    return self::FAILURE;
}
```

**Critical Issue:** QdrantService needs `getById()` method.

---

### Pattern 3: Filter Entries
**Eloquent:**
```php
$entries = Entry::query()
    ->where('category', $category)
    ->where('priority', $priority)
    ->where('confidence', '>=', $minConfidence)
    ->orderBy('confidence', 'desc')
    ->orderBy('usage_count', 'desc')
    ->limit($limit)
    ->get();
```

**Qdrant:**
```php
// Empty query string for pure filtering
$entries = $qdrant->search('', [
    'category' => $category,
    'priority' => $priority,
    // PROBLEM: No confidence filter support
], $limit);

// Results are ordered by relevance score, not confidence/usage
// NEED: QdrantService->listAll() for non-semantic filtering
```

**Notes:**
- Empty query string works for pure filtering
- Ordering is by semantic relevance, not metadata fields
- May need `listAll()` method for traditional filtering

---

### Pattern 4: Update Entry
**Eloquent:**
```php
$entry = Entry::find($id);
$entry->update(['status' => 'validated']);
```

**Qdrant:**
```php
// Need to fetch full entry first
$entry = $qdrant->getById($id); // Method doesn't exist yet!
if (!$entry) return;

// Modify data
$entry['status'] = 'validated';
$entry['updated_at'] = now()->toIso8601String();

// Upsert (update)
$qdrant->upsert($entry);

// BETTER: QdrantService->updateFields($id, ['status' => 'validated'])
```

**Critical Issue:** Need `updateFields()` method to avoid fetch + modify + upsert pattern.

---

### Pattern 5: Increment Usage
**Eloquent:**
```php
$entry = Entry::find($id);
$entry->incrementUsage(); // Atomic operation
```

**Qdrant:**
```php
// NOT ATOMIC - race condition possible
$entry = $qdrant->getById($id);
$entry['usage_count']++;
$entry['last_used'] = now()->toIso8601String();
$qdrant->upsert($entry);

// BETTER: QdrantService->incrementUsage($id)
```

**Critical Issue:** Usage tracking requires two operations (fetch + upsert), not atomic like Eloquent's increment().

---

### Pattern 6: Delete Entry
**Eloquent:**
```php
$entry = Entry::find($id);
$entry->delete();
```

**Qdrant:**
```php
$qdrant->delete([$id]); // Already supported ✓
```

**Notes:** Direct mapping, delete() already accepts array of IDs.

---

### Pattern 7: Aggregations (Stats)
**Eloquent:**
```php
// KnowledgeStatsCommand patterns
$total = Entry::count();
$totalUsage = Entry::sum('usage_count');
$avgUsage = Entry::avg('usage_count');

$statuses = Entry::selectRaw('status, count(*) as count')
    ->groupBy('status')
    ->get();

$categories = Entry::selectRaw('category, count(*) as count')
    ->whereNotNull('category')
    ->groupBy('category')
    ->get();

$mostUsed = Entry::orderBy('usage_count', 'desc')->first();
```

**Qdrant (PROBLEM - No Aggregations):**
```php
// Vector DB doesn't support SQL aggregations!
// Must fetch all entries and aggregate in application layer

$entries = $qdrant->listAll([], 10000); // Get all entries
$total = $entries->count();
$totalUsage = $entries->sum('usage_count');
$avgUsage = $entries->avg('usage_count');

// Group by status (in-memory)
$statuses = $entries->groupBy('status')
    ->map(fn($group) => ['status' => $group->first()['status'], 'count' => $group->count()]);

// This is SLOW for large datasets!
// ALTERNATIVE: Cache statistics, update on write
```

**Critical Issue:** Aggregation queries require fetching all entries into memory.

---

### Pattern 8: Relationships
**Eloquent:**
```php
// KnowledgeLinkCommand
$fromEntry = Entry::find($fromId);
$toEntry = Entry::find($toId);

$relationship = Relationship::create([
    'from_entry_id' => $fromId,
    'to_entry_id' => $toId,
    'type' => 'relates_to',
]);

// Collection/AddCommand
$collection->entries()->attach($entryId, ['sort_order' => $order]);
```

**Qdrant (PROBLEM - No Relationships):**
```php
// Option 1: Embed relationships in payload
$entry = $qdrant->getById($entryId);
$entry['related_to'] = array_merge($entry['related_to'] ?? [], [$relatedId]);
$qdrant->upsert($entry);

// Option 2: Create separate "relationships" collection
$qdrant->upsert([
    'id' => Str::uuid()->toString(),
    'from_entry_id' => $fromId,
    'to_entry_id' => $toId,
    'type' => 'relates_to',
], 'relationships');

// Option 3: Keep Entry model ONLY for relationships
// This maintains pivot tables while using Qdrant for search
```

**Critical Decision:** How to handle relationships? Embed in payload or separate collection?

---

## 4. Required QdrantService Enhancements

### Missing Methods (CRITICAL)

#### 1. getById()
```php
/**
 * Get entry by ID.
 *
 * @param string|int $id Entry ID (UUID or integer for backwards compat)
 * @param string $project Project namespace
 * @return array|null Entry data or null if not found
 */
public function getById(string|int $id, string $project = 'default'): ?array
{
    // Implementation: Use Qdrant's scroll or filter API
    // Filter by payload.id == $id
    // Return single result or null
}
```

#### 2. incrementUsage()
```php
/**
 * Increment usage count and update last_used timestamp.
 *
 * @param string|int $id Entry ID
 * @param string $project Project namespace
 * @return bool Success
 */
public function incrementUsage(string|int $id, string $project = 'default'): bool
{
    $entry = $this->getById($id, $project);
    if (!$entry) return false;

    $entry['usage_count'] = ($entry['usage_count'] ?? 0) + 1;
    $entry['last_used'] = now()->toIso8601String();

    return $this->upsert($entry, $project);
}
```

#### 3. updateFields()
```php
/**
 * Update specific fields without fetching full entry.
 *
 * @param string|int $id Entry ID
 * @param array $fields Fields to update
 * @param string $project Project namespace
 * @return bool Success
 */
public function updateFields(string|int $id, array $fields, string $project = 'default'): bool
{
    $entry = $this->getById($id, $project);
    if (!$entry) return false;

    foreach ($fields as $key => $value) {
        $entry[$key] = $value;
    }

    $entry['updated_at'] = now()->toIso8601String();

    return $this->upsert($entry, $project);
}
```

#### 4. listAll()
```php
/**
 * List all entries with filters (no semantic search).
 *
 * @param array $filters Filters (category, module, priority, status, tag)
 * @param int $limit Maximum results
 * @param string $project Project namespace
 * @return Collection<array> Entry collection
 */
public function listAll(array $filters = [], int $limit = 100, string $project = 'default'): Collection
{
    // Use search with empty query for pure filtering
    return $this->search('', $filters, $limit, $project);
}
```

---

## 5. Edge Cases to Test

### Critical Edge Cases
1. **Empty query with filters** - Pure filtering without semantic search
2. **Large limit values (>100)** - May hit Qdrant API limits
3. **Updating with same data** - Idempotency check
4. **Incrementing usage on non-existent entry** - Error handling
5. **Concurrent updates** - Race condition between fetch and upsert
6. **Special characters in title/content** - Embedding generation
7. **Empty or null tag arrays** - Array handling
8. **Migration with relationships** - Pivot table data
9. **Null/missing optional fields** - Default value handling
10. **UUID collision** - Extremely rare but possible

### Test Coverage Requirements
- **100% coverage** enforced by Synapse Sentinel
- **PHPStan level 8** strict mode
- **Pest** testing framework

---

## 6. Test Strategy

### Approach
1. **Mock QdrantService** in command tests to verify correct method calls
2. **Real integration tests** for QdrantService itself (with test Qdrant instance)
3. **Separate migration tests** with MigrateToQdrantCommand

### Mock Strategy
- **Command tests:** Mock QdrantService to verify logic without vector DB dependency
- **Integration tests:** Real QdrantService + mock EmbeddingService
- **End-to-end tests:** Real stack (Qdrant + Ollama + embeddings)

### Critical Migration Tests
1. Verify all Entry fields map to Qdrant payload correctly
2. Verify UUID generation is unique and valid
3. Verify embeddings are generated correctly (title + content)
4. Test rollback scenario (restore from backup)
5. Verify usage tracking still works after migration
6. Test edge case: entry with all null optional fields

### Coverage Gaps to Watch
- **Relationship handling** (tags, collections, relationships)
- **Batch operations** (inserting 1000+ entries)
- **Performance testing** (Qdrant vs SQLite query speed)
- **Concurrent access** (multiple commands modifying same entry)

---

## 7. Risk Assessment

### HIGH RISK

#### Risk 1: Relationships (tags, collections) not supported
- **Issue:** Eloquent relationships stored in pivot tables (entry_tag, collection_entry, relationships)
- **Affected Commands:** KnowledgeLinkCommand, KnowledgeUnlinkCommand, Collection/AddCommand, Collection/RemoveCommand, KnowledgeRelatedCommand, KnowledgeGraphCommand
- **Mitigation Options:**
  1. Store relationships as arrays in payload (simple but limited)
  2. Create separate Qdrant collections for relationships (complex but scalable)
  3. Keep Entry model ONLY for relationships (hybrid approach)

#### Risk 2: No direct find-by-ID method
- **Issue:** QdrantService has search() but no getById()
- **Affected Commands:** KnowledgeShowCommand, KnowledgeUpdateCommand, KnowledgeMergeCommand, KnowledgeDeprecateCommand, KnowledgeArchiveCommand
- **Mitigation:** Implement getById() using Qdrant scroll or filter API

#### Risk 3: incrementUsage() not atomic
- **Issue:** Requires fetch + upsert (two operations), possible race condition
- **Affected Commands:** KnowledgeShowCommand, KnowledgeSearchCommand
- **Mitigation:** Accept race condition risk OR implement locking mechanism

---

### MEDIUM RISK

#### Risk 4: Integer IDs → UUID strings (breaking change)
- **Issue:** Existing users expect integer IDs, UUIDs are strings
- **Affected Commands:** ALL commands accepting ID argument
- **Mitigation:** Support both formats during migration period, clear documentation

#### Risk 5: Eloquent query builder features not available
- **Issue:** No scopes, eager loading, aggregations (count, sum, avg, groupBy)
- **Affected Commands:** KnowledgeListCommand, KnowledgeStatsCommand, KnowledgePruneCommand, KnowledgeStaleCommand
- **Mitigation:** Reimplement filtering/aggregation in application layer, cache results

---

### LOW RISK

#### Risk 6: Different ordering results
- **Issue:** Qdrant orders by relevance score, not confidence/usage_count
- **Affected Commands:** KnowledgeListCommand, KnowledgeSearchCommand
- **Mitigation:** Document new behavior, allow sorting by multiple criteria

---

## 8. Migration Strategy

### RECOMMENDED: Phased Migration

#### PHASE 1: Enhance QdrantService (Week 1)
- [ ] Add `getById(string|int $id, string $project): ?array`
- [ ] Add `incrementUsage(string|int $id, string $project): bool`
- [ ] Add `updateFields(string|int $id, array $fields, string $project): bool`
- [ ] Add `listAll(array $filters, int $limit, string $project): Collection`
- [ ] Write comprehensive tests for new methods (100% coverage)
- [ ] Update QdrantService documentation

#### PHASE 2: Refactor Simple Commands (Week 2)
✓ KnowledgeAddCommand (already done)
- [ ] KnowledgeListCommand - Pure filtering, no relationships
- [ ] KnowledgeShowCommand - Find by ID + usage tracking
- [ ] KnowledgeSearchCommand - Semantic search (already using Qdrant?)
- [ ] KnowledgeIndexCommand - Indexing operations

#### PHASE 3: Refactor Update Commands (Week 3)
- [ ] KnowledgeUpdateCommand - Update fields
- [ ] KnowledgeDeprecateCommand - Update status
- [ ] KnowledgeArchiveCommand - Update status
- [ ] KnowledgeValidateCommand - Update validation_date

#### PHASE 4: Refactor Stats/Aggregation Commands (Week 4)
- [ ] KnowledgeStatsCommand - Implement in-memory aggregations
- [ ] KnowledgePruneCommand - Filter + delete
- [ ] KnowledgeStaleCommand - Filter by date
- [ ] KnowledgeDuplicatesCommand - Similarity detection

#### PHASE 5: Handle Relationships (Week 5-6)
**CRITICAL DECISION NEEDED:** Choose relationship storage strategy

**Option A: Embed in Payload (Simple)**
```php
// Store related IDs as arrays in payload
$entry['related_to'] = ['uuid-1', 'uuid-2', 'uuid-3'];
$entry['collections'] = ['collection-uuid-1', 'collection-uuid-2'];
```
Pros: Simple, no extra collections needed
Cons: Limited querying, no relationship metadata

**Option B: Separate Collections (Complex)**
```php
// Create "relationships" collection in Qdrant
$qdrant->upsert([
    'id' => Str::uuid()->toString(),
    'from_entry_id' => $fromId,
    'to_entry_id' => $toId,
    'type' => 'relates_to',
    'metadata' => $metadata,
], 'relationships');
```
Pros: Full relationship support, queryable
Cons: More complex, multiple collection queries

**Option C: Hybrid (Keep Entry for Relationships)**
```php
// Use QdrantService for search
$results = $qdrant->search($query);

// Use Entry model for relationships
$entry = Entry::find($id);
$related = $entry->outgoingRelationships;
```
Pros: No refactor needed for relationship commands
Cons: Maintains SQLite dependency

**Affected Commands:**
- [ ] KnowledgeLinkCommand
- [ ] KnowledgeUnlinkCommand
- [ ] KnowledgeRelatedCommand
- [ ] KnowledgeGraphCommand
- [ ] KnowledgeExportGraphCommand
- [ ] Collection/AddCommand
- [ ] Collection/RemoveCommand

#### PHASE 6: Data Migration (Week 7)
- [ ] Run MigrateToQdrantCommand to copy all Entry data to Qdrant
- [ ] Verify data integrity (all fields, relationships)
- [ ] Parallel mode: Keep both SQLite and Qdrant running
- [ ] Gradual rollout: Switch commands one by one
- [ ] Monitor for issues (performance, errors, data loss)

#### PHASE 7: Cleanup (Week 8)
- [ ] Deprecate Entry model (or keep for relationships only)
- [ ] Remove SQLite database (backup first!)
- [ ] Update all documentation
- [ ] Remove Entry model imports from all commands
- [ ] Final testing with 100% coverage

---

## 9. Backwards Compatibility

### Feature Flag Approach
```php
// config/knowledge.php
return [
    'storage' => env('KNOWLEDGE_STORAGE', 'qdrant'), // or 'sqlite'
];
```

### Repository Pattern (Advanced)
```php
interface EntryRepositoryInterface {
    public function find(string|int $id): ?array;
    public function search(string $query, array $filters, int $limit): Collection;
    public function create(array $data): string|int;
    public function update(string|int $id, array $data): bool;
    public function delete(string|int $id): bool;
    public function incrementUsage(string|int $id): bool;
}

class QdrantEntryRepository implements EntryRepositoryInterface {
    public function __construct(private QdrantService $qdrant) {}

    public function find(string|int $id): ?array {
        return $this->qdrant->getById($id);
    }

    // ... implement all methods
}

class EloquentEntryRepository implements EntryRepositoryInterface {
    public function find(string|int $id): ?array {
        $entry = Entry::find($id);
        return $entry ? $entry->toArray() : null;
    }

    // ... implement all methods
}
```

Bind in `config/app.php`:
```php
$this->app->bind(EntryRepositoryInterface::class, function() {
    return config('knowledge.storage') === 'qdrant'
        ? new QdrantEntryRepository(app(QdrantService::class))
        : new EloquentEntryRepository();
});
```

**Pros:** Clean abstraction, easy to switch storage backends
**Cons:** More code, additional layer of abstraction

---

## 10. Immediate Action Items

### Priority 1 (This Week)
1. [ ] **Add missing methods to QdrantService**
   - getById(string|int $id, string $project): ?array
   - incrementUsage(string|int $id, string $project): bool
   - updateFields(string|int $id, array $fields, string $project): bool
   - listAll(array $filters, int $limit, string $project): Collection

2. [ ] **Write comprehensive tests** for new QdrantService methods
   - Unit tests with mocked Qdrant connector
   - Integration tests with real Qdrant instance
   - Edge case tests (null values, empty strings, large arrays)

### Priority 2 (Next Week)
3. [ ] **Decide on relationship storage strategy**
   - Embed in payload (simple)
   - Separate collections (complex)
   - Hybrid approach (keep Entry for relationships)

4. [ ] **Refactor KnowledgeListCommand** to use QdrantService
   - Replace Entry::query()->where()->get() with $qdrant->listAll()
   - Update tests to mock QdrantService
   - Maintain 100% coverage

5. [ ] **Refactor KnowledgeShowCommand** to use QdrantService
   - Replace Entry::find() with $qdrant->getById()
   - Replace $entry->incrementUsage() with $qdrant->incrementUsage()
   - Update tests

### Priority 3 (Following Weeks)
6. [ ] **Document breaking changes**
   - Integer ID → UUID migration guide
   - Command argument changes
   - Relationship handling changes

7. [ ] **Update remaining 20+ commands incrementally**
   - Follow phased migration plan (Phases 2-7)
   - One command at a time
   - Test after each refactor

8. [ ] **Create EntryRepository interface** (optional, for abstraction)
   - Define interface for storage operations
   - Implement QdrantEntryRepository
   - Implement EloquentEntryRepository (fallback)

---

## 11. Code Examples

### Example 1: KnowledgeListCommand Refactor

**BEFORE (Eloquent):**
```php
public function handle(): int
{
    $category = $this->option('category');
    $priority = $this->option('priority');
    $limit = (int) $this->option('limit');

    $query = Entry::query()
        ->when($category, fn($q, $val) => $q->where('category', $val))
        ->when($priority, fn($q, $val) => $q->where('priority', $val))
        ->orderBy('confidence', 'desc')
        ->orderBy('usage_count', 'desc');

    $totalCount = $query->count();
    $entries = $query->limit($limit)->get();

    foreach ($entries as $entry) {
        $this->line("[{$entry->id}] {$entry->title}");
    }

    return self::SUCCESS;
}
```

**AFTER (Qdrant):**
```php
public function handle(QdrantService $qdrant): int
{
    $category = $this->option('category');
    $priority = $this->option('priority');
    $limit = (int) $this->option('limit');

    // Build filters
    $filters = array_filter([
        'category' => $category,
        'priority' => $priority,
    ]);

    // Use listAll() for non-semantic filtering
    $entries = $qdrant->listAll($filters, $limit);

    // Note: Can't get total count without fetching all entries
    // Could cache count or show "Showing X entries" instead of "X of Y"

    foreach ($entries as $entry) {
        $this->line("[{$entry['id']}] {$entry['title']}");
    }

    return self::SUCCESS;
}
```

**Changes:**
- Inject QdrantService instead of using Entry model
- Build filters array instead of chained where() calls
- Use `listAll()` instead of `get()`
- Access array keys instead of object properties
- No total count (would require fetching all entries)

---

### Example 2: KnowledgeShowCommand Refactor

**BEFORE (Eloquent):**
```php
public function handle(): int
{
    $id = $this->argument('id');

    if (!is_numeric($id)) {
        $this->error('The ID must be a valid number.');
        return self::FAILURE;
    }

    $entry = Entry::find((int) $id);

    if (!$entry) {
        $this->line('Entry not found.');
        return self::FAILURE;
    }

    $entry->incrementUsage();

    $this->info("ID: {$entry->id}");
    $this->info("Title: {$entry->title}");
    $this->line("Content: {$entry->content}");
    $this->line("Priority: {$entry->priority}");
    $this->line("Usage Count: {$entry->usage_count}");

    return self::SUCCESS;
}
```

**AFTER (Qdrant):**
```php
public function handle(QdrantService $qdrant): int
{
    $id = $this->argument('id');

    // Support both UUID and integer IDs (backwards compat)
    $entry = $qdrant->getById($id);

    if (!$entry) {
        $this->line('Entry not found.');
        return self::FAILURE;
    }

    // Increment usage (non-atomic, possible race condition)
    $qdrant->incrementUsage($id);

    $this->info("ID: {$entry['id']}");
    $this->info("Title: {$entry['title']}");
    $this->line("Content: {$entry['content']}");
    $this->line("Priority: {$entry['priority']}");
    $this->line("Usage Count: {$entry['usage_count']}");

    return self::SUCCESS;
}
```

**Changes:**
- Inject QdrantService
- Use `getById()` instead of `Entry::find()`
- Use `incrementUsage()` method instead of model method
- Access array keys instead of object properties
- Removed numeric ID validation (support UUIDs)

---

## 12. Performance Considerations

### Potential Performance Issues

1. **Aggregations (Stats Command)**
   - Entry: SQL aggregations (fast, database-optimized)
   - Qdrant: Fetch all entries into memory, aggregate in PHP (slow)
   - **Solution:** Cache aggregated stats, update on write

2. **Batch Operations**
   - Entry: Eloquent chunk() for large datasets
   - Qdrant: May need to fetch all entries at once
   - **Solution:** Implement pagination in Qdrant API calls

3. **Relationships**
   - Entry: Eager loading with `with()` (single query)
   - Qdrant: Multiple collection queries
   - **Solution:** Embed relationships in payload OR use separate collection with batch queries

### Performance Testing
- [ ] Benchmark Entry vs Qdrant for common operations
- [ ] Test with large datasets (10k, 100k entries)
- [ ] Measure memory usage for aggregation queries
- [ ] Test concurrent access patterns

---

## 13. Documentation Updates Needed

1. **README.md** - Update architecture section
2. **API.md** - Document new QdrantService methods
3. **MIGRATION.md** - Entry → Qdrant migration guide
4. **BREAKING_CHANGES.md** - Integer ID → UUID, command changes
5. **Command help text** - Update for ID format changes
6. **Tests README** - Update test patterns for QdrantService

---

## Conclusion

This refactor is **complex** but **achievable** with a phased approach. The key challenges are:

1. **Missing QdrantService methods** (getById, incrementUsage, updateFields, listAll)
2. **Relationship storage** (decide: embed, separate collection, or hybrid)
3. **Breaking changes** (integer ID → UUID)
4. **Test coverage** (maintain 100% with Synapse Sentinel)

**Estimated Timeline:** 8 weeks for full migration

**Next Steps:**
1. Implement missing QdrantService methods (Priority 1)
2. Decide on relationship storage strategy (Priority 2)
3. Begin phased command refactoring (Priority 2)

---

**Generated by:** Entry → Qdrant Refactor Analysis Script
**Contact:** Jordan Partridge (@jordanpartridge)
