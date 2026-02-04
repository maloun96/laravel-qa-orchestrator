<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maloun\QAOrchestrator\Models\QAProcess;
use Throwable;

class SlackNotificationService
{
    private readonly ?string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('qa-orchestrator.slack.webhook_url');
    }

    public function notifyError(string $stage, string $error, ?QAProcess $qaProcess = null): void
    {
        if (! $this->webhookUrl) {
            return;
        }

        try {
            $blocks = [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => '❌ QA Orchestrator Error',
                        'emoji' => true,
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Stage:*\n{$stage}",
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Time:*\n".now()->format('Y-m-d H:i:s'),
                        ],
                    ],
                ],
            ];

            if ($qaProcess) {
                $blocks[] = [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Jira Ticket:*\n<{$qaProcess->jira_issue_url}|{$qaProcess->jira_issue_key}>",
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Process ID:*\n{$qaProcess->id}",
                        ],
                    ],
                ];
            }

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Error:*\n```{$this->truncate($error, 500)}```",
                ],
            ];

            Http::post($this->webhookUrl, [
                'text' => "QA Orchestrator Error: {$stage}",
                'blocks' => $blocks,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to send Slack notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifySuccess(string $message, ?QAProcess $qaProcess = null): void
    {
        if (! $this->webhookUrl) {
            return;
        }

        try {
            $blocks = [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => '✅ QA Orchestrator Success',
                        'emoji' => true,
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $message,
                    ],
                ],
            ];

            if ($qaProcess) {
                $fields = [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Jira Ticket:*\n<{$qaProcess->jira_issue_url}|{$qaProcess->jira_issue_key}>",
                    ],
                ];

                if ($qaProcess->github_pr_url) {
                    $fields[] = [
                        'type' => 'mrkdwn',
                        'text' => "*PR:*\n<{$qaProcess->github_pr_url}|View PR>",
                    ];
                }

                $blocks[] = [
                    'type' => 'section',
                    'fields' => $fields,
                ];
            }

            Http::post($this->webhookUrl, [
                'text' => "QA Orchestrator: {$message}",
                'blocks' => $blocks,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to send Slack notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length).'...';
    }
}