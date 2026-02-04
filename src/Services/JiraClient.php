<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Maloun\QAOrchestrator\Dto\JiraTicketDto;
use Maloun\QAOrchestrator\Exceptions\JiraApiException;

class JiraClient
{
    private readonly PendingRequest $request;

    private readonly string $acceptanceCriteriaField;

    public function __construct()
    {
        $baseUrl = config('qa-orchestrator.jira.base_url');
        $email = config('qa-orchestrator.jira.email');
        $apiToken = config('qa-orchestrator.jira.api_token');
        $this->acceptanceCriteriaField = config('qa-orchestrator.jira.acceptance_criteria_field', 'customfield_10030');

        $this->request = Http::withBasicAuth($email, $apiToken)
            ->baseUrl($baseUrl)
            ->acceptJson()
            ->asJson();
    }

    public function getTicket(string $issueKey): JiraTicketDto
    {
        $response = $this->executeRequest(
            fn () => $this->request
                ->get("/rest/api/3/issue/{$issueKey}", [
                    'fields' => "summary,description,status,assignee,labels,components,issuetype,priority,{$this->acceptanceCriteriaField}",
                ])
                ->throw()
                ->json(),
        );

        $fields = $response['fields'] ?? [];

        return new JiraTicketDto(
            key: $response['key'],
            summary: $fields['summary'] ?? '',
            description: $this->parseDescription($fields['description'] ?? null),
            acceptanceCriteria: $fields[$this->acceptanceCriteriaField] ?? null,
            status: $fields['status']['name'] ?? '',
            assignee: $fields['assignee']['displayName'] ?? null,
            labels: $fields['labels'] ?? [],
            components: array_map(fn ($c) => $c['name'], $fields['components'] ?? []),
            issueType: $fields['issuetype']['name'] ?? null,
            priority: $fields['priority']['name'] ?? null,
        );
    }

    public function addComment(string $issueKey, string $comment): array
    {
        return $this->executeRequest(
            fn () => $this->request
                ->post("/rest/api/3/issue/{$issueKey}/comment", [
                    'body' => [
                        'type' => 'doc',
                        'version' => 1,
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'content' => [
                                    ['type' => 'text', 'text' => $comment],
                                ],
                            ],
                        ],
                    ],
                ])
                ->throw()
                ->json(),
        );
    }

    public function transitionIssue(string $issueKey, string $transitionId): void
    {
        $this->executeRequest(
            fn () => $this->request
                ->post("/rest/api/3/issue/{$issueKey}/transitions", [
                    'transition' => ['id' => $transitionId],
                ])
                ->throw(),
        );
    }

    public function getTransitions(string $issueKey): array
    {
        return $this->executeRequest(
            fn () => $this->request
                ->get("/rest/api/3/issue/{$issueKey}/transitions")
                ->throw()
                ->json('transitions'),
        );
    }

    public function createIssue(string $projectKey, string $issueType, string $summary, string $description, ?string $parentKey = null): array
    {
        $payload = [
            'fields' => [
                'project' => ['key' => $projectKey],
                'issuetype' => ['name' => $issueType],
                'summary' => $summary,
                'description' => [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => $description],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($parentKey) {
            $payload['fields']['parent'] = ['key' => $parentKey];
        }

        return $this->executeRequest(
            fn () => $this->request
                ->post('/rest/api/3/issue', $payload)
                ->throw()
                ->json(),
        );
    }

    public function linkIssues(string $inwardIssue, string $outwardIssue, string $linkType = 'Relates'): void
    {
        $this->executeRequest(
            fn () => $this->request
                ->post('/rest/api/3/issueLink', [
                    'type' => ['name' => $linkType],
                    'inwardIssue' => ['key' => $inwardIssue],
                    'outwardIssue' => ['key' => $outwardIssue],
                ])
                ->throw(),
        );
    }

    private function parseDescription(?array $description): ?string
    {
        if (! $description || ! isset($description['content'])) {
            return null;
        }

        return $this->parseAtlassianDocFormat($description);
    }

    private function parseAtlassianDocFormat(array $doc): string
    {
        $text = '';

        foreach ($doc['content'] ?? [] as $block) {
            $text .= $this->parseBlock($block)."\n";
        }

        return trim($text);
    }

    private function parseBlock(array $block): string
    {
        $type = $block['type'] ?? '';
        $content = $block['content'] ?? [];

        return match ($type) {
            'paragraph' => $this->parseInlineContent($content),
            'heading' => $this->parseInlineContent($content),
            'bulletList', 'orderedList' => $this->parseList($content),
            'listItem' => '- '.$this->parseInlineContent($block['content'][0]['content'] ?? []),
            'codeBlock' => '```'."\n".($block['content'][0]['text'] ?? '')."\n".'```',
            default => $this->parseInlineContent($content),
        };
    }

    private function parseInlineContent(array $content): string
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

    private function parseList(array $items): string
    {
        $text = '';

        foreach ($items as $item) {
            $text .= $this->parseBlock($item)."\n";
        }

        return $text;
    }

    private function executeRequest(callable $action): mixed
    {
        try {
            return $action();
        } catch (RequestException $e) {
            throw new JiraApiException($e->getMessage(), $e->getCode(), $e);
        }
    }
}