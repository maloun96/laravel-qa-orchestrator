<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Enums;

enum TestCaseStatusEnum: string
{
    case Pending = 'pending';
    case Generated = 'generated';
    case Running = 'running';
    case Passed = 'passed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}