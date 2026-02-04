<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Maloun\QAOrchestrator\Models\QAProcess;

class TestsCompletedEvent
{
    use Dispatchable;

    public function __construct(
        public QAProcess $qaProcess,
        public int $workflowRunId,
        public string $conclusion,
    ) {}
}