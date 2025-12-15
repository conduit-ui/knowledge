# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with this Laravel Zero application.

## What This Is

AI-powered knowledge base CLI tool with semantic search and ChromaDB integration. Built as a standalone Laravel Zero application.

## Quality Standards (Non-Negotiable)

- **100% test coverage** enforced by Synapse Sentinel
- **PHPStan level 8** with strict rules
- **Laravel Pint** code style (Laravel preset)
- **Auto-merge** after certification passes

## Development Workflow

### TDD Process
1. Write failing test (RED)
2. Make test pass (GREEN)
3. Refactor code
4. Push PR

### Commands
```bash
composer test              # Run all tests
composer test-coverage     # Run with coverage report
composer format            # Format code
composer analyse           # Static analysis
./application {command}    # Run CLI command
```

## Laravel Zero Conventions

- **Commands**: `app/Commands/` - extend `LaravelZero\Framework\Commands\Command`
- **Tests**: `tests/Feature/` and `tests/Unit/` - use Pest
- **Services**: `app/Providers/` - register in `config/app.php`
- **Entry point**: `./application` (not `artisan`)

Test commands with: `$this->artisan('command-name')->assertSuccessful();`