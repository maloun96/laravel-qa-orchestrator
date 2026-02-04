<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maloun\QAOrchestrator\Actions\AnalyzeTestResultsAction;
use Maloun\QAOrchestrator\Actions\UpdateJiraWithResultsAction;
use Maloun\QAOrchestrator\Enums\QAStatusEnum;
use Maloun\QAOrchestrator\Models\QAProcess;
use Throwable;

class AnalyzeTestResultsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 180;

    public function __construct(
        private readonly QAProcess $qaProcess,
        private readonly int $workflowRunId,
        private readonly string $conclusion,
    ) {}

    public function handle(AnalyzeTestResultsAction $analyzeAction, UpdateJiraWithResultsAction $jiraAction): void
    {
        $this->qaProcess->updateStatus(QAStatusEnum::AnalyzingResults);

        $analysis = $analyzeAction->handle($this->qaProcess, $this->workflowRunId, $this->conclusion);

        $jiraAction->handle($this->qaProcess, $analysis);

        $this->qaProcess->updateStatus(QAStatusEnum::Completed);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('AnalyzeTestResultsJob failed', [
            'qa_process_id' => $this->qaProcess->id,
            'error' => $exception->getMessage(),
        ]);

        $this->qaProcess->markFailed('Result analysis failed: '.$exception->getMessage());
    }
}