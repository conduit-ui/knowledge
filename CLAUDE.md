# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

AI-powered knowledge base CLI tool with semantic search and ChromaDB integration. Built as a standalone Laravel Zero application.

## Commands

```bash
composer test              # Run all tests
composer test-coverage     # Run with coverage report
composer format            # Format code
composer analyse           # Static analysis (PHPStan level 8)
./know {command}           # Run CLI command
./know list                # List all available commands
```

Run a single test file:
```bash
vendor/bin/pest tests/Feature/YourTest.php
```

## Quality Standards (Non-Negotiable)

- **100% test coverage** enforced by Synapse Sentinel gate
- **PHPStan level 8** with strict rules
- **Laravel Pint** code style (Laravel preset)
- **Auto-merge** after certification passes

## Architecture

- **Entry point**: `./know` (not `artisan`)
- **Commands**: `app/Commands/` - extend `LaravelZero\Framework\Commands\Command`
- **Tests**: `tests/Feature/` and `tests/Unit/` - use Pest
- **Services**: Register in `config/app.php` providers array

## TDD Workflow

1. Write failing test (RED)
2. Make test pass (GREEN)
3. Refactor if needed
4. Push PR - auto-merges after Sentinel gate passes

## Testing Commands

```php
$this->artisan('command-name')->assertSuccessful();
$this->artisan('command-name', ['argument' => 'value'])->assertExitCode(0);
```
