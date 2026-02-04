<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Listeners;

use Maloun\QAOrchestrator\Events\TestsCompletedEvent;
use Maloun\QAOrchestrator\Jobs\AnalyzeTestResultsJob;

class AnalyzeTestsCompletedListener
{
    public function handle(TestsCompletedEvent $event): void
    {
        AnalyzeTestResultsJob::dispatch(
            $event->qaProcess,
            $event->workflowRunId,
            $event->conclusion,
        );
    }
}