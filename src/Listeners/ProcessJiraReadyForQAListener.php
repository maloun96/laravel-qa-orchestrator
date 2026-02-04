<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Listeners;

use Maloun\QAOrchestrator\Events\JiraReadyForQAEvent;
use Maloun\QAOrchestrator\Jobs\ProcessJiraQAWebhookJob;

class ProcessJiraReadyForQAListener
{
    public function handle(JiraReadyForQAEvent $event): void
    {
        ProcessJiraQAWebhookJob::dispatch($event->payload);
    }
}