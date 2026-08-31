# Qwen: implement `specs/know-add-write-path`

You are implementing one knowledge SPEC. You are not designing. You are not adding features.

## Read first

1. `specs/know-add-write-path/spec.md` (normative)
2. `app/Commands/KnowledgeAddCommand.php`
3. `app/Mcp/Tools/RememberTool.php`
4. `tests/Feature/KnowledgeAddCommandTest.php`
5. `tests/Unit/Mcp/Tools/RememberToolTest.php`

Do not edit `WriteGateService.php` criteria.

## Do

Pest first. Allowlist files only.

`vendor/bin/pest --filter='KnowledgeAddCommand|RememberTool'` must pass.

Unknown `--category` is not a failure. Hash duplicate is success + existing id. WriteGate reject goes to stderr. `--quiet` silences stderr, not the exit code.

## Do not

Asgard, librarian, Ollama mesh router, new category enums, Prompts `error()` for gate/dup/wrong-shelf, extra SPECs.

Stop when tests are green and `git diff --stat` matches the spec allowlist.
