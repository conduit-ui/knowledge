<?php

declare(strict_types=1);

use App\Services\SymbolIndexService;

uses()->group('symbol-index');

function createTestIndex(string $dir): string
{
    $indexDir = $dir.'/code-index';
    mkdir($indexDir, 0755, true);

    // Create index JSON matching jcodemunch format
    $index = [
        'repo' => 'local/test-repo',
        'owner' => 'local',
        'name' => 'test-repo',
        'indexed_at' => '2026-03-05T12:00:00',
        'index_version' => 2,
        'source_files' => ['app/Services/UserService.php', 'app/Models/User.php'],
        'languages' => ['php' => 2],
        'file_hashes' => [
            'app/Services/UserService.php' => hash('sha256', 'user-service-content'),
            'app/Models/User.php' => hash('sha256', 'user-model-content'),
        ],
        'symbols' => [
            [
                'id' => 'app/Services/UserService.php::UserService#class',
                'file' => 'app/Services/UserService.php',
                'name' => 'UserService',
                'qualified_name' => 'UserService',
                'kind' => 'class',
                'language' => 'php',
                'signature' => 'class UserService',
                'docstring' => 'Handles user operations and authentication.',
                'summary' => 'Class UserService',
                'decorators' => [],
                'keywords' => ['user', 'auth'],
                'parent' => null,
                'line' => 10,
                'end_line' => 50,
                'byte_offset' => 100,
                'byte_length' => 800,
                'content_hash' => 'abc123',
            ],
            [
                'id' => 'app/Services/UserService.php::UserService.authenticate#method',
                'file' => 'app/Services/UserService.php',
                'name' => 'authenticate',
                'qualified_name' => 'UserService.authenticate',
                'kind' => 'method',
                'language' => 'php',
                'signature' => 'public function authenticate(string $email, string $password): bool',
                'docstring' => 'Authenticate a user by email and password.',
                'summary' => 'Authenticate a user.',
                'decorators' => [],
                'keywords' => ['auth', 'login'],
                'parent' => 'app/Services/UserService.php::UserService#class',
                'line' => 15,
                'end_line' => 30,
                'byte_offset' => 200,
                'byte_length' => 400,
                'content_hash' => 'def456',
            ],
            [
                'id' => 'app/Models/User.php::User#class',
                'file' => 'app/Models/User.php',
                'name' => 'User',
                'qualified_name' => 'User',
                'kind' => 'class',
                'language' => 'php',
                'signature' => 'class User extends Model',
                'docstring' => '',
                'summary' => 'Class User',
                'decorators' => [],
                'keywords' => [],
                'parent' => null,
                'line' => 8,
                'end_line' => 40,
                'byte_offset' => 50,
                'byte_length' => 600,
                'content_hash' => 'ghi789',
            ],
        ],
    ];

    file_put_contents($indexDir.'/local-test-repo.json', json_encode($index, JSON_PRETTY_PRINT));

    // Create raw content directory with source files
    $contentDir = $indexDir.'/local-test-repo';
    mkdir($contentDir.'/app/Services', 0755, true);
    mkdir($contentDir.'/app/Models', 0755, true);

    // Write source files with content at correct byte offsets
    $serviceContent = str_repeat(' ', 200)
        .'public function authenticate(string $email, string $password): bool { return true; }'
        .str_repeat(' ', 500);
    file_put_contents($contentDir.'/app/Services/UserService.php', $serviceContent);
    file_put_contents($contentDir.'/app/Models/User.php', str_repeat(' ', 700));

    return $indexDir;
}

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/symbol-index-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->indexDir = createTestIndex($this->tempDir);
    $this->service = new SymbolIndexService($this->indexDir);
});

afterEach(function (): void {
    // Recursive delete
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($this->tempDir);
});

describe('searchSymbols', function (): void {
    it('finds symbols by exact name match', function (): void {
        $results = $this->service->searchSymbols('authenticate', 'local/test-repo');

        expect($results)->not->toBeEmpty();
        expect($results[0]['name'])->toBe('authenticate');
        expect($results[0]['score'])->toBeGreaterThanOrEqual(20);
    });

    it('finds symbols by partial name match', function (): void {
        $results = $this->service->searchSymbols('auth', 'local/test-repo');

        expect($results)->not->toBeEmpty();
        // Should find both UserService (via keywords) and authenticate (via name)
        $names = array_column($results, 'name');
        expect($names)->toContain('authenticate');
    });

    it('ranks exact matches above partial matches', function (): void {
        $results = $this->service->searchSymbols('User', 'local/test-repo');

        expect($results)->toHaveCount(3);
        expect($results[0]['name'])->toBe('User');
    });

    it('filters by symbol kind', function (): void {
        $results = $this->service->searchSymbols('user', 'local/test-repo', kind: 'class');

        foreach ($results as $result) {
            expect($result['kind'])->toBe('class');
        }
    });

    it('filters by file pattern', function (): void {
        $results = $this->service->searchSymbols('user', 'local/test-repo', filePattern: '*/Models/*');

        foreach ($results as $result) {
            expect($result['file'])->toContain('Models');
        }
    });

    it('respects max results limit', function (): void {
        $results = $this->service->searchSymbols('user', 'local/test-repo', maxResults: 1);

        expect($results)->toHaveCount(1);
    });

    it('returns empty array for non-matching query', function (): void {
        $results = $this->service->searchSymbols('zzzznonexistent', 'local/test-repo');

        expect($results)->toBeEmpty();
    });

    it('returns empty array for non-existent repo', function (): void {
        $results = $this->service->searchSymbols('test', 'local/nonexistent');

        expect($results)->toBeEmpty();
    });

    it('scores signature matches', function (): void {
        $results = $this->service->searchSymbols('string email', 'local/test-repo');

        expect($results)->not->toBeEmpty();
        // authenticate has 'string $email' in signature
        $names = array_column($results, 'name');
        expect($names)->toContain('authenticate');
    });

    it('scores docstring matches', function (): void {
        $results = $this->service->searchSymbols('password', 'local/test-repo');

        expect($results)->not->toBeEmpty();
        $names = array_column($results, 'name');
        expect($names)->toContain('authenticate');
    });

    it('scores keyword matches', function (): void {
        $results = $this->service->searchSymbols('login', 'local/test-repo');

        expect($results)->not->toBeEmpty();
        $names = array_column($results, 'name');
        expect($names)->toContain('authenticate');
    });
});

describe('getSymbolSource', function (): void {
    it('retrieves source code via byte-offset seek', function (): void {
        $source = $this->service->getSymbolSource(
            'app/Services/UserService.php::UserService.authenticate#method',
            'local/test-repo'
        );

        expect($source)->not->toBeNull();
        expect($source)->toContain('function authenticate');
    });

    it('returns null for non-existent symbol', function (): void {
        $source = $this->service->getSymbolSource('nonexistent#method', 'local/test-repo');

        expect($source)->toBeNull();
    });

    it('returns null for non-existent repo', function (): void {
        $source = $this->service->getSymbolSource('any#method', 'local/nonexistent');

        expect($source)->toBeNull();
    });
});

describe('getSymbol', function (): void {
    it('returns symbol metadata by ID', function (): void {
        $symbol = $this->service->getSymbol(
            'app/Services/UserService.php::UserService#class',
            'local/test-repo'
        );

        expect($symbol)->not->toBeNull();
        expect($symbol['name'])->toBe('UserService');
        expect($symbol['kind'])->toBe('class');
        expect($symbol['line'])->toBe(10);
    });

    it('returns null for non-existent symbol', function (): void {
        $symbol = $this->service->getSymbol('nonexistent#class', 'local/test-repo');

        expect($symbol)->toBeNull();
    });
});

describe('getFileOutline', function (): void {
    it('returns hierarchical symbol tree for a file', function (): void {
        $outline = $this->service->getFileOutline(
            'app/Services/UserService.php',
            'local/test-repo'
        );

        expect($outline)->toHaveCount(1);
        expect($outline[0]['name'])->toBe('UserService');
        expect($outline[0]['kind'])->toBe('class');
        expect($outline[0])->toHaveKey('children');
        expect($outline[0]['children'])->toHaveCount(1);
        expect($outline[0]['children'][0]['name'])->toBe('authenticate');
    });

    it('returns empty array for non-existent file', function (): void {
        $outline = $this->service->getFileOutline('nonexistent.php', 'local/test-repo');

        expect($outline)->toBeEmpty();
    });

    it('returns flat list for files without hierarchy', function (): void {
        $outline = $this->service->getFileOutline(
            'app/Models/User.php',
            'local/test-repo'
        );

        expect($outline)->toHaveCount(1);
        expect($outline[0]['name'])->toBe('User');
        expect($outline[0])->not->toHaveKey('children');
    });
});

describe('detectChanges', function (): void {
    it('detects changed files by hash comparison', function (): void {
        $changes = $this->service->detectChanges([
            'app/Services/UserService.php' => 'modified-content',
            'app/Models/User.php' => 'user-model-content',
        ], 'local/test-repo');

        expect($changes['changed'])->toContain('app/Services/UserService.php');
        expect($changes['new'])->toBeEmpty();
        expect($changes['deleted'])->toBeEmpty();
    });

    it('detects new files', function (): void {
        $changes = $this->service->detectChanges([
            'app/Services/UserService.php' => 'user-service-content',
            'app/Models/User.php' => 'user-model-content',
            'app/Services/NewService.php' => 'new-content',
        ], 'local/test-repo');

        expect($changes['new'])->toContain('app/Services/NewService.php');
        expect($changes['changed'])->toBeEmpty();
    });

    it('detects deleted files', function (): void {
        $changes = $this->service->detectChanges([
            'app/Services/UserService.php' => 'user-service-content',
        ], 'local/test-repo');

        expect($changes['deleted'])->toContain('app/Models/User.php');
    });

    it('detects no changes when files unchanged', function (): void {
        $changes = $this->service->detectChanges([
            'app/Services/UserService.php' => 'user-service-content',
            'app/Models/User.php' => 'user-model-content',
        ], 'local/test-repo');

        expect($changes['changed'])->toBeEmpty();
        expect($changes['new'])->toBeEmpty();
        expect($changes['deleted'])->toBeEmpty();
    });
});

describe('listRepos', function (): void {
    it('lists indexed repositories', function (): void {
        $repos = $this->service->listRepos();

        expect($repos)->toHaveCount(1);
        expect($repos[0]['repo'])->toBe('local/test-repo');
        expect($repos[0]['symbol_count'])->toBe(3);
        expect($repos[0]['file_count'])->toBe(2);
    });

    it('returns empty array when no indexes exist', function (): void {
        $service = new SymbolIndexService($this->tempDir.'/empty');

        $repos = $service->listRepos();

        expect($repos)->toBeEmpty();
    });
});

describe('getSymbolSource edge cases', function (): void {
    it('returns null when content file does not exist', function (): void {
        // Delete the raw content file
        unlink($this->indexDir.'/local-test-repo/app/Services/UserService.php');

        $source = $this->service->getSymbolSource(
            'app/Services/UserService.php::UserService.authenticate#method',
            'local/test-repo'
        );

        expect($source)->toBeNull();
    });
});

describe('getSymbol edge cases', function (): void {
    it('returns null for non-existent repo', function (): void {
        $symbol = $this->service->getSymbol('any#method', 'local/nonexistent');

        expect($symbol)->toBeNull();
    });
});

describe('getFileOutline edge cases', function (): void {
    it('returns empty for non-existent repo', function (): void {
        $outline = $this->service->getFileOutline('any.php', 'local/nonexistent');

        expect($outline)->toBeEmpty();
    });
});

describe('indexFolder', function (): void {
    it('returns error for invalid path', function (): void {
        $result = $this->service->indexFolder('/nonexistent/path/to/folder');

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('Invalid path');
    });

    it('returns error for file path instead of directory', function (): void {
        $file = $this->tempDir.'/not-a-dir.txt';
        file_put_contents($file, 'content');

        $result = $this->service->indexFolder($file);

        expect($result['success'])->toBeFalse();
        expect($result['error'])->toContain('Invalid path');
    });
});

describe('path traversal protection', function (): void {
    it('returns null for path traversal attempts', function (): void {
        // Create a symbol with a path traversal attempt in the index
        $index = json_decode(
            file_get_contents($this->indexDir.'/local-test-repo.json'),
            true
        );
        $index['symbols'][] = [
            'id' => '../../etc/passwd::evil#function',
            'file' => '../../etc/passwd',
            'name' => 'evil',
            'qualified_name' => 'evil',
            'kind' => 'function',
            'language' => 'php',
            'signature' => 'function evil()',
            'docstring' => '',
            'summary' => '',
            'decorators' => [],
            'keywords' => [],
            'parent' => null,
            'line' => 1,
            'end_line' => 5,
            'byte_offset' => 0,
            'byte_length' => 100,
            'content_hash' => 'abc',
        ];
        file_put_contents(
            $this->indexDir.'/local-test-repo.json',
            json_encode($index)
        );

        $source = $this->service->getSymbolSource(
            '../../etc/passwd::evil#function',
            'local/test-repo'
        );

        expect($source)->toBeNull();
    });
});

describe('repo parsing', function (): void {
    it('handles single-segment repo names', function (): void {
        // Create an index for a single-segment repo name
        $index = [
            'repo' => 'local/myrepo',
            'owner' => 'local',
            'name' => 'myrepo',
            'indexed_at' => '2026-01-01',
            'index_version' => 2,
            'source_files' => [],
            'languages' => [],
            'symbols' => [
                [
                    'id' => 'test.php::hello#function',
                    'file' => 'test.php',
                    'name' => 'hello',
                    'qualified_name' => 'hello',
                    'kind' => 'function',
                    'language' => 'php',
                    'signature' => 'function hello()',
                    'docstring' => '',
                    'summary' => '',
                    'decorators' => [],
                    'keywords' => [],
                    'parent' => null,
                    'line' => 1,
                    'end_line' => 3,
                    'byte_offset' => 0,
                    'byte_length' => 50,
                    'content_hash' => 'xxx',
                ],
            ],
            'file_hashes' => [],
        ];
        file_put_contents(
            $this->indexDir.'/local-myrepo.json',
            json_encode($index)
        );

        // Search using single-segment name (triggers parseRepo fallback)
        $results = $this->service->searchSymbols('hello', 'myrepo');

        expect($results)->not->toBeEmpty();
        expect($results[0]['name'])->toBe('hello');
    });
});

describe('corrupt index handling', function (): void {
    it('returns empty results for corrupt JSON index', function (): void {
        file_put_contents(
            $this->indexDir.'/local-corrupt.json',
            '"just a string, not an object"'
        );

        $results = $this->service->searchSymbols('test', 'local/corrupt');

        expect($results)->toBeEmpty();
    });
});

describe('index version handling', function (): void {
    it('rejects indexes with future version numbers', function (): void {
        $futureIndex = [
            'repo' => 'local/future',
            'owner' => 'local',
            'name' => 'future',
            'indexed_at' => '2026-01-01',
            'index_version' => 99,
            'source_files' => [],
            'languages' => [],
            'symbols' => [],
            'file_hashes' => [],
        ];
        file_put_contents(
            $this->indexDir.'/local-future.json',
            json_encode($futureIndex)
        );

        $results = $this->service->searchSymbols('test', 'local/future');

        expect($results)->toBeEmpty();
    });
});

describe('listRepos edge cases', function (): void {
    it('skips invalid JSON files in index dir', function (): void {
        file_put_contents($this->indexDir.'/broken.json', 'not-valid-json{{{');

        $repos = $this->service->listRepos();

        // Should still return the valid repo, skipping the broken file
        $repoNames = array_column($repos, 'repo');
        expect($repoNames)->toContain('local/test-repo');
    });

    it('skips unreadable index files', function (): void {
        file_put_contents($this->indexDir.'/empty-array.json', '[]');

        $repos = $this->service->listRepos();

        $repoNames = array_column($repos, 'repo');
        expect($repoNames)->toContain('local/test-repo');
    });
});

describe('detectChanges edge cases', function (): void {
    it('treats all files as new when no prior index exists', function (): void {
        $changes = $this->service->detectChanges([
            'new_file.php' => 'content',
        ], 'local/nonexistent');

        // No prior index means file_hashes is empty, so everything is new
        expect($changes['new'])->toContain('new_file.php');
        expect($changes['changed'])->toBeEmpty();
        expect($changes['deleted'])->toBeEmpty();
    });
});
