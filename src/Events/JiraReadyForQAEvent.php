<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Maloun\QAOrchestrator\Dto\JiraWebhookPayloadDto;

class JiraReadyForQAEvent
{
    use Dispatchable;

    public function __construct(
        public JiraWebhookPayloadDto $payload,
    ) {}
}