<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Dto;

use Spatie\LaravelData\Data;

class JiraTicketDto extends Data
{
    public function __construct(
        public string $key,
        public string $summary,
        public ?string $description,
        public ?string $acceptanceCriteria,
        public string $status,
        public ?string $assignee,
        public array $labels,
        public array $components,
        public ?string $issueType,
        public ?string $priority,
    ) {}
}