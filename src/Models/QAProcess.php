<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Maloun\QAOrchestrator\Enums\QAStatusEnum;

/**
 * @property int $id
 * @property string $jira_issue_key
 * @property ?string $jira_issue_url
 * @property ?string $jira_project_key
 * @property QAStatusEnum $status
 * @property ?string $github_pr_url
 * @property ?int $github_pr_number
 * @property ?string $github_branch
 * @property ?int $workflow_run_id
 * @property ?array $test_results
 * @property ?string $error_message
 * @property ?string $jira_summary
 * @property ?string $jira_description
 * @property ?array $jira_acceptance_criteria
 */
class QAProcess extends Model
{
    protected $table = 'qa_processes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => QAStatusEnum::class,
            'test_results' => 'array',
            'jira_acceptance_criteria' => 'array',
        ];
    }

    public function testCases(): HasMany
    {
        return $this->hasMany(QATestCase::class, 'qa_process_id');
    }

    public function updateStatus(QAStatusEnum $status, ?string $errorMessage = null): self
    {
        $this->update([
            'status' => $status,
            'error_message' => $errorMessage,
        ]);

        return $this;
    }

    public function markFailed(string $errorMessage): self
    {
        return $this->updateStatus(QAStatusEnum::Failed, $errorMessage);
    }

    public function isPending(): bool
    {
        return $this->status === QAStatusEnum::Pending;
    }

    public function isCompleted(): bool
    {
        return $this->status === QAStatusEnum::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === QAStatusEnum::Failed;
    }
}