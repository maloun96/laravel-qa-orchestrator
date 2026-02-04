<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Maloun\QAOrchestrator\Models\QAProcess;

class TestCasesGeneratedEvent
{
    use Dispatchable;

    public function __construct(
        public QAProcess $qaProcess,
    ) {}
}