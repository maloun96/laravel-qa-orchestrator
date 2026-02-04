# Laravel QA Orchestrator

AI-powered QA automation: Jira webhook → AI test generation → Playwright PR → CI → Analysis → Jira update

## Installation

```bash
composer require maloun96/laravel-qa-orchestrator
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=qa-orchestrator-config
```

Add to `.env`:

```env
# Jira
QA_JIRA_BASE_URL=https://your-domain.atlassian.net
QA_JIRA_EMAIL=your-email@example.com
QA_JIRA_API_TOKEN=your-api-token
QA_JIRA_QA_STATUS="Ready for QA"
QA_JIRA_AUTO_CREATE_DEFECTS=false

# Claude AI
QA_CLAUDE_API_KEY=your-anthropic-api-key
QA_CLAUDE_MODEL=claude-sonnet-4-20250514

# GitHub
QA_GITHUB_TOKEN=your-github-token
QA_GITHUB_REPO=owner/repo
QA_GITHUB_DEFAULT_BRANCH=main
```

## Migrations

```bash
php artisan migrate
```

Or publish migrations for customization:

```bash
php artisan vendor:publish --tag=qa-orchestrator-migrations
```

## Architecture

```
Jira (Ready for QA) ──webhook──▶ JiraQAWebhookController
                                       │
                                       ▼
                              JiraReadyForQAEvent
                                       │
                                       ▼
                          ProcessJiraQAWebhookJob (queued)
                                       │
                   ┌───────────────────┼───────────────────┐
                   ▼                   ▼                   ▼
          JiraClient.getTicket   Create QAProcess    ClaudeService
                   │                   │            .generateTestCases
                   └───────────────────┼───────────────────┘
                                       ▼
                          ClaudeService.generatePlaywright
                                       │
                                       ▼
                   GitHubService.createBranch/commit/PR
                                       │
                                       ▼
                          GitHub Actions (e2e_tests)
                                       │
                                       ▼
                          GitHubQAWebhookController
                                       │
                                       ▼
                      ClaudeService.analyzeResults
                                       │
                   ┌───────────────────┼───────────────────┐
                   ▼                   ▼                   ▼
         JiraClient.update    Update QAProcess    Create defects
```

## API Endpoints

### POST /api/qa/jira-webhook
Receives Jira webhooks when tickets move to "Ready for QA"

### POST /api/qa/github-webhook
Receives GitHub Actions workflow completion events

## Events

- `JiraReadyForQAEvent` - Dispatched when a Jira ticket is ready for QA
- `TestCasesGeneratedEvent` - Dispatched when test cases are generated
- `TestsCompletedEvent` - Dispatched when GitHub Actions tests complete

## Customization

### Views

Publish views for customization:

```bash
php artisan vendor:publish --tag=qa-orchestrator-views
```

Views are located in `resources/views/vendor/qa-orchestrator/prompts/`:
- `generate-test-cases.blade.php` - Test case generation prompt
- `generate-playwright.blade.php` - Playwright code generation prompt
- `analyze-results.blade.php` - Test results analysis prompt

### Disabling Routes

Set `QA_ROUTES_ENABLED=false` in `.env` to disable automatic route registration.

### Custom Route Prefix

Set `QA_ROUTES_PREFIX=your-prefix` to change the route prefix.

## License

MIT