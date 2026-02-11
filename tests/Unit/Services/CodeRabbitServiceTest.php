<?php

declare(strict_types=1);

use App\Services\CodeRabbitService;
use Symfony\Component\Process\Process;

/**
 * Create a CodeRabbitService with fake gh CLI responses.
 *
 * @param  list<array{success: bool, output: string}>  $responses  Ordered responses for each runGhCommand call
 */
function fakeCodeRabbitService(array $responses): CodeRabbitService
{
    return new class($responses) extends CodeRabbitService
    {
        /** @var list<array{success: bool, output: string}> */
        private array $responses;

        private int $callIndex = 0;

        /** @param list<array{success: bool, output: string}> $responses */
        public function __construct(array $responses)
        {
            parent::__construct();
            $this->responses = $responses;
        }

        protected function runGhCommand(array $command): Process
        {
            $response = $this->responses[$this->callIndex++] ?? ['success' => false, 'output' => ''];
            $process = Mockery::mock(Process::class);
            $process->shouldReceive('isSuccessful')->andReturn($response['success']);
            $process->shouldReceive('getOutput')->andReturn($response['output']);

            return $process;
        }
    };
}

describe('fetchReviewComments', function (): void {
    it('returns null when PR metadata fetch fails', function (): void {
        $service = fakeCodeRabbitService([
            ['success' => false, 'output' => ''],
        ]);

        expect($service->fetchReviewComments(1))->toBeNull();
    });

    it('returns null when PR metadata returns invalid JSON', function (): void {
        $service = fakeCodeRabbitService([
            ['success' => true, 'output' => 'not json'],
        ]);

        expect($service->fetchReviewComments(1))->toBeNull();
    });

    it('returns null when both comment fetches fail', function (): void {
        $service = fakeCodeRabbitService([
            ['success' => true, 'output' => json_encode(['title' => 'PR', 'url' => 'https://example.com/pr/1'])],
            ['success' => false, 'output' => ''],
            ['success' => false, 'output' => ''],
        ]);

        expect($service->fetchReviewComments(1))->toBeNull();
    });

    it('filters comments to coderabbit author only', function (): void {
        $service = fakeCodeRabbitService([
            ['success' => true, 'output' => json_encode(['title' => 'Test PR', 'url' => 'https://example.com/pr/1'])],
            ['success' => true, 'output' => json_encode([
                ['body' => 'LGTM', 'path' => null, 'author' => 'human-reviewer'],
                ['body' => 'Found a bug', 'path' => null, 'author' => 'coderabbitai[bot]'],
            ])],
            ['success' => true, 'output' => json_encode([
                ['body' => 'Nit: naming', 'path' => 'src/Foo.php', 'author' => 'coderabbitai[bot]'],
                ['body' => 'Looks good', 'path' => 'src/Bar.php', 'author' => 'other-bot'],
            ])],
        ]);

        $result = $service->fetchReviewComments(1);

        expect($result)->not->toBeNull();
        expect($result['pr_title'])->toBe('Test PR');
        expect($result['pr_url'])->toBe('https://example.com/pr/1');
        expect($result['comments'])->toHaveCount(2);
        expect($result['comments'][0]['body'])->toBe('Found a bug');
        expect($result['comments'][1]['body'])->toBe('Nit: naming');
    });

    it('returns empty comments when no coderabbit comments exist', function (): void {
        $service = fakeCodeRabbitService([
            ['success' => true, 'output' => json_encode(['title' => 'PR', 'url' => 'https://example.com/pr/1'])],
            ['success' => true, 'output' => json_encode([
                ['body' => 'LGTM', 'path' => null, 'author' => 'human'],
            ])],
            ['success' => true, 'output' => json_encode([])],
        ]);

        $result = $service->fetchReviewComments(1);

        expect($result['comments'])->toHaveCount(0);
    });

    it('merges issue and review comments', function (): void {
        $service = fakeCodeRabbitService([
            ['success' => true, 'output' => json_encode(['title' => 'PR', 'url' => 'https://example.com/pr/1'])],
            ['success' => true, 'output' => json_encode([
                ['body' => 'Issue comment', 'path' => null, 'author' => 'coderabbitai[bot]'],
            ])],
            ['success' => true, 'output' => json_encode([
                ['body' => 'Review comment', 'path' => 'file.php', 'author' => 'coderabbitai[bot]'],
            ])],
        ]);

        $result = $service->fetchReviewComments(1);

        expect($result['comments'])->toHaveCount(2);
    });

    it('handles partial comment fetch failure gracefully', function (): void {
        $service = fakeCodeRabbitService([
            ['success' => true, 'output' => json_encode(['title' => 'PR', 'url' => 'https://example.com/pr/1'])],
            ['success' => false, 'output' => ''],
            ['success' => true, 'output' => json_encode([
                ['body' => 'Review only', 'path' => 'file.php', 'author' => 'coderabbitai[bot]'],
            ])],
        ]);

        $result = $service->fetchReviewComments(1);

        expect($result)->not->toBeNull();
        expect($result['comments'])->toHaveCount(1);
    });

    it('handles invalid JSON in comment responses', function (): void {
        $service = fakeCodeRabbitService([
            ['success' => true, 'output' => json_encode(['title' => 'PR', 'url' => 'https://example.com/pr/1'])],
            ['success' => true, 'output' => 'broken json'],
            ['success' => true, 'output' => json_encode([
                ['body' => 'Valid comment', 'path' => null, 'author' => 'coderabbitai[bot]'],
            ])],
        ]);

        $result = $service->fetchReviewComments(1);

        expect($result['comments'])->toHaveCount(1);
    });
});

describe('parseFindings', function (): void {
    it('parses a single comment into a finding', function (): void {
        $service = new CodeRabbitService;

        $comments = [
            ['body' => 'Bug: This function has a missing validation that could fail', 'path' => 'src/Service.php', 'author' => 'coderabbitai[bot]'],
        ];

        $findings = $service->parseFindings($comments);

        expect($findings)->toHaveCount(1);
        expect($findings[0]['severity'])->toBe('high');
        expect($findings[0]['file'])->toBe('src/Service.php');
        expect($findings[0]['confidence'])->toBe(75);
    });

    it('splits multi-section comments into separate findings', function (): void {
        $service = new CodeRabbitService;

        $body = <<<'MD'
## Security vulnerability in query builder

SQL injection risk detected in raw query.

## Nit: formatting issue

Minor style inconsistency.
MD;

        $comments = [
            ['body' => $body, 'path' => 'src/Query.php', 'author' => 'coderabbitai[bot]'],
        ];

        $findings = $service->parseFindings($comments);

        expect($findings)->toHaveCount(2);
        expect($findings[0]['severity'])->toBe('critical');
        expect($findings[0]['title'])->toBe('Security vulnerability in query builder');
        expect($findings[1]['severity'])->toBe('low');
    });

    it('skips empty comment bodies', function (): void {
        $service = new CodeRabbitService;

        $comments = [
            ['body' => '', 'path' => null, 'author' => 'coderabbitai[bot]'],
            ['body' => '   ', 'path' => null, 'author' => 'coderabbitai[bot]'],
        ];

        $findings = $service->parseFindings($comments);

        expect($findings)->toHaveCount(0);
    });

    it('handles comments without file path', function (): void {
        $service = new CodeRabbitService;

        $comments = [
            ['body' => 'Consider simplifying this logic', 'path' => null, 'author' => 'coderabbitai[bot]'],
        ];

        $findings = $service->parseFindings($comments);

        expect($findings)->toHaveCount(1);
        expect($findings[0]['file'])->toBeNull();
    });

    it('detects critical severity from security keywords', function (): void {
        $service = new CodeRabbitService;

        $comments = [
            ['body' => 'This code has a security vulnerability that allows injection', 'path' => null, 'author' => 'coderabbitai[bot]'],
        ];

        $findings = $service->parseFindings($comments);

        expect($findings[0]['severity'])->toBe('critical');
        expect($findings[0]['confidence'])->toBe(85);
    });

    it('detects high severity from bug keywords', function (): void {
        $service = new CodeRabbitService;

        $comments = [
            ['body' => 'This will fail with an error when input is empty', 'path' => null, 'author' => 'coderabbitai[bot]'],
        ];

        $findings = $service->parseFindings($comments);

        expect($findings[0]['severity'])->toBe('high');
        expect($findings[0]['confidence'])->toBe(75);
    });

    it('detects medium severity from improvement keywords', function (): void {
        $service = new CodeRabbitService;

        $comments = [
            ['body' => 'Consider using a cleaner approach for readability', 'path' => null, 'author' => 'coderabbitai[bot]'],
        ];

        $findings = $service->parseFindings($comments);

        expect($findings[0]['severity'])->toBe('medium');
    });

    it('detects low severity from nit keywords', function (): void {
        $service = new CodeRabbitService;

        $comments = [
            ['body' => 'Nit: typo in variable naming convention', 'path' => null, 'author' => 'coderabbitai[bot]'],
        ];

        $findings = $service->parseFindings($comments);

        expect($findings[0]['severity'])->toBe('low');
        expect($findings[0]['confidence'])->toBe(45);
    });

    it('defaults to medium severity for unrecognized content', function (): void {
        $service = new CodeRabbitService;

        $comments = [
            ['body' => 'Interesting approach here, looks reasonable', 'path' => null, 'author' => 'coderabbitai[bot]'],
        ];

        $findings = $service->parseFindings($comments);

        expect($findings[0]['severity'])->toBe('medium');
    });

    it('truncates long titles', function (): void {
        $service = new CodeRabbitService;

        $longTitle = str_repeat('A very long title that should be truncated ', 10);
        $comments = [
            ['body' => $longTitle, 'path' => null, 'author' => 'coderabbitai[bot]'],
        ];

        $findings = $service->parseFindings($comments);

        expect(mb_strlen($findings[0]['title']))->toBeLessThanOrEqual(120);
    });
});

describe('filterBySeverity', function (): void {
    it('filters findings below minimum severity', function (): void {
        $service = new CodeRabbitService;

        $findings = [
            ['title' => 'Critical', 'content' => 'Critical issue', 'file' => null, 'severity' => 'critical', 'confidence' => 85],
            ['title' => 'High', 'content' => 'High issue', 'file' => null, 'severity' => 'high', 'confidence' => 75],
            ['title' => 'Medium', 'content' => 'Medium issue', 'file' => null, 'severity' => 'medium', 'confidence' => 60],
            ['title' => 'Low', 'content' => 'Low issue', 'file' => null, 'severity' => 'low', 'confidence' => 45],
        ];

        $filtered = $service->filterBySeverity($findings, 'high');

        expect($filtered)->toHaveCount(2);
        expect($filtered[0]['severity'])->toBe('critical');
        expect($filtered[1]['severity'])->toBe('high');
    });

    it('returns all findings when minimum is low', function (): void {
        $service = new CodeRabbitService;

        $findings = [
            ['title' => 'Critical', 'content' => 'Critical', 'file' => null, 'severity' => 'critical', 'confidence' => 85],
            ['title' => 'Low', 'content' => 'Low', 'file' => null, 'severity' => 'low', 'confidence' => 45],
        ];

        $filtered = $service->filterBySeverity($findings, 'low');

        expect($filtered)->toHaveCount(2);
    });

    it('returns only critical when minimum is critical', function (): void {
        $service = new CodeRabbitService;

        $findings = [
            ['title' => 'Critical', 'content' => 'Critical', 'file' => null, 'severity' => 'critical', 'confidence' => 85],
            ['title' => 'High', 'content' => 'High', 'file' => null, 'severity' => 'high', 'confidence' => 75],
            ['title' => 'Low', 'content' => 'Low', 'file' => null, 'severity' => 'low', 'confidence' => 45],
        ];

        $filtered = $service->filterBySeverity($findings, 'critical');

        expect($filtered)->toHaveCount(1);
        expect($filtered[0]['severity'])->toBe('critical');
    });

    it('returns empty array when no findings meet threshold', function (): void {
        $service = new CodeRabbitService;

        $findings = [
            ['title' => 'Low', 'content' => 'Low', 'file' => null, 'severity' => 'low', 'confidence' => 45],
        ];

        $filtered = $service->filterBySeverity($findings, 'critical');

        expect($filtered)->toHaveCount(0);
    });
});
