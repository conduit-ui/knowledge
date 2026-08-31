Implement SPEC `specs/know-add-write-path/spec.md` exactly. Read `specs/know-add-write-path/QWEN.md` first.

You are a local Ollama coding agent (qwen-coder-32k). Pest first. Allowlist only. Tonight remainder is #161 stderr.

Do:
1. Read the spec and `app/Commands/KnowledgeAddCommand.php`, `app/Mcp/Tools/RememberTool.php`, the two listed test files.
2. WriteGate reject → stderr only, exit 1, no stdout table. No Laravel Prompts `error()` for gate/dup/unknown-category. `--quiet` silences stderr, not the exit code.
3. Unknown category succeeds (persisted null). Hash duplicate is exit 0 with existing id. Non-interactive similarity = return existing.
4. Process tests so stdout/stderr are separate.
5. `vendor/bin/pest --filter='KnowledgeAddCommand|RememberTool'` green. Stop.

Do not: Asgard, librarian, mesh Ollama router, new shelves, JSON-schema enum on MCP category, WriteGate criteria change, `--id-only`, PSTrax, `gh pr merge`.
