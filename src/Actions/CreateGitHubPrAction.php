<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Actions;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maloun\QAOrchestrator\Models\QAProcess;
use Maloun\QAOrchestrator\Models\QATestCase;
use Maloun\QAOrchestrator\Services\GitHubService;
use Maloun\QAOrchestrator\Services\SlackNotificationService;

class CreateGitHubPrAction
{
    public function __construct(
        private readonly GitHubService $gitHubService,
    ) {}

    public function handle(QAProcess $qaProcess): void
    {
        Log::info('CreateGitHubPrAction: Creating GitHub PR', [
            'qa_process_id' => $qaProcess->id,
        ]);

        $testResults = $qaProcess->test_results ?? [];
        $playwrightCode = $testResults['playwright_code'] ?? null;
        $basePath = $testResults['playwright_file'] ?? null;

        if (! $playwrightCode || ! $basePath) {
            Log::warning('CreateGitHubPrAction: No Playwright code to commit', [
                'qa_process_id' => $qaProcess->id,
            ]);

            return;
        }

        // Find existing PR or branch for this Jira key
        $targetBranch = $this->findTargetBranch($qaProcess->jira_issue_key);
        $baseBranch = $targetBranch ?? $this->gitHubService->getDefaultBranch();

        Log::info('CreateGitHubPrAction: Target branch determined', [
            'qa_process_id' => $qaProcess->id,
            'target_branch' => $targetBranch,
            'base_branch' => $baseBranch,
        ]);

        $branchName = $this->generateBranchName($qaProcess);

        // Create QA branch from the target branch (existing feature branch or main)
        $this->gitHubService->createBranch($branchName, $baseBranch);

        // Parse and commit multiple files from AI output
        $files = $this->parseMultipleFiles($playwrightCode, $basePath);

        foreach ($files as $file) {
            $this->gitHubService->createOrUpdateFile(
                branch: $branchName,
                path: $file['path'],
                content: $file['content'],
                message: "test(e2e): add {$file['name']} for {$qaProcess->jira_issue_key}",
            );

            Log::info('CreateGitHubPrAction: Committed file', [
                'qa_process_id' => $qaProcess->id,
                'file' => $file['path'],
            ]);
        }

        $prBody = $this->generatePrBody($qaProcess, $baseBranch);

        // Create PR targeting the feature branch (or main if no feature branch exists)
        $pr = $this->gitHubService->createPullRequest(
            title: "test(e2e): {$qaProcess->jira_issue_key} - {$qaProcess->jira_summary}",
            body: $prBody,
            head: $branchName,
            base: $baseBranch,
        );

        $qaProcess->update([
            'github_branch' => $branchName,
            'github_pr_url' => $pr['html_url'] ?? null,
            'github_pr_number' => $pr['number'] ?? null,
            'github_target_branch' => $baseBranch,
        ]);

        $this->triggerTests($qaProcess, $branchName);

        Log::info('CreateGitHubPrAction: Created GitHub PR', [
            'qa_process_id' => $qaProcess->id,
            'pr_url' => $qaProcess->github_pr_url,
            'target_branch' => $baseBranch,
        ]);

        if (config('qa-orchestrator.slack.notify_on_success')) {
            $message = $targetBranch
                ? "PR created targeting existing branch `{$targetBranch}`"
                : "PR created targeting `{$baseBranch}` (no existing feature branch found)";

            app(SlackNotificationService::class)->notifySuccess($message, $qaProcess);
        }
    }

    /**
     * Find an existing branch or PR for the Jira key to use as base
     */
    private function findTargetBranch(string $jiraKey): ?string
    {
        // First, check for an existing open PR with this Jira key
        $existingPr = $this->gitHubService->findPrByJiraKey($jiraKey);

        if ($existingPr) {
            $branch = $existingPr['head']['ref'] ?? null;

            Log::info('CreateGitHubPrAction: Found existing PR for Jira key', [
                'jira_key' => $jiraKey,
                'pr_number' => $existingPr['number'],
                'branch' => $branch,
            ]);

            return $branch;
        }

        // If no PR, check for an existing branch with this Jira key
        $existingBranch = $this->gitHubService->findBranchByJiraKey($jiraKey);

        if ($existingBranch) {
            Log::info('CreateGitHubPrAction: Found existing branch for Jira key', [
                'jira_key' => $jiraKey,
                'branch' => $existingBranch,
            ]);

            return $existingBranch;
        }

        Log::info('CreateGitHubPrAction: No existing PR or branch found for Jira key', [
            'jira_key' => $jiraKey,
        ]);

        return null;
    }

    private function generateBranchName(QAProcess $qaProcess): string
    {
        $issueKey = Str::lower($qaProcess->jira_issue_key);
        $timestamp = now()->format('Ymd-His');

        return "qa/{$issueKey}-{$timestamp}";
    }

    /**
     * Parse multi-file output from AI (separated by // === FILE: path === markers)
     *
     * @return array<array{name: string, path: string, content: string}>
     */
    private function parseMultipleFiles(string $code, string $basePath): array
    {
        $testPath = dirname($basePath);
        $files = [];

        // Match pattern: // === FILE: filename.ext ===
        $pattern = '/\/\/\s*===\s*FILE:\s*([^\s=]+)\s*===/';

        Log::info('parseMultipleFiles: Starting parse', [
            'code_length' => strlen($code),
            'code_preview' => substr($code, 0, 200),
            'pattern' => $pattern,
        ]);

        $matchResult = preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE);

        Log::info('parseMultipleFiles: Regex result', [
            'match_result' => $matchResult,
            'matches_count' => $matchResult ? count($matches[0]) : 0,
            'matches' => $matchResult ? $matches : 'no matches',
        ]);

        if ($matchResult) {
            $fullMatches = $matches[0];
            $fileNames = $matches[1];
            $count = count($fileNames);

            for ($i = 0; $i < $count; $i++) {
                $fileName = $fileNames[$i][0];
                $matchStart = $fullMatches[$i][1];
                $matchLength = strlen($fullMatches[$i][0]);
                $startOffset = $matchStart + $matchLength;

                // Find end of this file's content (next FILE marker or end of string)
                $endOffset = ($i + 1 < $count)
                    ? $fullMatches[$i + 1][1]
                    : strlen($code);

                $content = trim(substr($code, $startOffset, $endOffset - $startOffset));

                // Determine full path
                $fullPath = "{$testPath}/{$fileName}";

                $files[] = [
                    'name' => $fileName,
                    'path' => $fullPath,
                    'content' => $content,
                ];
            }
        }

        Log::info('parseMultipleFiles: Parsed files', [
            'files_count' => count($files),
            'file_names' => array_column($files, 'name'),
        ]);

        // Fallback: if no FILE markers found, treat entire code as single spec file
        if (empty($files)) {
            Log::warning('parseMultipleFiles: No FILE markers found, using fallback');
            $files[] = [
                'name' => basename($basePath),
                'path' => $basePath,
                'content' => $code,
            ];
        }

        return $files;
    }

    private function generatePrBody(QAProcess $qaProcess, string $targetBranch): string
    {
        $testCases = $qaProcess->testCases()->get();

        $body = "## AI-Generated E2E Tests\n\n";
        $body .= "**Jira Ticket:** [{$qaProcess->jira_issue_key}]({$qaProcess->jira_issue_url})\n";
        $body .= "**Target Branch:** `{$targetBranch}`\n\n";
        $body .= "### Test Cases\n\n";

        /** @var QATestCase $testCase */
        foreach ($testCases as $testCase) {
            $body .= "- [ ] {$testCase->title}\n";
        }

        $body .= "\n### Summary\n\n";
        $body .= $qaProcess->jira_summary."\n\n";

        $body .= "---\n";
        $body .= "_This PR was automatically generated by the QA Orchestrator._\n";

        return $body;
    }

    private function triggerTests(QAProcess $qaProcess, string $branchName): void
    {
        try {
            $this->gitHubService->createRepositoryDispatch('qa-e2e-tests', [
                'qa_process_id' => $qaProcess->id,
                'branch' => $branchName,
                'jira_key' => $qaProcess->jira_issue_key,
            ]);
        } catch (Exception $e) {
            Log::warning('CreateGitHubPrAction: Failed to trigger tests', [
                'qa_process_id' => $qaProcess->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}