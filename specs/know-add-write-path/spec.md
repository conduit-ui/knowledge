# SPEC: know add — caller stays dumb (WriteGate on stderr)

Status: **READY** (night-shift 2026-08-31, tightened)
Repo: `conduit-ui/knowledge`
Issues: [#161](https://github.com/conduit-ui/knowledge/issues/161) remainder; [#167](https://github.com/conduit-ui/knowledge/issues/167) title is stale
Thor clone: `/home/jordan/Projects/conduit-ui/repos/knowledge`
Slug: `know-add-write-path`

**Tonight remainder is #161.** Category is already optional on `master` (`6ce840d`). #167's title ("do not require category") is stale. Remaining contract: WriteGate rejection (and other add failures) go to **stderr**; stdout is the success table only. Duplicate/wrong-shelf is not a caller error.

Issue #161 bonus `--quiet` prints UUID on success: **out of scope tonight** (residual `--id-only`). `--quiet` in this SPEC silences stderr; exit codes unchanged.

## Goal

`know add` / MCP `remember` accept title + content + provenance. WriteGate rejection is stderr + non-zero so pipelines that grep stdout cannot swallow a failed write. Unknown category is dropped. Hash/similar duplicate returns existing id as success (non-interactive).

## In-scope

- WriteGate reject → FAILURE, reason on **stderr only**, no stdout table. Do not use Laravel Prompts `error()` (stdout) for gate/dup/invalid-category.
- `--quiet` suppresses stderr; exit code unchanged.
- Unknown / omitted category is not a caller error (already mostly shipped; keep tests).
- Hash duplicate and non-interactive similarity duplicate → SUCCESS + existing id.
- Pest Process tests so stdout/stderr are separate (`artisan()` mixes streams).

## Out-of-scope

- WriteGate *criteria* / eval set of known-good entries.
- `--id-only` machine stdout (issue's UUID-on-success idea).
- Asgard librarian, tenant routing, shelves.
- knowledge#164 Ollama mesh router.
- PSTrax. Merge. Secrets. `gh pr merge`.

## Files likely touched

```
app/Commands/KnowledgeAddCommand.php
app/Mcp/Tools/RememberTool.php
tests/Feature/KnowledgeAddCommandTest.php
tests/Unit/Mcp/Tools/RememberToolTest.php
specs/know-add-write-path/
specs/README.md
```

Do not edit `WriteGateService` criteria.

## Tests

```
vendor/bin/pest --filter='KnowledgeAddCommand|RememberTool'
```

Must include: WriteGate rejection message on stderr not stdout (Process test); `--quiet` + reject → stderr empty, exit 1.

## Done-when

- [ ] `know add … >/tmp/out 2>/tmp/err` on gate reject → `/tmp/out` empty, `/tmp/err` has the reason, exit 1.
- [ ] Filter green. Allowlist only.
- [ ] Unknown category still not a caller error (existing remainder).

## Must-not

- No PSTrax. No merge. No secrets.
- Do not fail the caller because `--category` is missing or unknown.
- Do not print WriteGate rejection via Laravel Prompts `error()`.
- Do not call Asgard/librarian. Do not invent shelves.


## Already shipped (do not rebuild)

| Piece | What it is | What it is not |
|---|---|---|
| `know add {title} --content=` | Required write | Category required |
| `--category=` option | Optional. Missing → payload `category: null` | Closed enum that fails the caller |
| Pest `creates a knowledge entry with required fields` | Title + content | Category in the required set |
| MCP `RememberTool` schema | `category` optional | Enum gate on write |
| MCP duplicate catch | `status: duplicate_detected` + `existing_id` as **text**, not `Response::error` | CLI hash-dup still `FAILURE` |
| `--force` | Skip write gate and duplicate check | Quiet stderr |
| WriteGate heuristics | Category `deployment`/`security`/`architecture` as **positive** signals | Caller-facing shelf picker |

On `master` (2026-07-06, `6ce840d`) category is already optional. #167’s title is stale. Remaining contract is wrong-shelf + duplicate + stderr.

## One-sentence intent

**`know add` and MCP `remember` accept title + content + provenance; unknown category is dropped; exact or similar duplicate returns the existing id as success; WriteGate rejection is stderr + non-zero; librarian is not this package.**

## Locked decisions

- Required on write: `title`, `content`. Provenance (`--source`, git context, `--ticket`, `--author`) is optional and already wired.
- Category is a **hint**. It is never required. It is never a caller error.
- Unknown / empty / omitted category → persist `category: null`. stderr warning only when the caller **passed** a value that is not in `VALID_CATEGORIES`. Omitted category: no warning.
- Do not persist the invalid string. Do not invent shelves (`patterns`, `decisions`, `gotchas`, …). MCP must match CLI: unknown → `null`.
- Hash duplicate → **SUCCESS**. Print existing id on stdout (scripts capture it). Notice on stderr. No new upsert. No prompt.
- Similarity duplicate, non-interactive → **SUCCESS**, same as hash: return existing id. No `confirm()`.
- Similarity duplicate, interactive TTY without `--force` → keep today’s supersede prompt. Agents and pipelines are non-interactive.
- `--force` still skips gate + duplicate detection and creates a new id.
- WriteGate reject → **FAILURE**, reason on **stderr only**, no stdout table. `--force` bypasses. `--quiet` suppresses stderr (exit code unchanged).
- Do not change WriteGate *criteria* in this SPEC. Heuristics may keep using category. Librarian may rewrite shelves later; not here.
- Laravel Prompts `error()` is stdout for this CLI. Do not use it for gate/dup/invalid-category. Use stderr (`fwrite(STDERR, …)` or the command error output).
- Pest `artisan()` mixes streams. Gate/dup/quiet tests that care about stderr must drive the `know` binary (or Symfony Process) so stdout/stderr are separate. Existing `assertFailed()` / `assertSuccessful()` artisan tests stay for exit codes.
- MCP: duplicate stays a JSON text payload (`duplicate_detected` + `existing_id`), not `Response::error`. WriteGate reject stays `Response::error`. Unknown category → stored `null`, still create if gate passes.
- `#164` mesh OpenAI router is a residual. Do not port `OllamaService` here.

## CLI contract

```
know add <title> --content=<blob> [--category=…] [--quiet] [--force] …
```

| Input | stdout | stderr | exit |
|---|---|---|---|
| New entry, no category | table (or id); category shown `N/A` | empty unless other warnings | 0 |
| `--category=testing` (valid) | table; category `testing` | empty | 0 |
| `--category=bogus` | table; category `N/A` | warning: ignored unknown category | 0 |
| Hash or similar dup, not TTY, no `--force` | existing id | notice: already exists | 0 |
| WriteGate reject, no `--force` | empty | `Write gate rejected entry: …` | 1 |
| WriteGate reject + `--quiet` | empty | empty | 1 |
| Missing `--content` | empty | content required | 1 |

`--quiet` applies to warnings and rejections on stderr. It does not change exit codes. It does not hide a successful table unless we later add a `--id-only` flag (not this SPEC).

## MCP `remember` contract

| Input | Result |
|---|---|
| title + content, no category | `status: created`, `category` null |
| `category: "bogus"` | created, stored category null |
| hash/similar duplicate | `status: duplicate_detected`, `existing_id`, not an error |
| WriteGate fail | `Response::error` (already) |

Schema: `category` remains optional, **no enum**. Do not add CLI’s `VALID_CATEGORIES` as a JSON-schema enum.

## Allowlist

```
app/Commands/KnowledgeAddCommand.php
app/Mcp/Tools/RememberTool.php
tests/Feature/KnowledgeAddCommandTest.php
tests/Unit/Mcp/Tools/RememberToolTest.php
specs/know-add-write-path/
specs/README.md
```

Do not edit `WriteGateService` unless a test cannot observe stderr without it — it should not need a criteria change.

## Pest (required)

Keep existing green tests except the ones this SPEC deliberately changes:

- `it('validates category is valid')` → **replace**: unknown category succeeds, persisted category null, upsert **does** run.
- `it('fails on exact hash duplicate')` → **replace**: exit 0, no second representation of the blob; existing id available.
- Similarity tests that `assertFailed()` on decline remain for **TTY / confirm** only. Add a non-interactive test: no confirm, exit 0, existing id.

Add:

- unknown category is not a caller error (CLI + MCP)
- omitted category still creates (already exists; keep)
- WriteGate rejection message is on stderr, not stdout (Process test)
- `--quiet` + WriteGate reject: stderr empty, exit 1
- MCP unknown category stored null
- MCP duplicate remains non-error JSON

Filter:

```
vendor/bin/pest --filter='KnowledgeAddCommand|RememberTool'
```

House tests outside the filter must stay green if you run the full suite; do not “fix” them by widening the allowlist.

## Forbidden strings / behavior

- Do not fail the caller because `--category` is missing or unknown.
- Do not print WriteGate rejection via Laravel Prompts `error()` (stdout).
- Do not call Asgard, librarian, Qdrant-as-org-memory, or invent shelves.
- Do not port Ollama to the mesh router.
- Do not add `--category` to MCP required schema.

## Residuals (not this PR)

- knowledge#164 — `OllamaService` → mesh OpenAI endpoint
- WriteGate criteria / auto-tagger as librarian
- `--id-only` machine stdout
- Tenant routing

## Acceptance

1. Filter green.
2. `git diff --stat` matches the allowlist.
3. `know add "x" --content="enough blob" --category=not-a-shelf` exits 0 and stores `category: null`.
4. Hash duplicate exits 0 and does not upsert a second point.
5. WriteGate reject: `know add … >/tmp/out 2>/tmp/err` → `/tmp/out` empty, `/tmp/err` has the reason, exit 1.

## Implementer contract (Qwen)

Read `specs/know-add-write-path/QWEN.md` then this file. Pest first. Allowlist only. Stop when the filter is green.
