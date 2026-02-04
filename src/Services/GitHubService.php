<?php

declare(strict_types=1);

namespace Maloun\QAOrchestrator\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Maloun\QAOrchestrator\Exceptions\GitHubApiException;

class GitHubService
{
    private readonly PendingRequest $request;

    private readonly string $owner;

    private readonly string $repo;

    private readonly string $defaultBranch;

    private readonly string $testPath;

    public function __construct()
    {
        $token = config('qa-orchestrator.github.token');
        $repoPath = config('qa-orchestrator.github.repo');
        [$this->owner, $this->repo] = explode('/', $repoPath);
        $this->defaultBranch = config('qa-orchestrator.github.default_branch', 'main');
        $this->testPath = config('qa-orchestrator.github.test_path', 'e2e/generated');

        $this->request = Http::withToken($token)
            ->baseUrl('https://api.github.com')
            ->acceptJson()
            ->asJson()
            ->withHeader('X-GitHub-Api-Version', '2022-11-28');
    }

    public function createBranch(string $branchName, ?string $baseBranch = null): array
    {
        $baseBranch = $baseBranch ?? $this->defaultBranch;

        $ref = $this->executeRequest(
            fn () => $this->request
                ->get("/repos/{$this->owner}/{$this->repo}/git/ref/heads/{$baseBranch}")
                ->throw()
                ->json(),
        );

        $sha = $ref['object']['sha'];

        return $this->executeRequest(
            fn () => $this->request
                ->post("/repos/{$this->owner}/{$this->repo}/git/refs", [
                    'ref' => "refs/heads/{$branchName}",
                    'sha' => $sha,
                ])
                ->throw()
                ->json(),
        );
    }

    public function createOrUpdateFile(string $branch, string $path, string $content, string $message, ?string $sha = null): array
    {
        $payload = [
            'message' => $message,
            'content' => base64_encode($content),
            'branch' => $branch,
        ];

        if ($sha) {
            $payload['sha'] = $sha;
        }

        return $this->executeRequest(
            fn () => $this->request
                ->put("/repos/{$this->owner}/{$this->repo}/contents/{$path}", $payload)
                ->throw()
                ->json(),
        );
    }

    public function getFileContent(string $branch, string $path): ?array
    {
        try {
            return $this->request
                ->get("/repos/{$this->owner}/{$this->repo}/contents/{$path}", ['ref' => $branch])
                ->throw()
                ->json();
        } catch (RequestException) {
            return null;
        }
    }

    public function createPullRequest(string $title, string $body, string $head, ?string $base = null): array
    {
        $base = $base ?? $this->defaultBranch;

        return $this->executeRequest(
            fn () => $this->request
                ->post("/repos/{$this->owner}/{$this->repo}/pulls", [
                    'title' => $title,
                    'body' => $body,
                    'head' => $head,
                    'base' => $base,
                ])
                ->throw()
                ->json(),
        );
    }

    public function triggerWorkflow(string $workflowId, string $ref, array $inputs = []): void
    {
        $this->executeRequest(
            fn () => $this->request
                ->post("/repos/{$this->owner}/{$this->repo}/actions/workflows/{$workflowId}/dispatches", [
                    'ref' => $ref,
                    'inputs' => $inputs,
                ])
                ->throw(),
        );
    }

    public function createRepositoryDispatch(string $eventType, array $clientPayload = []): void
    {
        $this->executeRequest(
            fn () => $this->request
                ->post("/repos/{$this->owner}/{$this->repo}/dispatches", [
                    'event_type' => $eventType,
                    'client_payload' => $clientPayload,
                ])
                ->throw(),
        );
    }

    public function getWorkflowRun(int $runId): array
    {
        return $this->executeRequest(
            fn () => $this->request
                ->get("/repos/{$this->owner}/{$this->repo}/actions/runs/{$runId}")
                ->throw()
                ->json(),
        );
    }

    public function getWorkflowRunJobs(int $runId): array
    {
        return $this->executeRequest(
            fn () => $this->request
                ->get("/repos/{$this->owner}/{$this->repo}/actions/runs/{$runId}/jobs")
                ->throw()
                ->json(),
        );
    }

    public function downloadArtifact(int $artifactId): string
    {
        $response = $this->executeRequest(
            fn () => $this->request
                ->get("/repos/{$this->owner}/{$this->repo}/actions/artifacts/{$artifactId}/zip")
                ->throw(),
        );

        return $response->body();
    }

    public function getRunArtifacts(int $runId): array
    {
        return $this->executeRequest(
            fn () => $this->request
                ->get("/repos/{$this->owner}/{$this->repo}/actions/runs/{$runId}/artifacts")
                ->throw()
                ->json(),
        );
    }

    public function addPrComment(int $prNumber, string $body): array
    {
        return $this->executeRequest(
            fn () => $this->request
                ->post("/repos/{$this->owner}/{$this->repo}/issues/{$prNumber}/comments", [
                    'body' => $body,
                ])
                ->throw()
                ->json(),
        );
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function getRepo(): string
    {
        return $this->repo;
    }

    public function getTestPath(): string
    {
        return $this->testPath;
    }

    public function getDefaultBranch(): string
    {
        return $this->defaultBranch;
    }

    /**
     * Find an open PR that matches the Jira key pattern (excluding QA branches)
     */
    public function findPrByJiraKey(string $jiraKey): ?array
    {
        try {
            $prs = $this->request
                ->get("/repos/{$this->owner}/{$this->repo}/pulls", [
                    'state' => 'open',
                    'per_page' => 100,
                ])
                ->throw()
                ->json();

            $jiraKeyLower = strtolower($jiraKey);

            foreach ($prs as $pr) {
                $branch = $pr['head']['ref'] ?? '';
                $branchLower = strtolower($branch);
                $titleLower = strtolower($pr['title'] ?? '');

                // Skip QA branches - we want feature branches only
                if (str_starts_with($branchLower, 'qa/')) {
                    continue;
                }

                if (str_contains($branchLower, $jiraKeyLower) || str_contains($titleLower, $jiraKeyLower)) {
                    return $pr;
                }
            }

            return null;
        } catch (RequestException) {
            return null;
        }
    }

    /**
     * Find a branch that matches the Jira key pattern (excluding QA branches)
     */
    public function findBranchByJiraKey(string $jiraKey): ?string
    {
        try {
            $branches = $this->request
                ->get("/repos/{$this->owner}/{$this->repo}/branches", [
                    'per_page' => 100,
                ])
                ->throw()
                ->json();

            $jiraKeyLower = strtolower($jiraKey);

            foreach ($branches as $branch) {
                $branchName = $branch['name'] ?? '';
                $branchLower = strtolower($branchName);

                // Skip QA branches - we want feature branches only
                if (str_starts_with($branchLower, 'qa/')) {
                    continue;
                }

                if (str_contains($branchLower, $jiraKeyLower)) {
                    return $branchName;
                }
            }

            return null;
        } catch (RequestException) {
            return null;
        }
    }

    /**
     * Check if a branch exists
     */
    public function branchExists(string $branchName): bool
    {
        try {
            $this->request
                ->get("/repos/{$this->owner}/{$this->repo}/git/ref/heads/{$branchName}")
                ->throw();

            return true;
        } catch (RequestException) {
            return false;
        }
    }

    private function executeRequest(callable $action): mixed
    {
        try {
            return $action();
        } catch (RequestException $e) {
            throw new GitHubApiException($e->getMessage(), $e->getCode(), $e);
        }
    }
}