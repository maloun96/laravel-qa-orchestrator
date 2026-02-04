<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maloun\QAOrchestrator\Actions\GenerateTestCasesAction;
use Maloun\QAOrchestrator\Enums\QAStatusEnum;
use Maloun\QAOrchestrator\Models\QAProcess;
use Maloun\QAOrchestrator\Services\SlackNotificationService;
use Throwable;

class GenerateTestCasesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 180;

    public function __construct(
        private readonly QAProcess $qaProcess,
    ) {}

    public function handle(GenerateTestCasesAction $action): void
    {
        $this->qaProcess->updateStatus(QAStatusEnum::GeneratingTestCases);

        $action->handle($this->qaProcess);

        GeneratePlaywrightCodeJob::dispatch($this->qaProcess);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateTestCasesJob failed', [
            'qa_process_id' => $this->qaProcess->id,
            'error' => $exception->getMessage(),
        ]);

        $this->qaProcess->markFailed('Test case generation failed: '.$exception->getMessage());

        app(SlackNotificationService::class)->notifyError(
            'Test Case Generation',
            $exception->getMessage(),
            $this->qaProcess
        );
    }
}