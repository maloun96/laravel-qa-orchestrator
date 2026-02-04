<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator;

use Illuminate\Support\ServiceProvider;
use Maloun\QAOrchestrator\Services\ClaudeService;
use Maloun\QAOrchestrator\Services\GitHubService;
use Maloun\QAOrchestrator\Services\JiraClient;

class QAOrchestratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/qa-orchestrator.php', 'qa-orchestrator');

        $this->app->singleton(JiraClient::class);
        $this->app->singleton(ClaudeService::class);
        $this->app->singleton(GitHubService::class);
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerRoutes();
        $this->registerViews();
        $this->registerMigrations();
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/qa-orchestrator.php' => config_path('qa-orchestrator.php'),
            ], 'qa-orchestrator-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'qa-orchestrator-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/qa-orchestrator'),
            ], 'qa-orchestrator-views');
        }
    }

    protected function registerRoutes(): void
    {
        if (config('qa-orchestrator.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'qa-orchestrator');
    }

    protected function registerMigrations(): void
    {
        if (config('qa-orchestrator.migrations.enabled', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }
}