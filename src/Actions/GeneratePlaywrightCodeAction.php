<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Actions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maloun\QAOrchestrator\Models\QAProcess;
use Maloun\QAOrchestrator\Models\QATestCase;
use Maloun\QAOrchestrator\Services\ClaudeService;
use Maloun\QAOrchestrator\Services\GitHubService;

class GeneratePlaywrightCodeAction
{
    public function __construct(
        private readonly ClaudeService $claudeService,
        private readonly GitHubService $gitHubService,
    ) {}

    public function handle(QAProcess $qaProcess, string $existingPagesContext = ''): void
    {
        Log::info('GeneratePlaywrightCodeAction: Generating Playwright code', [
            'qa_process_id' => $qaProcess->id,
        ]);

        $testCases = $qaProcess->testCases()->get();

        if ($testCases->isEmpty()) {
            Log::warning('GeneratePlaywrightCodeAction: No test cases to generate', [
                'qa_process_id' => $qaProcess->id,
            ]);

            return;
        }

        $code = $this->claudeService->generatePlaywrightCode(
            summary: $qaProcess->jira_summary ?? '',
            testCases: $testCases->map(fn (QATestCase $tc) => [
                'title' => $tc->title,
                'description' => $tc->description,
                'steps' => $tc->steps,
                'expectedResult' => $tc->expected_result,
            ])->toArray(),
            existingPagesContext: $existingPagesContext,
        );

        $fileName = $this->generateFileName($qaProcess);

        foreach ($testCases as $testCase) {
            $testCase->update(['playwright_file_path' => $fileName]);
        }

        $qaProcess->update([
            'test_results' => array_merge($qaProcess->test_results ?? [], [
                'playwright_code' => $code,
                'playwright_file' => $fileName,
            ]),
        ]);

        Log::info('GeneratePlaywrightCodeAction: Generated Playwright code', [
            'qa_process_id' => $qaProcess->id,
            'file' => $fileName,
        ]);
    }

    private function generateFileName(QAProcess $qaProcess): string
    {
        $issueKey = Str::lower($qaProcess->jira_issue_key);
        $slug = Str::slug(Str::limit($qaProcess->jira_summary ?? 'test', 30, ''));
        $testPath = $this->gitHubService->getTestPath();

        return "{$testPath}/{$issueKey}-{$slug}.spec.ts";
    }
}