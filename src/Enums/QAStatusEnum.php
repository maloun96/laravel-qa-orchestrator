<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Enums;

enum QAStatusEnum: string
{
    case Pending = 'pending';
    case GeneratingTestCases = 'generating_test_cases';
    case GeneratingPlaywright = 'generating_playwright';
    case CreatingPR = 'creating_pr';
    case RunningTests = 'running_tests';
    case AnalyzingResults = 'analyzing_results';
    case Completed = 'completed';
    case Failed = 'failed';
}