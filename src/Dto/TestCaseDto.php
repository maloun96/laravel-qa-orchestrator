<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Dto;

use Spatie\LaravelData\Data;

class TestCaseDto extends Data
{
    /**
     * @param  array<int, array{action: string, data?: string, expectedResult: string}>  $steps
     */
    public function __construct(
        public string $title,
        public string $description,
        public array $steps,
        public string $expectedResult,
        public ?string $preconditions = null,
        public array $tags = [],
    ) {}
}