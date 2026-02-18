# Feature Specification: Supersede Knowledge Entries

## User Story
As a developer using the Knowledge CLI, I want to mark an outdated knowledge entry as "superseded" by a newer, more accurate entry. This action should automatically deprecate the old entry, link it to the new one, and provide a clear reason for the replacement, ensuring that search results prioritize current information while preserving historical context.

## Requirements
1.  **Command Interface**:
    -   Command: `know supersede <old_id> <new_id> [reason]`
    -   Accepts optional `[reason]` argument. If omitted, the system generates a reason using AI.
2.  **Data Updates**:
    -   **Old Entry**:
        -   Status updated to `deprecated`.
        -   `superseded_by` field set to `new_id`.
        -   `superseded_date` set to current timestamp.
        -   `superseded_reason` set to provided or generated reason.
    -   **New Entry**:
        -   (Optional) Validate that status is `validated` or `draft` (not `deprecated`).
3.  **AI Integration**:
    -   Use `x-ai/grok-4-fast` via `AiService` to generate the supersession reason if not provided.
    -   Prompt should analyze both entries and summarize the improvement/change.
4.  **Validation**:
    -   Prevent circular dependencies (A supersedes B, B supersedes A).
    -   Ensure both entries exist.
    -   Ensure `old_id` !== `new_id`.

## Technical Implementation Plan

### 1. New Service: `App\Services\SupersedeService`
A new service responsible for the business logic of superseding entries. It orchestrates `QdrantService` for data updates and `AiService` for reason generation.

#### Dependencies:
-   `App\Services\QdrantService`
-   `App\Services\AiService`
-   `App\Services\KnowledgeCacheService` (optional, for cache invalidation)

#### Responsibilities:
-   Fetch old and new entries.
-   Validate entry existence and status.
-   Check for circular references.
-   Generate reason via AI if missing.
-   Update old entry metadata.

### 2. Update `App\Services\AiService`
-   Ensure `generate()` supports model override or configuration for `x-ai/grok-4-fast`.
-   Alternatively, add a `compare(array $oldEntry, array $newEntry): string` method that constructs a specific prompt for Grok.

### 3. New Command: `App\Commands\SupersedeCommand`
-   Signature: `supersede {old_id} {new_id} {reason? : Optional reason for supersession}`
-   Flow:
    1.  Resolve project.
    2.  Call `SupersedeService::supersede($oldId, $newId, $reason)`.
    3.  Display success message with generated reason (if applicable).
    4.  Show diff or summary of changes.

## Class Interface: `SupersedeService`

```php
namespace App\Services;

class SupersedeService
{
    public function __construct(
        private QdrantService $qdrant,
        private AiService $ai
    ) {}

    /**
     * Mark an entry as superseded by another.
     *
     * @param string|int $oldId
     * @param string|int $newId
     * @param string|null $reason
     * @param string $project
     * @return array{old: array, new: array, reason: string}
     * @throws \Exception If validation fails
     */
    public function supersede(string|int $oldId, string|int $newId, ?string $reason = null, string $project = 'default'): array;

    /**
     * Check for circular dependency.
     */
    private function checkCircularDependency(string|int $oldId, string|int $newId, string $project): bool;

    /**
     * Generate supersession reason using AI.
     */
    private function generateReason(array $oldEntry, array $newEntry): string;
}
```

## AI Prompt Strategy (Grok)

When `reason` is missing, the `SupersedeService` will construct a prompt:

```text
You are maintaining a technical knowledge base.
Entry A (Old): "{old_title}"
Summary A: "{old_content_excerpt}"

Entry B (New): "{new_title}"
Summary B: "{new_content_excerpt}"

Explain why Entry B supersedes Entry A in one concise sentence.
Focus on what is newer, more accurate, or improved.
```

## Edge Cases
1.  **Circular Reference**:
    -   If A supersedes B, and user tries "supersede B A", throw error.
    -   If A supersedes B, B supersedes C, and user tries "supersede C A", detect cycle. (Recursive check max depth 5).
2.  **Cross-Project Supersession**:
    -   Currently supported by `QdrantService` if project is consistent.
    -   MVP: Restrict to same project for safety.
3.  **Missing AI Service**:
    -   Fallback to a default reason: "Superseded by {new_id}."

## Schema Updates
No schema changes required. `QdrantService` already supports:
-   `superseded_by`
-   `superseded_date`
-   `superseded_reason`
-   `status` ('deprecated')
