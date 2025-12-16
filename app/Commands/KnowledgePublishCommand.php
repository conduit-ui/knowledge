<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\StaticSitePublisher;
use LaravelZero\Framework\Commands\Command;

class KnowledgePublishCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'knowledge:publish
                            {--site=./public : Output directory for static site}';

    /**
     * @var string
     */
    protected $description = 'Publish knowledge base as a static HTML site';

    public function handle(StaticSitePublisher $publisher): int
    {
        /** @var string $outputDir */
        $outputDir = $this->option('site') ?? './public';

        $this->info("Publishing static site to: {$outputDir}");

        try {
            $publisher->publish($outputDir);

            $this->newLine();
            $this->info('Static site published successfully!');
            $this->line("Open {$outputDir}/index.html in your browser to view.");

            return self::SUCCESS;
        // @codeCoverageIgnoreStart
        } catch (\Exception $e) {
            $this->error("Failed to publish site: {$e->getMessage()}");

            return self::FAILURE;
        }
        // @codeCoverageIgnoreEnd
    }
}
