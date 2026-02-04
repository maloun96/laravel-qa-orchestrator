<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Dto;

use Spatie\LaravelData\Data;

class TestResultDto extends Data
{
    public function __construct(
        public string $testName,
        public string $status,
        public ?int $duration,
        public ?string $errorMessage,
        public ?string $stackTrace,
        public ?string $screenshotPath,
    ) {}
}