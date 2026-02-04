<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maloun\QAOrchestrator\Events\TestsCompletedEvent;
use Maloun\QAOrchestrator\Models\QAProcess;

class GitHubQAWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        Log::info('GitHubQAWebhookController received', $request->all());

        $action = $request->input('action');
        $workflowRun = $request->input('workflow_run');

        if ($action !== 'completed' || ! $workflowRun) {
            return response()->json(['status' => 'skipped']);
        }

        $runId = $workflowRun['id'];
        $conclusion = $workflowRun['conclusion'];
        $headBranch = $workflowRun['head_branch'];

        $qaProcess = QAProcess::query()
            ->where('workflow_run_id', $runId)
            ->orWhere('github_branch', $headBranch)
            ->first();

        if (! $qaProcess) {
            Log::warning('GitHubQAWebhookController: No QAProcess found', [
                'run_id' => $runId,
                'branch' => $headBranch,
            ]);

            return response()->json(['status' => 'no_process_found']);
        }

        TestsCompletedEvent::dispatch($qaProcess, $runId, $conclusion);

        return response()->json(['status' => 'accepted']);
    }
}