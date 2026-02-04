<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Services;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maloun\QAOrchestrator\Dto\TestCaseDto;
use Maloun\QAOrchestrator\Exceptions\ClaudeApiException;

class ClaudeService
{
    private readonly string $apiKey;

    private readonly string $model;

    private readonly int $maxTokens;

    private readonly int $timeout;

    private readonly int $maxRetries;

    public function __construct()
    {
        $this->apiKey = config('qa-orchestrator.claude.api_key');
        $this->model = config('qa-orchestrator.claude.model', 'anthropic/claude-sonnet-4-20250514');
        $this->maxTokens = config('qa-orchestrator.claude.max_tokens', 8192);
        $this->timeout = config('qa-orchestrator.claude.timeout', 120);
        $this->maxRetries = config('qa-orchestrator.claude.max_retries', 3);
    }

    /**
     * @return array<TestCaseDto>
     */
    public function generateTestCases(string $summary, ?string $description, ?string $acceptanceCriteria): array
    {
        $prompt = view('qa-orchestrator::prompts.generate-test-cases', [
            'summary' => $summary,
            'description' => $description,
            'acceptanceCriteria' => $acceptanceCriteria,
        ])->render();

        $response = $this->sendMessage($prompt);
        $json = $this->extractJson($response);

        return array_map(fn ($tc) => new TestCaseDto(
            title: $tc['title'],
            description: $tc['description'] ?? '',
            steps: $tc['steps'] ?? [],
            expectedResult: $tc['expectedResult'] ?? '',
            preconditions: $tc['preconditions'] ?? null,
            tags: $tc['tags'] ?? [],
        ), $json['testCases'] ?? []);
    }

    public function generatePlaywrightCode(string $summary, array $testCases, string $existingPagesContext = ''): string
    {
        $prompt = view('qa-orchestrator::prompts.generate-playwright', [
            'summary' => $summary,
            'testCases' => $testCases,
            'existingPagesContext' => $existingPagesContext,
        ])->render();

        $response = $this->sendMessage($prompt);

        return $this->extractCodeBlock($response);
    }

    public function analyzeTestResults(array $testResults, string $jiraSummary, ?string $jiraDescription): array
    {
        $prompt = view('qa-orchestrator::prompts.analyze-results', [
            'testResults' => $testResults,
            'jiraSummary' => $jiraSummary,
            'jiraDescription' => $jiraDescription,
        ])->render();

        $response = $this->sendMessage($prompt);

        return $this->extractJson($response);
    }

    private function sendMessage(string $prompt, ?int $maxTokens = null): string
    {
        $attempt = 0;
        $lastException = null;
        $maxTokens = $maxTokens ?? $this->maxTokens;

        while ($attempt < $this->maxRetries) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('app.name'),
                ])
                    ->timeout($this->timeout)
                    ->post('https://openrouter.ai/api/v1/chat/completions', [
                        'model' => $this->model,
                        'max_tokens' => $maxTokens,
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt],
                        ],
                    ])
                    ->throw();

                return $response->json('choices.0.message.content');
            } catch (RequestException $e) {
                $lastException = $e;
                $attempt++;

                Log::warning('OpenRouter API error', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'response' => $e->response?->body(),
                ]);

                if ($attempt < $this->maxRetries) {
                    sleep(2 ** $attempt);
                }
            } catch (Exception $e) {
                throw new ClaudeApiException('OpenRouter API error: '.$e->getMessage(), 0, $e);
            }
        }

        throw new ClaudeApiException(
            'Failed to get response after '.$this->maxRetries.' attempts',
            0,
            $lastException
        );
    }

    private function extractJson(string $response): array
    {
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $response, $matches)) {
            $json = json_decode($matches[1], true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        $json = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        throw new ClaudeApiException('Failed to parse JSON from Claude response');
    }

    private function extractCodeBlock(string $response): string
    {
        if (preg_match('/```(?:typescript|ts)?\s*\n?(.*?)\n?```/s', $response, $matches)) {
            return trim($matches[1]);
        }

        return trim($response);
    }
}