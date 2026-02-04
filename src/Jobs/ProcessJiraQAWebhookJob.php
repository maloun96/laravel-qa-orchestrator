<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maloun\QAOrchestrator\Dto\JiraWebhookPayloadDto;
use Maloun\QAOrchestrator\Enums\QAStatusEnum;
use Maloun\QAOrchestrator\Models\QAProcess;
use Maloun\QAOrchestrator\Services\JiraClient;
use Maloun\QAOrchestrator\Services\SlackNotificationService;
use Throwable;

class ProcessJiraQAWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly JiraWebhookPayloadDto $payload,
    ) {}

    public function handle(JiraClient $jiraClient): void
    {
        $qaProcess = QAProcess::query()
            ->firstOrCreate(
                ['jira_issue_key' => $this->payload->issueKey],
                [
                    'status' => QAStatusEnum::Pending,
                    'jira_project_key' => $this->payload->projectKey,
                    'jira_issue_url' => $this->payload->issueUrl,
                    'jira_summary' => $this->payload->summary,
                    'jira_description' => $this->payload->description,
                ]
            );

        if (! $qaProcess->isPending()) {
            Log::info('QAProcess already in progress', [
                'issue_key' => $this->payload->issueKey,
                'status' => $qaProcess->status->value,
            ]);

            return;
        }

        $ticket = $jiraClient->getTicket($this->payload->issueKey);

        $qaProcess->update([
            'jira_summary' => $ticket->summary,
            'jira_description' => $ticket->description,
            'jira_acceptance_criteria' => $ticket->acceptanceCriteria ? [$ticket->acceptanceCriteria] : null,
        ]);

        GenerateTestCasesJob::dispatch($qaProcess);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ProcessJiraQAWebhookJob failed', [
            'issue_key' => $this->payload->issueKey,
            'error' => $exception->getMessage(),
        ]);

        app(SlackNotificationService::class)->notifyError(
            'Jira Webhook Processing',
            "Issue: {$this->payload->issueKey}\n{$exception->getMessage()}"
        );
    }
}