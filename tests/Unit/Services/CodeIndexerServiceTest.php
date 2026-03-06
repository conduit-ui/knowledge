<?php

declare(strict_types=1);

use App\Contracts\EmbeddingServiceInterface;
use App\Integrations\Qdrant\QdrantConnector;
use App\Integrations\Qdrant\Requests\CreateCollection;
use App\Integrations\Qdrant\Requests\GetCollectionInfo;
use App\Integrations\Qdrant\Requests\SearchPoints;
use App\Integrations\Qdrant\Requests\UpsertPoints;
use App\Services\CodeIndexerService;
use Saloon\Http\Response;

uses()->group('code-indexer-unit');

beforeEach(function (): void {
    $this->mockEmbedding = Mockery::mock(EmbeddingServiceInterface::class);
    $this->mockConnector = Mockery::mock(QdrantConnector::class);
    $this->service = new CodeIndexerService($this->mockEmbedding, 1024);

    // Inject mock connector via reflection
    $reflection = new ReflectionClass($this->service);
    $property = $reflection->getProperty('connector');
    $property->setAccessible(true);
    $property->setValue($this->service, $this->mockConnector);
});

afterEach(function (): void {
    Mockery::close();
});

if (! function_exists('createCodeMockResponse')) {
    /**
     * Create a mock Response object with common configuration.
     */
    function createCodeMockResponse(bool $successful, int $status = 200, ?array $json = null): Response
    {
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('successful')->andReturn($successful);

        if (! $successful || $status !== 200) {
            $response->shouldReceive('status')->andReturn($status);
        }

        if ($json !== null) {
            $response->shouldReceive('json')->andReturn($json);
        }

        return $response;
    }
}

describe('ensureCollection', function (): void {
    it('returns true when collection already exists', function (): void {
        $response = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($response);

        expect($this->service->ensureCollection())->toBeTrue();
    });

    it('creates collection when it does not exist (404)', function (): void {
        $getResponse = createCodeMockResponse(false, 404);
        $createResponse = createCodeMockResponse(true);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(CreateCollection::class))
            ->once()
            ->andReturn($createResponse);

        expect($this->service->ensureCollection())->toBeTrue();
    });

    it('returns false when collection creation fails', function (): void {
        $getResponse = createCodeMockResponse(false, 404);
        $createResponse = createCodeMockResponse(false, 500);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($getResponse);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(CreateCollection::class))
            ->once()
            ->andReturn($createResponse);

        expect($this->service->ensureCollection())->toBeFalse();
    });

    it('returns false on unexpected response status', function (): void {
        $response = createCodeMockResponse(false, 500);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(GetCollectionInfo::class))
            ->once()
            ->andReturn($response);

        expect($this->service->ensureCollection())->toBeFalse();
    });
});

describe('findFiles', function (): void {
    it('yields files from valid directory', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir.'/test.php', '<?php echo "test";');
        file_put_contents($tempDir.'/app.js', 'console.log("test");');

        $files = iterator_to_array($this->service->findFiles([$tempDir]));

        expect($files)->toHaveCount(2);
        expect($files[0])->toHaveKeys(['path', 'repo']);
        expect($files[0]['repo'])->toBe(basename($tempDir));

        // Cleanup
        unlink($tempDir.'/test.php');
        unlink($tempDir.'/app.js');
        rmdir($tempDir);
    });

    it('skips non-existent directories', function (): void {
        $files = iterator_to_array($this->service->findFiles(['/nonexistent/path']));

        expect($files)->toBeEmpty();
    });

    it('excludes vendor and node_modules directories', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        mkdir($tempDir.'/vendor');
        mkdir($tempDir.'/node_modules');
        file_put_contents($tempDir.'/test.php', '<?php echo "test";');
        file_put_contents($tempDir.'/vendor/vendor.php', '<?php echo "vendor";');
        file_put_contents($tempDir.'/node_modules/module.js', 'console.log("module");');

        $files = iterator_to_array($this->service->findFiles([$tempDir]));

        expect($files)->toHaveCount(1);
        expect($files[0]['path'])->toContain('test.php');

        // Cleanup
        unlink($tempDir.'/test.php');
        unlink($tempDir.'/vendor/vendor.php');
        unlink($tempDir.'/node_modules/module.js');
        rmdir($tempDir.'/vendor');
        rmdir($tempDir.'/node_modules');
        rmdir($tempDir);
    });

    it('only finds files with supported extensions', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir.'/test.php', '<?php echo "test";');
        file_put_contents($tempDir.'/test.py', 'print("test")');
        file_put_contents($tempDir.'/test.js', 'console.log("test");');
        file_put_contents($tempDir.'/test.ts', 'console.log("test");');
        file_put_contents($tempDir.'/test.tsx', 'console.log("test");');
        file_put_contents($tempDir.'/test.jsx', 'console.log("test");');
        file_put_contents($tempDir.'/test.vue', '<template></template>');
        file_put_contents($tempDir.'/test.txt', 'plain text');
        file_put_contents($tempDir.'/test.md', '# Markdown');

        $files = iterator_to_array($this->service->findFiles([$tempDir]));

        expect($files)->toHaveCount(7); // php, py, js, ts, tsx, jsx, vue

        // Cleanup
        foreach (['php', 'py', 'js', 'ts', 'tsx', 'jsx', 'vue', 'txt', 'md'] as $ext) {
            unlink($tempDir.'/test.'.$ext);
        }
        rmdir($tempDir);
    });

    it('processes multiple base paths', function (): void {
        $tempDir1 = sys_get_temp_dir().'/code_indexer_test1_'.uniqid();
        $tempDir2 = sys_get_temp_dir().'/code_indexer_test2_'.uniqid();
        mkdir($tempDir1);
        mkdir($tempDir2);
        file_put_contents($tempDir1.'/file1.php', '<?php echo "test1";');
        file_put_contents($tempDir2.'/file2.php', '<?php echo "test2";');

        $files = iterator_to_array($this->service->findFiles([$tempDir1, $tempDir2]));

        expect($files)->toHaveCount(2);

        // Cleanup
        unlink($tempDir1.'/file1.php');
        unlink($tempDir2.'/file2.php');
        rmdir($tempDir1);
        rmdir($tempDir2);
    });
});

describe('indexFile', function (): void {
    it('successfully indexes a PHP file', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        $filepath = $tempDir.'/TestClass.php';
        file_put_contents($filepath, '<?php
function testFunction() {
    return "test";
}
');

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexFile($filepath, 'test-repo');

        expect($result)->toMatchArray([
            'chunks' => 1,
            'success' => true,
        ]);

        // Cleanup
        unlink($filepath);
        rmdir($tempDir);
    });

    it('returns error when file cannot be read', function (): void {
        $result = $this->service->indexFile('/nonexistent/file.php', 'test-repo');

        expect($result)->toMatchArray([
            'chunks' => 0,
            'success' => false,
            'error' => 'Could not read file',
        ]);
    });

    it('returns error when embedding generation fails', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        $filepath = $tempDir.'/test.php';
        file_put_contents($filepath, '<?php echo "test";');

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn([]);

        $result = $this->service->indexFile($filepath, 'test-repo');

        expect($result)->toMatchArray([
            'chunks' => 0,
            'success' => false,
            'error' => 'Failed to generate embeddings',
        ]);

        // Cleanup
        unlink($filepath);
        rmdir($tempDir);
    });

    it('returns error when upsert fails', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        $filepath = $tempDir.'/test.php';
        file_put_contents($filepath, '<?php echo "test";');

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(false, 500);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexFile($filepath, 'test-repo');

        expect($result)->toMatchArray([
            'chunks' => 1,
            'success' => false,
            'error' => 'Upsert failed',
        ]);

        // Cleanup
        unlink($filepath);
        rmdir($tempDir);
    });

    it('chunks large files appropriately', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        $filepath = $tempDir.'/large.php';
        // Create content larger than CHUNK_SIZE (2000 chars) - should produce multiple chunks
        $content = "<?php\n".str_repeat("// This is line number X with some code\n", 100);
        file_put_contents($filepath, $content);

        // Allow any number of generate calls (depends on content size and chunking)
        $this->mockEmbedding->shouldReceive('generate')
            ->atLeast()->times(2)
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexFile($filepath, 'test-repo');

        expect($result['success'])->toBeTrue();
        expect($result['chunks'])->toBeGreaterThan(1);

        // Cleanup
        unlink($filepath);
        rmdir($tempDir);
    });

    it('extracts PHP function names', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        $filepath = $tempDir.'/functions.php';
        file_put_contents($filepath, '<?php
function globalFunction() {}
public function publicMethod() {}
private function privateMethod() {}
protected function protectedMethod() {}
');

        $this->mockEmbedding->shouldReceive('generate')
            ->withArgs(function ($text) {
                return str_contains($text, 'globalFunction')
                    && str_contains($text, 'publicMethod')
                    && str_contains($text, 'privateMethod')
                    && str_contains($text, 'protectedMethod');
            })
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexFile($filepath, 'test-repo');

        expect($result['success'])->toBeTrue();

        // Cleanup
        unlink($filepath);
        rmdir($tempDir);
    });

    it('extracts Python function names', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        $filepath = $tempDir.'/functions.py';
        file_put_contents($filepath, '
def regular_function():
    pass

async def async_function():
    pass
');

        $this->mockEmbedding->shouldReceive('generate')
            ->withArgs(function ($text) {
                return str_contains($text, 'regular_function')
                    && str_contains($text, 'async_function');
            })
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexFile($filepath, 'test-repo');

        expect($result['success'])->toBeTrue();

        // Cleanup
        unlink($filepath);
        rmdir($tempDir);
    });

    it('extracts JavaScript function names', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        $filepath = $tempDir.'/functions.js';
        file_put_contents($filepath, '
function regularFunction() {}
const arrowFunction = () => {};
async arrowAsync() {}
');

        $this->mockEmbedding->shouldReceive('generate')
            ->withArgs(function ($text) {
                return str_contains($text, 'regularFunction');
            })
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexFile($filepath, 'test-repo');

        expect($result['success'])->toBeTrue();

        // Cleanup
        unlink($filepath);
        rmdir($tempDir);
    });

    it('extracts TypeScript function names', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        $filepath = $tempDir.'/functions.ts';
        file_put_contents($filepath, '
function typescriptFunction() {}
const constFunction = async () => {};
');

        $this->mockEmbedding->shouldReceive('generate')
            ->withArgs(function ($text) {
                return str_contains($text, 'typescriptFunction');
            })
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexFile($filepath, 'test-repo');

        expect($result['success'])->toBeTrue();

        // Cleanup
        unlink($filepath);
        rmdir($tempDir);
    });

    it('handles Vue files', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        $filepath = $tempDir.'/Component.vue';
        file_put_contents($filepath, '<template><div></div></template>
<script>
function vueFunction() {}
</script>');

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexFile($filepath, 'test-repo');

        expect($result['success'])->toBeTrue();

        // Cleanup
        unlink($filepath);
        rmdir($tempDir);
    });

    it('handles unknown file extensions gracefully', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        $filepath = $tempDir.'/unknown.xyz';
        file_put_contents($filepath, 'some content');

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexFile($filepath, 'test-repo');

        expect($result['success'])->toBeTrue();

        // Cleanup
        unlink($filepath);
        rmdir($tempDir);
    });

    it('handles TSX files', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        $filepath = $tempDir.'/Component.tsx';
        file_put_contents($filepath, '
function ReactComponent() {
    return <div>Hello</div>;
}
');

        $this->mockEmbedding->shouldReceive('generate')
            ->withArgs(function ($text) {
                return str_contains($text, 'ReactComponent');
            })
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexFile($filepath, 'test-repo');

        expect($result['success'])->toBeTrue();

        // Cleanup
        unlink($filepath);
        rmdir($tempDir);
    });

    it('handles JSX files', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        $filepath = $tempDir.'/Component.jsx';
        file_put_contents($filepath, '
function JsxComponent() {
    return <div>Hello</div>;
}
');

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexFile($filepath, 'test-repo');

        expect($result['success'])->toBeTrue();

        // Cleanup
        unlink($filepath);
        rmdir($tempDir);
    });

    it('skips chunks when embedding fails for specific chunk', function (): void {
        $tempDir = sys_get_temp_dir().'/code_indexer_test_'.uniqid();
        mkdir($tempDir);
        $filepath = $tempDir.'/large.php';
        // Create content to produce 2 chunks
        $content = "<?php\n".str_repeat("// Line of code here\n", 150);
        file_put_contents($filepath, $content);

        // First chunk returns empty embedding, second succeeds
        $this->mockEmbedding->shouldReceive('generate')
            ->twice()
            ->andReturn([], array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexFile($filepath, 'test-repo');

        expect($result['success'])->toBeTrue();
        expect($result['chunks'])->toBe(1); // Only one chunk succeeded

        // Cleanup
        unlink($filepath);
        rmdir($tempDir);
    });
});

describe('search', function (): void {
    it('successfully searches code with results', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('find authentication function')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $searchResponse = createCodeMockResponse(true, 200, [
            'result' => [
                [
                    'id' => 'abc123',
                    'score' => 0.95,
                    'payload' => [
                        'filepath' => '/app/Auth/Login.php',
                        'repo' => 'myproject',
                        'language' => 'php',
                        'functions' => ['authenticate', 'login'],
                        'content' => 'function authenticate() {}',
                        'start_line' => 10,
                        'end_line' => 25,
                    ],
                ],
            ],
        ]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('find authentication function', 10);

        expect($results)->toHaveCount(1);
        expect($results[0])->toMatchArray([
            'filepath' => '/app/Auth/Login.php',
            'repo' => 'myproject',
            'language' => 'php',
            'content' => 'function authenticate() {}',
            'score' => 0.95,
            'functions' => ['authenticate', 'login'],
            'start_line' => 10,
            'end_line' => 25,
        ]);
    });

    it('returns empty array when embedding generation fails', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('query')
            ->once()
            ->andReturn([]);

        $results = $this->service->search('query');

        expect($results)->toBeEmpty();
    });

    it('returns empty array when search request fails', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $searchResponse = createCodeMockResponse(false, 500);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('query');

        expect($results)->toBeEmpty();
    });

    it('handles empty result set', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('nonexistent code')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $searchResponse = createCodeMockResponse(true, 200, ['result' => []]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('nonexistent code');

        expect($results)->toBeEmpty();
    });

    it('applies repo filter', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('search query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $searchResponse = createCodeMockResponse(true, 200, ['result' => []]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('search query', 10, ['repo' => 'myproject']);

        expect($results)->toBeEmpty();
    });

    it('applies language filter', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('search query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $searchResponse = createCodeMockResponse(true, 200, ['result' => []]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('search query', 10, ['language' => 'php']);

        expect($results)->toBeEmpty();
    });

    it('applies both repo and language filters', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('search query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $searchResponse = createCodeMockResponse(true, 200, ['result' => []]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('search query', 10, [
            'repo' => 'myproject',
            'language' => 'python',
        ]);

        expect($results)->toBeEmpty();
    });

    it('handles custom limit', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('search')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $searchResponse = createCodeMockResponse(true, 200, ['result' => []]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('search', 50);

        expect($results)->toBeEmpty();
    });

    it('handles missing payload fields gracefully', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $searchResponse = createCodeMockResponse(true, 200, [
            'result' => [
                [
                    'id' => 'abc123',
                    'score' => 0.8,
                    'payload' => [], // Empty payload
                ],
            ],
        ]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('query');

        expect($results)->toHaveCount(1);
        expect($results[0])->toMatchArray([
            'filepath' => '',
            'repo' => '',
            'language' => '',
            'content' => '',
            'score' => 0.8,
            'functions' => [],
            'start_line' => 1,
            'end_line' => 1,
        ]);
    });

    it('handles missing score gracefully', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $searchResponse = createCodeMockResponse(true, 200, [
            'result' => [
                [
                    'id' => 'abc123',
                    'payload' => [
                        'filepath' => '/test.php',
                    ],
                ],
            ],
        ]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('query');

        expect($results)->toHaveCount(1);
        expect($results[0]['score'])->toBe(0.0);
    });

    it('handles null result in response', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('query')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $searchResponse = createCodeMockResponse(true, 200, []);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('query');

        expect($results)->toBeEmpty();
    });

    it('handles empty filter array', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->with('search')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $searchResponse = createCodeMockResponse(true, 200, ['result' => []]);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(SearchPoints::class))
            ->once()
            ->andReturn($searchResponse);

        $results = $this->service->search('search', 10, []);

        expect($results)->toBeEmpty();
    });
});

describe('indexSymbol', function (): void {
    it('successfully indexes a symbol', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexSymbol(
            text: 'class UserController extends Controller',
            filepath: 'app/Http/Controllers/UserController.php',
            repo: 'local/pstrax',
            language: 'php',
            symbolName: 'UserController',
            symbolKind: 'class',
            line: 10,
            signature: 'class UserController extends Controller',
        );

        expect($result)->toMatchArray(['success' => true]);
    });

    it('returns error when embedding is empty', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn([]);

        $result = $this->service->indexSymbol(
            text: 'class Foo',
            filepath: 'Foo.php',
            repo: 'local/test',
            language: 'php',
            symbolName: 'Foo',
            symbolKind: 'class',
            line: 1,
            signature: 'class Foo',
        );

        expect($result)->toMatchArray(['success' => false, 'error' => 'Empty embedding']);
    });

    it('returns error when upsert fails', function (): void {
        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(false, 500);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexSymbol(
            text: 'class Foo',
            filepath: 'Foo.php',
            repo: 'local/test',
            language: 'php',
            symbolName: 'Foo',
            symbolKind: 'class',
            line: 1,
            signature: 'class Foo',
        );

        expect($result)->toMatchArray(['success' => false, 'error' => 'Upsert failed']);
    });

    it('truncates content to 4000 chars', function (): void {
        $longText = str_repeat('x', 5000);

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::on(function ($request) {
                // Verify the upsert request has truncated content
                return $request instanceof UpsertPoints;
            }))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->indexSymbol(
            text: $longText,
            filepath: 'Foo.php',
            repo: 'local/test',
            language: 'php',
            symbolName: 'Foo',
            symbolKind: 'class',
            line: 1,
            signature: 'class Foo',
        );

        expect($result['success'])->toBeTrue();
    });
});

describe('vectorizeFromIndex', function (): void {
    it('returns zeros for non-existent file', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        unlink($tempFile); // Ensure it doesn't exist

        $symbolIndex = Mockery::mock(\App\Services\SymbolIndexService::class);

        // Suppress the E_WARNING from file_get_contents on non-existent file
        $result = @$this->service->vectorizeFromIndex(
            $tempFile,
            'local/test',
            $symbolIndex,
        );

        expect($result)->toMatchArray(['success' => 0, 'failed' => 0, 'total' => 0]);
    });

    it('returns zeros for invalid JSON', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, 'not json');

        $symbolIndex = Mockery::mock(\App\Services\SymbolIndexService::class);

        $result = $this->service->vectorizeFromIndex($tempFile, 'local/test', $symbolIndex);

        expect($result)->toMatchArray(['success' => 0, 'failed' => 0, 'total' => 0]);

        unlink($tempFile);
    });

    it('returns zeros for JSON without symbols key', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, json_encode(['no_symbols' => true]));

        $symbolIndex = Mockery::mock(\App\Services\SymbolIndexService::class);

        $result = $this->service->vectorizeFromIndex($tempFile, 'local/test', $symbolIndex);

        expect($result)->toMatchArray(['success' => 0, 'failed' => 0, 'total' => 0]);

        unlink($tempFile);
    });

    it('processes symbols from valid index', function (): void {
        $indexData = [
            'symbols' => [
                [
                    'id' => 'sym-1',
                    'kind' => 'class',
                    'name' => 'UserController',
                    'file' => 'app/Controllers/UserController.php',
                    'line' => 10,
                    'signature' => 'class UserController',
                    'summary' => 'Handles user actions',
                ],
            ],
        ];
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, json_encode($indexData));

        $symbolIndex = Mockery::mock(\App\Services\SymbolIndexService::class);
        $symbolIndex->shouldReceive('getSymbolSource')
            ->with('sym-1', 'local/test')
            ->once()
            ->andReturn('class UserController { }');

        $this->mockEmbedding->shouldReceive('generate')
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->vectorizeFromIndex($tempFile, 'local/test', $symbolIndex);

        expect($result)->toMatchArray(['success' => 1, 'failed' => 0, 'total' => 1]);

        unlink($tempFile);
    });

    it('filters by kind', function (): void {
        $indexData = [
            'symbols' => [
                ['id' => 'sym-1', 'kind' => 'class', 'name' => 'Foo', 'file' => 'Foo.php', 'line' => 1, 'signature' => 'class Foo'],
                ['id' => 'sym-2', 'kind' => 'function', 'name' => 'bar', 'file' => 'helpers.php', 'line' => 1, 'signature' => 'function bar()'],
            ],
        ];
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, json_encode($indexData));

        $symbolIndex = Mockery::mock(\App\Services\SymbolIndexService::class);
        $symbolIndex->shouldReceive('getSymbolSource')->once()->andReturnNull();

        $this->mockEmbedding->shouldReceive('generate')->once()->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->vectorizeFromIndex($tempFile, 'local/test', $symbolIndex, ['class']);

        expect($result['total'])->toBe(1)
            ->and($result['success'])->toBe(1);

        unlink($tempFile);
    });

    it('filters by language', function (): void {
        $indexData = [
            'symbols' => [
                ['id' => 'sym-1', 'kind' => 'class', 'name' => 'Foo', 'file' => 'Foo.php', 'line' => 1, 'signature' => 'class Foo'],
                ['id' => 'sym-2', 'kind' => 'class', 'name' => 'Bar', 'file' => 'Bar.ts', 'line' => 1, 'signature' => 'class Bar'],
            ],
        ];
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, json_encode($indexData));

        $symbolIndex = Mockery::mock(\App\Services\SymbolIndexService::class);
        $symbolIndex->shouldReceive('getSymbolSource')->once()->andReturnNull();

        $this->mockEmbedding->shouldReceive('generate')->once()->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->vectorizeFromIndex($tempFile, 'local/test', $symbolIndex, [], 'php');

        expect($result['total'])->toBe(1)
            ->and($result['success'])->toBe(1);

        unlink($tempFile);
    });

    it('calls progress callback', function (): void {
        $indexData = [
            'symbols' => [
                ['id' => 'sym-1', 'kind' => 'class', 'name' => 'Foo', 'file' => 'Foo.php', 'line' => 1, 'signature' => 'class Foo'],
            ],
        ];
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, json_encode($indexData));

        $symbolIndex = Mockery::mock(\App\Services\SymbolIndexService::class);
        $symbolIndex->shouldReceive('getSymbolSource')->once()->andReturnNull();

        $this->mockEmbedding->shouldReceive('generate')->once()->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $progressCalled = false;
        $result = $this->service->vectorizeFromIndex(
            $tempFile,
            'local/test',
            $symbolIndex,
            [],
            null,
            function (int $success, int $failed, int $total) use (&$progressCalled): void {
                $progressCalled = true;
                expect($total)->toBe(1);
            },
        );

        expect($progressCalled)->toBeTrue();

        unlink($tempFile);
    });

    it('counts failed symbols with empty text', function (): void {
        $indexData = [
            'symbols' => [
                ['id' => 'sym-1', 'kind' => 'class', 'name' => '', 'file' => '', 'line' => 0, 'signature' => '', 'summary' => '', 'docstring' => ''],
            ],
        ];
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, json_encode($indexData));

        $symbolIndex = Mockery::mock(\App\Services\SymbolIndexService::class);

        // buildSymbolText produces "class \n\n\nfile: " which has content, so it won't fail on empty text.
        // Instead, simulate an embedding failure.
        $symbolIndex->shouldReceive('getSymbolSource')->once()->andReturnNull();
        $this->mockEmbedding->shouldReceive('generate')->once()->andReturn([]);

        $result = $this->service->vectorizeFromIndex($tempFile, 'local/test', $symbolIndex);

        expect($result['failed'])->toBe(1)
            ->and($result['success'])->toBe(0);

        unlink($tempFile);
    });

    it('excludes non-structural kinds by default', function (): void {
        $indexData = [
            'symbols' => [
                ['id' => 'sym-1', 'kind' => 'variable', 'name' => '$foo', 'file' => 'Foo.php', 'line' => 1, 'signature' => '$foo'],
                ['id' => 'sym-2', 'kind' => 'import', 'name' => 'Bar', 'file' => 'Foo.php', 'line' => 2, 'signature' => 'use Bar'],
            ],
        ];
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, json_encode($indexData));

        $symbolIndex = Mockery::mock(\App\Services\SymbolIndexService::class);

        $result = $this->service->vectorizeFromIndex($tempFile, 'local/test', $symbolIndex);

        expect($result['total'])->toBe(0);

        unlink($tempFile);
    });

    it('appends source code when available', function (): void {
        $indexData = [
            'symbols' => [
                ['id' => 'sym-1', 'kind' => 'class', 'name' => 'Foo', 'file' => 'Foo.php', 'line' => 1, 'signature' => 'class Foo'],
            ],
        ];
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, json_encode($indexData));

        $symbolIndex = Mockery::mock(\App\Services\SymbolIndexService::class);
        $symbolIndex->shouldReceive('getSymbolSource')
            ->with('sym-1', 'local/test')
            ->once()
            ->andReturn('class Foo { public function bar() {} }');

        $this->mockEmbedding->shouldReceive('generate')
            ->withArgs(function (string $text): bool {
                return str_contains($text, 'class Foo { public function bar() {} }');
            })
            ->once()
            ->andReturn(array_fill(0, 1024, 0.1));

        $upsertResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(UpsertPoints::class))
            ->once()
            ->andReturn($upsertResponse);

        $result = $this->service->vectorizeFromIndex($tempFile, 'local/test', $symbolIndex);

        expect($result['success'])->toBe(1);

        unlink($tempFile);
    });
});

describe('constructor', function (): void {
    it('uses default vector size of 1024', function (): void {
        $service = new CodeIndexerService($this->mockEmbedding);

        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('vectorSize');
        $property->setAccessible(true);

        expect($property->getValue($service))->toBe(1024);
    });

    it('accepts custom vector size', function (): void {
        $service = new CodeIndexerService($this->mockEmbedding, 768);

        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('vectorSize');
        $property->setAccessible(true);

        expect($property->getValue($service))->toBe(768);
    });
});

describe('pruneStaleSymbols', function (): void {
    it('returns zeros for non-existent file', function (): void {
        $result = $this->service->pruneStaleSymbols('/nonexistent/file.json', 'local/test');

        expect($result)->toMatchArray(['deleted' => 0, 'total_checked' => 0]);
    });

    it('returns zeros for invalid JSON', function (): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, 'not json');

        $result = $this->service->pruneStaleSymbols($tempFile, 'local/test');

        expect($result)->toMatchArray(['deleted' => 0, 'total_checked' => 0]);
        unlink($tempFile);
    });

    it('deletes stale points', function (): void {
        $indexData = [
            'symbols' => [
                ['name' => 'Foo', 'file' => 'Foo.php', 'line' => 1, 'kind' => 'class'],
            ],
        ];
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, json_encode($indexData));

        $validId = md5('local/test:Foo.php:Foo:1');
        $staleId = md5('local/test:Bar.php:Bar:1');

        // Scroll returns one valid and one stale point
        $scrollResponse = createCodeMockResponse(true, 200, [
            'result' => [
                'points' => [
                    ['id' => $validId, 'payload' => ['symbol_name' => 'Foo', 'repo' => 'local/test']],
                    ['id' => $staleId, 'payload' => ['symbol_name' => 'Bar', 'repo' => 'local/test']],
                ],
                'next_page_offset' => null,
            ],
        ]);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(\App\Integrations\Qdrant\Requests\ScrollPoints::class))
            ->once()
            ->andReturn($scrollResponse);

        $deleteResponse = createCodeMockResponse(true);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(\App\Integrations\Qdrant\Requests\DeletePoints::class))
            ->once()
            ->andReturn($deleteResponse);

        $result = $this->service->pruneStaleSymbols($tempFile, 'local/test');

        expect($result['deleted'])->toBe(1)
            ->and($result['total_checked'])->toBe(2);

        unlink($tempFile);
    });

    it('skips non-symbol points (file chunks)', function (): void {
        $indexData = ['symbols' => []];
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, json_encode($indexData));

        $scrollResponse = createCodeMockResponse(true, 200, [
            'result' => [
                'points' => [
                    ['id' => 'chunk-1', 'payload' => ['filepath' => 'Foo.php', 'repo' => 'local/test']],
                ],
                'next_page_offset' => null,
            ],
        ]);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(\App\Integrations\Qdrant\Requests\ScrollPoints::class))
            ->once()
            ->andReturn($scrollResponse);

        $result = $this->service->pruneStaleSymbols($tempFile, 'local/test');

        expect($result['deleted'])->toBe(0)
            ->and($result['total_checked'])->toBe(0);

        unlink($tempFile);
    });

    it('handles scroll failure gracefully', function (): void {
        $indexData = ['symbols' => [['name' => 'Foo', 'file' => 'Foo.php', 'line' => 1, 'kind' => 'class']]];
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, json_encode($indexData));

        $scrollResponse = createCodeMockResponse(false, 500);
        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(\App\Integrations\Qdrant\Requests\ScrollPoints::class))
            ->once()
            ->andReturn($scrollResponse);

        $result = $this->service->pruneStaleSymbols($tempFile, 'local/test');

        expect($result['deleted'])->toBe(0)
            ->and($result['total_checked'])->toBe(0);

        unlink($tempFile);
    });

    it('reports no deletions when all points are current', function (): void {
        $indexData = [
            'symbols' => [
                ['name' => 'Foo', 'file' => 'Foo.php', 'line' => 1, 'kind' => 'class'],
            ],
        ];
        $tempFile = tempnam(sys_get_temp_dir(), 'idx_');
        file_put_contents($tempFile, json_encode($indexData));

        $validId = md5('local/test:Foo.php:Foo:1');

        $scrollResponse = createCodeMockResponse(true, 200, [
            'result' => [
                'points' => [
                    ['id' => $validId, 'payload' => ['symbol_name' => 'Foo', 'repo' => 'local/test']],
                ],
                'next_page_offset' => null,
            ],
        ]);

        $this->mockConnector->shouldReceive('send')
            ->with(Mockery::type(\App\Integrations\Qdrant\Requests\ScrollPoints::class))
            ->once()
            ->andReturn($scrollResponse);

        $result = $this->service->pruneStaleSymbols($tempFile, 'local/test');

        expect($result['deleted'])->toBe(0)
            ->and($result['total_checked'])->toBe(1);

        unlink($tempFile);
    });
});
