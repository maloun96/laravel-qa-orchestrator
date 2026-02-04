<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maloun\QAOrchestrator\Actions\CreateGitHubPrAction;
use Maloun\QAOrchestrator\Actions\GeneratePlaywrightCodeAction;
use Maloun\QAOrchestrator\Enums\QAStatusEnum;
use Maloun\QAOrchestrator\Models\QAProcess;
use Maloun\QAOrchestrator\Services\SlackNotificationService;
use Throwable;

class GeneratePlaywrightCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 300;

    public function __construct(
        private readonly QAProcess $qaProcess,
    ) {}

    public function handle(GeneratePlaywrightCodeAction $generateAction, CreateGitHubPrAction $prAction): void
    {
        $this->qaProcess->updateStatus(QAStatusEnum::GeneratingPlaywright);

        $generateAction->handle($this->qaProcess);

        $this->qaProcess->updateStatus(QAStatusEnum::CreatingPR);

        $prAction->handle($this->qaProcess);

        $this->qaProcess->updateStatus(QAStatusEnum::RunningTests);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GeneratePlaywrightCodeJob failed', [
            'qa_process_id' => $this->qaProcess->id,
            'error' => $exception->getMessage(),
        ]);

        $this->qaProcess->markFailed('Playwright generation failed: '.$exception->getMessage());

        app(SlackNotificationService::class)->notifyError(
            'Playwright Code Generation',
            $exception->getMessage(),
            $this->qaProcess
        );
    }
}