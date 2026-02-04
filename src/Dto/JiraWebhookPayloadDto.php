<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Dto;

use Spatie\LaravelData\Data;

class JiraWebhookPayloadDto extends Data
{
    public function __construct(
        public string $webhookEvent,
        public string $issueKey,
        public ?string $projectKey,
        public ?string $issueUrl,
        public ?string $summary,
        public ?string $description,
        public ?string $status,
        public ?string $assignee,
        public ?array $labels,
        public ?array $components,
        public ?string $acceptanceCriteria,
    ) {}

    public static function fromWebhook(array $payload): self
    {
        $issue = $payload['issue'] ?? [];
        $fields = $issue['fields'] ?? [];
        $acceptanceCriteriaField = config('qa-orchestrator.jira.acceptance_criteria_field', 'customfield_10030');

        return new self(
            webhookEvent: $payload['webhookEvent'] ?? '',
            issueKey: $issue['key'] ?? '',
            projectKey: $fields['project']['key'] ?? null,
            issueUrl: $issue['self'] ?? null,
            summary: $fields['summary'] ?? null,
            description: self::extractDescription($fields),
            status: $fields['status']['name'] ?? null,
            assignee: $fields['assignee']['displayName'] ?? null,
            labels: $fields['labels'] ?? [],
            components: array_map(fn ($c) => $c['name'], $fields['components'] ?? []),
            acceptanceCriteria: $fields[$acceptanceCriteriaField] ?? null,
        );
    }

    private static function extractDescription(array $fields): ?string
    {
        $description = $fields['description'] ?? null;

        if (is_string($description)) {
            return $description;
        }

        if (is_array($description) && isset($description['content'])) {
            return self::parseAtlassianDocFormat($description);
        }

        return null;
    }

    private static function parseAtlassianDocFormat(array $doc): string
    {
        $text = '';

        foreach ($doc['content'] ?? [] as $block) {
            $text .= self::parseBlock($block)."\n";
        }

        return trim($text);
    }

    private static function parseBlock(array $block): string
    {
        $type = $block['type'] ?? '';
        $content = $block['content'] ?? [];

        return match ($type) {
            'paragraph' => self::parseInlineContent($content),
            'heading' => self::parseInlineContent($content),
            'bulletList', 'orderedList' => self::parseList($content),
            'listItem' => '- '.self::parseInlineContent($block['content'][0]['content'] ?? []),
            'codeBlock' => '```'."\n".($block['content'][0]['text'] ?? '')."\n".'```',
            default => self::parseInlineContent($content),
        };
    }

    private static function parseInlineContent(array $content): string
    {
        $text = '';

        foreach ($content as $node) {
            $text .= match ($node['type'] ?? '') {
                'text' => $node['text'] ?? '',
                'hardBreak' => "\n",
                default => '',
            };
        }

        return $text;
    }

    private static function parseList(array $items): string
    {
        $text = '';

        foreach ($items as $item) {
            $text .= self::parseBlock($item)."\n";
        }

        return $text;
    }
}