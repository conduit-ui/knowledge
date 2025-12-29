# Knowledge Sync Command

Sync knowledge entries from the prefrontal-cortex Knowledge API to your local knowledge base.

## Setup

Add your API token to the `.env` file:

```bash
PREFRONTAL_API_TOKEN=your-api-token-here
```

## Usage

### Basic Sync

Sync new entries from the default prefrontal-cortex API:

```bash
php know sync
```

This will:
- Fetch new entries from `https://prefrontal-cortex.laravel.cloud/api/knowledge/all`
- Create new entries in the local database
- Update existing entries (matched by source URL)
- Auto-index entries to ChromaDB (if enabled)
- Track the last sync timestamp to only fetch new entries next time

### Full Sync

Perform a full sync, ignoring the last sync timestamp:

```bash
php know sync --full
```

### Custom API URL

Sync from a custom API endpoint:

```bash
php know sync --from=https://your-api.example.com/api/knowledge
```

## API Response Format

The API should return entries in this format:

```json
[
  {
    "title": "Issue #123: Fix authentication bug",
    "content": "Fixed JWT token validation in auth middleware",
    "type": "issue",
    "tags": ["bug", "security", "auth"],
    "url": "https://github.com/org/repo/issues/123",
    "repo": "org/repo"
  }
]
```

Or wrapped in a data key:

```json
{
  "data": [
    {
      "title": "PR #456: Add search feature",
      "content": "Implemented full-text search",
      "type": "pr",
      "tags": ["feature", "search"],
      "url": "https://github.com/org/repo/pull/456",
      "repo": "org/repo"
    }
  ]
}
```

## Field Mapping

API fields are mapped to Entry model fields:

| API Field | Entry Field | Default Value |
|-----------|-------------|---------------|
| title | title | "Untitled" |
| content | content | (required) |
| type | (not stored) | - |
| tags | tags | null |
| url | source | null |
| repo | module | null |
| - | category | "github" |
| - | priority | "medium" |
| - | confidence | 70 |
| - | status | "draft" |

## Update Logic

Entries are matched by their `source` URL. If an entry with the same source URL already exists, it will be updated. Otherwise, a new entry is created.

## Sync Tracking

The command tracks the last sync timestamp in cache using the key `knowledge.last_sync_timestamp`. This allows incremental syncs by passing `?since=<timestamp>` to the API.

Use `--full` to ignore the last sync timestamp and fetch all entries.

## Auto-indexing

When entries are created or updated, the Entry model automatically:
1. Saves to the SQLite database
2. Indexes to ChromaDB for semantic search (if enabled)

No manual indexing step is required.

## Example Output

```
Starting sync from: https://prefrontal-cortex.laravel.cloud/api/knowledge/all
Last sync: 2025-12-29T12:00:00+00:00
Found 15 entries to sync.
███████████████████████████████████████████ 100%

Sync completed successfully!
+----------+-------+
| Status   | Count |
+----------+-------+
| Created  | 12    |
| Updated  | 3     |
| Failed   | 0     |
| Total    | 15    |
+----------+-------+
```

## Error Handling

- Missing API token: Command fails with error message
- API connection errors: Command fails with HTTP error details
- Invalid entry data: Entry is skipped, warning is displayed
- Failed entries are counted in the summary table

## Testing

Run tests for the sync command:

```bash
php vendor/bin/pest tests/Feature/SyncFromApiCommandTest.php
```
