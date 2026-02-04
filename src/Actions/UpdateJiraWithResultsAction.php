<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Actions;

use Exception;
use Illuminate\Support\Facades\Log;
use Maloun\QAOrchestrator\Models\QAProcess;
use Maloun\QAOrchestrator\Services\JiraClient;

class UpdateJiraWithResultsAction
{
    public function __construct(
        private readonly JiraClient $jiraClient,
    ) {}

    public function handle(QAProcess $qaProcess, array $analysis): void
    {
        Log::info('UpdateJiraWithResultsAction: Updating Jira', [
            'qa_process_id' => $qaProcess->id,
            'jira_key' => $qaProcess->jira_issue_key,
        ]);

        $this->addJiraComment($qaProcess, $analysis);

        $this->createDefectsIfNeeded($qaProcess, $analysis);

        Log::info('UpdateJiraWithResultsAction: Jira updated', [
            'qa_process_id' => $qaProcess->id,
        ]);
    }

    private function addJiraComment(QAProcess $qaProcess, array $analysis): void
    {
        $testResults = $qaProcess->test_results ?? [];
        $conclusion = $testResults['conclusion'] ?? 'unknown';
        $emoji = $conclusion === 'success' ? '✅' : '❌';

        $comment = "{$emoji} *QA Automation Results*\n\n";
        $comment .= "*Summary:* {$analysis['summary']}\n\n";

        if ($qaProcess->github_pr_url) {
            $comment .= "*PR:* {$qaProcess->github_pr_url}\n";
        }

        $testCases = $qaProcess->testCases()->get();
        $passed = $testCases->where('status', 'passed')->count();
        $failed = $testCases->where('status', 'failed')->count();

        $comment .= "\n*Results:* {$passed} passed, {$failed} failed\n";

        if (! empty($analysis['failures'])) {
            $comment .= "\n*Failures:*\n";

            foreach ($analysis['failures'] as $failure) {
                $comment .= "- {$failure['test']}: {$failure['reason']}\n";
            }
        }

        if (! empty($analysis['recommendations'])) {
            $comment .= "\n*Recommendations:*\n";

            foreach ($analysis['recommendations'] as $rec) {
                $comment .= "- {$rec}\n";
            }
        }

        try {
            $this->jiraClient->addComment($qaProcess->jira_issue_key, $comment);
        } catch (Exception $e) {
            Log::warning('UpdateJiraWithResultsAction: Failed to add Jira comment', [
                'jira_key' => $qaProcess->jira_issue_key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function createDefectsIfNeeded(QAProcess $qaProcess, array $analysis): void
    {
        if (! config('qa-orchestrator.jira.auto_create_defects', false)) {
            return;
        }

        $failures = $analysis['failures'] ?? [];

        if (empty($failures)) {
            return;
        }

        foreach ($failures as $failure) {
            try {
                $defect = $this->jiraClient->createIssue(
                    projectKey: $qaProcess->jira_project_key,
                    issueType: 'Bug',
                    summary: "[Auto] {$failure['test']} - Test Failure",
                    description: "Automated test failure detected.\n\n"
                        ."*Test:* {$failure['test']}\n"
                        ."*Reason:* {$failure['reason']}\n\n"
                        ."Related to: {$qaProcess->jira_issue_key}",
                    parentKey: null,
                );

                $this->jiraClient->linkIssues(
                    inwardIssue: $defect['key'],
                    outwardIssue: $qaProcess->jira_issue_key,
                    linkType: 'Relates',
                );

                Log::info('UpdateJiraWithResultsAction: Created defect', [
                    'defect_key' => $defect['key'],
                    'related_to' => $qaProcess->jira_issue_key,
                ]);
            } catch (Exception $e) {
                Log::warning('UpdateJiraWithResultsAction: Failed to create defect', [
                    'failure' => $failure,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}