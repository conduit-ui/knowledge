<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;

class KnowledgeInstallCommand extends Command
{
    protected $signature = 'knowledge:install
                            {--bin-path= : Custom binary path (default: ~/.local/bin)}
                            {--data-path= : Custom data path (default: ~/.conduit)}
                            {--force : Overwrite existing installation}';

    protected $description = 'Install knowledge CLI globally for macOS/Linux';

    private const DEFAULT_BIN_PATH = '~/.local/bin';

    private const DEFAULT_DATA_PATH = '~/.conduit';

    public function handle(): int
    {
        // Check OS - only support macOS and Linux
        if (PHP_OS_FAMILY === 'Windows') {
            $this->error('Windows is not supported. Use WSL for Windows installations.');

            return self::FAILURE;
        }

        $binPath = $this->expandPath($this->option('bin-path') ?? self::DEFAULT_BIN_PATH);
        $dataPath = $this->expandPath($this->option('data-path') ?? self::DEFAULT_DATA_PATH);
        $force = $this->option('force');

        $this->info('🔧 Installing knowledge CLI...');
        $this->newLine();

        // Step 1: Create bin directory
        if (! $this->createDirectory($binPath, 'bin')) {
            return self::FAILURE;
        }

        // Step 2: Create data directory
        if (! $this->createDirectory($dataPath, 'data')) {
            return self::FAILURE;
        }

        // Step 3: Create the wrapper script
        $binaryPath = $binPath.'/know';
        if (! $this->createBinary($binaryPath, $force)) {
            return self::FAILURE;
        }

        // Step 4: Create/update .env with database path
        if (! $this->configureDatabase($dataPath)) {
            return self::FAILURE;
        }

        // Step 5: Run migrations
        if (! $this->runMigrations()) {
            return self::FAILURE;
        }

        // Step 6: Verify installation
        $this->verifyInstallation($binPath);

        return self::SUCCESS;
    }

    private function expandPath(string $path): string
    {
        if (str_starts_with($path, '~')) {
            $home = getenv('HOME') ?: posix_getpwuid(posix_getuid())['dir'];

            return $home.substr($path, 1);
        }

        return $path;
    }

    private function createDirectory(string $path, string $label): bool
    {
        if (File::isDirectory($path)) {
            $this->line("  ✓ {$label} directory exists: {$path}");

            return true;
        }

        if (! File::makeDirectory($path, 0755, true)) {
            $this->error("Failed to create {$label} directory: {$path}");

            return false;
        }

        $this->line("  ✓ Created {$label} directory: {$path}");

        return true;
    }

    private function createBinary(string $binaryPath, bool $force): bool
    {
        if (File::exists($binaryPath) && ! $force) {
            $this->line("  ✓ Binary already exists: {$binaryPath}");
            $this->line('    Use --force to overwrite');

            return true;
        }

        $sourcePath = dirname(__DIR__, 2);
        $wrapper = <<<BASH
#!/bin/bash
# Knowledge CLI wrapper - installed by knowledge:install
php "{$sourcePath}/know" "\$@"
BASH;

        if (! File::put($binaryPath, $wrapper)) {
            $this->error("Failed to create binary: {$binaryPath}");

            return false;
        }

        chmod($binaryPath, 0755);
        $this->line("  ✓ Created binary: {$binaryPath}");

        return true;
    }

    private function configureDatabase(string $dataPath): bool
    {
        $dbPath = $dataPath.'/knowledge.sqlite';
        $envPath = dirname(__DIR__, 2).'/.env';

        // Create empty database file if it doesn't exist
        if (! File::exists($dbPath)) {
            File::put($dbPath, '');
            $this->line("  ✓ Created database: {$dbPath}");
        } else {
            $this->line("  ✓ Database exists: {$dbPath}");
        }

        // Update .env file
        $envContent = File::exists($envPath) ? File::get($envPath) : '';

        // Check if DB_DATABASE is already set
        if (str_contains($envContent, 'DB_DATABASE=')) {
            // Update existing value
            $envContent = preg_replace(
                '/^DB_DATABASE=.*$/m',
                "DB_DATABASE={$dbPath}",
                $envContent
            );
        } else {
            // Add new value
            $envContent .= "\nDB_DATABASE={$dbPath}\n";
        }

        if (! File::put($envPath, $envContent)) {
            $this->error('Failed to update .env file');

            return false;
        }

        $this->line('  ✓ Configured database path in .env');

        return true;
    }

    private function runMigrations(): bool
    {
        $this->line('  ⟳ Running migrations...');

        try {
            $this->callSilently('migrate', ['--force' => true]);
            $this->line('  ✓ Migrations complete');

            return true;
        } catch (\Exception $e) {
            $this->error('  ✗ Migration failed: '.$e->getMessage());

            return false;
        }
    }

    private function verifyInstallation(string $binPath): void
    {
        $this->newLine();
        $this->info('✅ Installation complete!');
        $this->newLine();

        // Check if bin path is in PATH
        $pathEnv = getenv('PATH') ?: '';
        $inPath = str_contains($pathEnv, $binPath);

        if (! $inPath) {
            $this->warn("Note: {$binPath} is not in your PATH");
            $this->line('Add this to your shell profile (~/.zshrc or ~/.bashrc):');
            $this->newLine();
            $this->line("  export PATH=\"{$binPath}:\$PATH\"");
            $this->newLine();
            $this->line('Then restart your terminal or run: source ~/.zshrc');
        } else {
            $this->line('You can now use the knowledge CLI anywhere:');
        }

        $this->newLine();
        $this->line('  know knowledge:list              # List entries');
        $this->line('  know knowledge:search "query"    # Search knowledge');
        $this->line('  know knowledge:add "title"       # Add new entry');
        $this->newLine();
    }
}
