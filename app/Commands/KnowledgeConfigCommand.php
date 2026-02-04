<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\KnowledgePathService;
use LaravelZero\Framework\Commands\Command;

class KnowledgeConfigCommand extends Command
{
    protected $signature = 'config
                            {action=list : Action to perform (list, get, set)}
                            {key? : Configuration key (e.g., qdrant.url)}
                            {value? : Configuration value}';

    protected $description = 'Manage Knowledge configuration settings';

    private const CONFIG_FILE = 'config.json';

    private const DEFAULTS = [
        'qdrant' => [
            'url' => 'http://localhost:6333',
            'collection' => 'knowledge',
        ],
        'embeddings' => [
            'url' => 'http://localhost:8001',
        ],
    ];

    private const VALID_KEYS = [
        'qdrant.url',
        'qdrant.collection',
        'embeddings.url',
    ];

    private const URL_KEYS = [
        'qdrant.url',
        'embeddings.url',
    ];

    private KnowledgePathService $pathService;

    public function handle(KnowledgePathService $pathService): int
    {
        $this->pathService = $pathService;
        $action = $this->argument('action');

        // @codeCoverageIgnoreStart
        // Type narrowing for PHPStan - Laravel's command system ensures string
        if (! is_string($action)) {
            return $this->invalidAction('');
        }
        // @codeCoverageIgnoreEnd

        return match ($action) {
            'list' => $this->listConfig(),
            'get' => $this->getConfig(),
            'set' => $this->setConfig(),
            default => $this->invalidAction($action),
        };
    }

    private function listConfig(): int
    {
        $config = $this->loadConfig();

        $this->line('<fg=cyan>Knowledge Configuration:</>');
        $this->newLine();

        $this->displayConfigTree($config);

        return self::SUCCESS;
    }

    private function getConfig(): int
    {
        $key = $this->argument('key');

        // Type narrowing for PHPStan
        if (! is_string($key) || $key === '') {
            $this->error('Key is required for get action.');

            return self::FAILURE;
        }

        if (! $this->isValidKey($key)) {
            $this->error('Invalid configuration key: '.$key);
            $this->line('Valid keys: '.implode(', ', self::VALID_KEYS));

            return self::FAILURE;
        }

        $config = $this->loadConfig();
        $value = $this->getNestedValue($config, $key);

        $this->line($this->formatValue($value));

        return self::SUCCESS;
    }

    private function setConfig(): int
    {
        $key = $this->argument('key');
        $value = $this->argument('value');

        // Type narrowing for PHPStan
        if (! is_string($key) || $key === '' || ! is_string($value) || $value === '') {
            $this->error('Key and value are required for set action.');

            return self::FAILURE;
        }

        if (! $this->isValidKey($key)) {
            $this->error('Invalid configuration key: '.$key);
            $this->line('Valid keys: '.implode(', ', self::VALID_KEYS));

            return self::FAILURE;
        }

        // Validate value based on key type
        $validationResult = $this->validateValue($key, $value);
        if ($validationResult !== null) {
            $this->error($validationResult);

            return self::FAILURE;
        }

        $config = $this->loadConfig();
        $typedValue = $this->parseValue($value);
        $this->setNestedValue($config, $key, $typedValue);

        $this->saveConfig($config);

        $this->info('Configuration updated successfully.');
        $this->line("<fg=cyan>{$key}:</> ".$this->formatValue($typedValue));

        return self::SUCCESS;
    }

    private function invalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->line('Valid actions: list, get, set');

        return self::FAILURE;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        $configPath = $this->getConfigPath();

        if (! file_exists($configPath)) {
            return self::DEFAULTS;
        }

        $content = file_get_contents($configPath);
        // @codeCoverageIgnoreStart
        // Defensive: file_get_contents only fails on read errors
        if ($content === false) {
            return self::DEFAULTS;
        }
        // @codeCoverageIgnoreEnd

        $config = json_decode($content, true);
        if (! is_array($config)) {
            return self::DEFAULTS;
        }

        // Merge with defaults to ensure all keys exist
        return array_replace_recursive(self::DEFAULTS, $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function saveConfig(array $config): void
    {
        $knowledgeDir = $this->pathService->getKnowledgeDirectory();
        $this->pathService->ensureDirectoryExists($knowledgeDir);

        $configPath = $this->getConfigPath();
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
    }

    private function getConfigPath(): string
    {
        return $this->pathService->getKnowledgeDirectory().'/'.self::CONFIG_FILE;
    }

    private function isValidKey(string $key): bool
    {
        return in_array($key, self::VALID_KEYS, true);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function getNestedValue(array $config, string $key): mixed
    {
        $parts = explode('.', $key);
        $value = $config;

        foreach ($parts as $part) {
            // @codeCoverageIgnoreStart
            // Defensive: defaults are always merged, so keys should exist
            if (! is_array($value) || ! array_key_exists($part, $value)) {
                return null;
            }
            // @codeCoverageIgnoreEnd
            $value = $value[$part];
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function setNestedValue(array &$config, string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $current = &$config;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $current[$part] = $value;
            } else {
                // @codeCoverageIgnoreStart
                // Defensive: defaults are always merged, so parent keys should exist
                if (! isset($current[$part]) || ! is_array($current[$part])) {
                    $current[$part] = [];
                }
                // @codeCoverageIgnoreEnd
                $current = &$current[$part];
            }
        }
    }

    private function parseValue(string $value): string
    {
        // All current config values are strings (URLs or collection names)
        return $value;
    }

    private function validateValue(string $key, string $value): ?string
    {
        if (! in_array($key, self::URL_KEYS, true)) {
            return null;
        }
        if (! $this->isValidUrl($value)) {
            return "Value for {$key} must be a valid URL.";
        }

        return null;
    }

    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'));
    }

    private function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        // @codeCoverageIgnoreStart
        // Defensive: config values should always be strings
        if ($value === null) {
            return 'null';
        }

        return (string) $value;
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function displayConfigTree(array $config, string $prefix = ''): void
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $this->displayConfigTree($value, $prefix.$key.'.');
            } else {
                $fullKey = $prefix.$key;
                $formattedValue = $this->formatValue($value);
                $this->line("<fg=cyan>{$fullKey}:</> {$formattedValue}");
            }
        }
    }
}
