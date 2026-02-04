<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Actions;

use Illuminate\Support\Facades\Log;
use Maloun\QAOrchestrator\Enums\TestCaseStatusEnum;
use Maloun\QAOrchestrator\Models\QAProcess;
use Maloun\QAOrchestrator\Models\QATestCase;
use Maloun\QAOrchestrator\Services\ClaudeService;

class GenerateTestCasesAction
{
    public function __construct(
        private readonly ClaudeService $claudeService,
    ) {}

    public function handle(QAProcess $qaProcess): void
    {
        Log::info('GenerateTestCasesAction: Generating test cases', [
            'qa_process_id' => $qaProcess->id,
            'jira_key' => $qaProcess->jira_issue_key,
        ]);

        $testCases = $this->claudeService->generateTestCases(
            summary: $qaProcess->jira_summary ?? '',
            description: $qaProcess->jira_description,
            acceptanceCriteria: $this->formatAcceptanceCriteria($qaProcess->jira_acceptance_criteria),
        );

        foreach ($testCases as $testCase) {
            QATestCase::create([
                'qa_process_id' => $qaProcess->id,
                'title' => $testCase->title,
                'description' => $testCase->description,
                'steps' => $testCase->steps,
                'expected_result' => $testCase->expectedResult,
                'status' => TestCaseStatusEnum::Generated,
            ]);
        }

        Log::info('GenerateTestCasesAction: Generated test cases', [
            'qa_process_id' => $qaProcess->id,
            'count' => count($testCases),
        ]);
    }

    private function formatAcceptanceCriteria(?array $criteria): ?string
    {
        if (! $criteria) {
            return null;
        }

        return implode("\n", $criteria);
    }
}