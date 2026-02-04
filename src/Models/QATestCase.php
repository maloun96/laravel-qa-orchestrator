<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Maloun\QAOrchestrator\Enums\TestCaseStatusEnum;

/**
 * @property int $id
 * @property int $qa_process_id
 * @property string $title
 * @property ?string $description
 * @property ?array $steps
 * @property ?string $expected_result
 * @property ?string $playwright_file_path
 * @property TestCaseStatusEnum $status
 * @property ?array $execution_result
 */
class QATestCase extends Model
{
    protected $table = 'qa_test_cases';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TestCaseStatusEnum::class,
            'steps' => 'array',
            'execution_result' => 'array',
        ];
    }

    public function qaProcess(): BelongsTo
    {
        return $this->belongsTo(QAProcess::class, 'qa_process_id');
    }

    public function updateStatus(TestCaseStatusEnum $status): self
    {
        $this->update(['status' => $status]);

        return $this;
    }

    public function markPassed(array $result = []): self
    {
        $this->update([
            'status' => TestCaseStatusEnum::Passed,
            'execution_result' => $result,
        ]);

        return $this;
    }

    public function markFailed(array $result): self
    {
        $this->update([
            'status' => TestCaseStatusEnum::Failed,
            'execution_result' => $result,
        ]);

        return $this;
    }
}