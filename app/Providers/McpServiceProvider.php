<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Console\Commands\StartCommand;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Registrar;

/**
 * Slim MCP provider for Laravel Zero.
 *
 * The default McpServiceProvider requires illuminate/routing (Route facade),
 * which Laravel Zero doesn't include. This provider registers only what's
 * needed for Mcp::local() stdio transport.
 */
class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registrar::class, fn (): Registrar => new Registrar);

        $this->mergeConfigFrom(
            base_path('vendor/laravel/mcp/config/mcp.php'),
            'mcp'
        );
    }

    public function boot(): void
    {
        $this->registerContainerCallbacks();
        $this->loadRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([StartCommand::class]);
        }
    }

    private function registerContainerCallbacks(): void
    {
        $this->app->resolving(Request::class, function (Request $request, $app): void {
            if ($app->bound('mcp.request')) {
                /** @var Request $currentRequest */
                $currentRequest = $app->make('mcp.request');

                $request->setArguments($currentRequest->all());
                $request->setSessionId($currentRequest->sessionId());
                $request->setMeta($currentRequest->meta());
            }
        });
    }

    private function loadRoutes(): void
    {
        $path = base_path('routes/ai.php');

        if (file_exists($path)) {
            require $path;
        }
    }
}
