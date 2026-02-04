<?php

use Illuminate\Support\Facades\Route;
use Maloun\QAOrchestrator\Http\Controllers\GitHubQAWebhookController;
use Maloun\QAOrchestrator\Http\Controllers\JiraQAWebhookController;

Route::group([
    'prefix' => config('qa-orchestrator.routes.prefix', 'api/qa'),
    'middleware' => config('qa-orchestrator.routes.middleware', ['api']),
], function () {
    Route::post('/jira-webhook', JiraQAWebhookController::class)->name('qa-orchestrator.jira-webhook');
    Route::post('/github-webhook', GitHubQAWebhookController::class)->name('qa-orchestrator.github-webhook');
});