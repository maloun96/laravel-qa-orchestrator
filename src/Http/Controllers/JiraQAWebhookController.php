<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maloun\QAOrchestrator\Dto\JiraWebhookPayloadDto;
use Maloun\QAOrchestrator\Events\JiraReadyForQAEvent;

class JiraQAWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        Log::info('JiraQAWebhookController received', [
            'event' => $request->input('webhookEvent'),
            'issue' => $request->input('issue.key'),
        ]);

        $payload = JiraWebhookPayloadDto::fromWebhook($request->all());

        if (! $this->shouldProcess($payload)) {
            return response()->json(['status' => 'skipped']);
        }

        JiraReadyForQAEvent::dispatch($payload);

        return response()->json(['status' => 'accepted']);
    }

    private function shouldProcess(JiraWebhookPayloadDto $payload): bool
    {
        if (empty($payload->issueKey)) {
            return false;
        }

        $validEvents = [
            'jira:issue_updated',
            'jira:issue_created',
        ];

        if (! in_array($payload->webhookEvent, $validEvents)) {
            return false;
        }

        $targetStatus = config('qa-orchestrator.jira.qa_status', 'Ready for QA');

        return $payload->status === $targetStatus;
    }
}