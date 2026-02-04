<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Jira Configuration
    |--------------------------------------------------------------------------
    */
    'jira' => [
        'base_url' => env('QA_JIRA_BASE_URL'),
        'email' => env('QA_JIRA_EMAIL'),
        'api_token' => env('QA_JIRA_API_TOKEN'),
        'qa_status' => env('QA_JIRA_QA_STATUS', 'Ready for QA'),
        'auto_create_defects' => env('QA_JIRA_AUTO_CREATE_DEFECTS', false),
        'acceptance_criteria_field' => env('QA_JIRA_ACCEPTANCE_CRITERIA_FIELD', 'customfield_10030'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Configuration (OpenRouter)
    |--------------------------------------------------------------------------
    */
    'claude' => [
        'api_key' => env('QA_CLAUDE_API_KEY'),
        'model' => env('QA_CLAUDE_MODEL', 'anthropic/claude-sonnet-4-20250514'),
        'max_tokens' => env('QA_CLAUDE_MAX_TOKENS', 8192),
        'timeout' => env('QA_CLAUDE_TIMEOUT', 120),
        'max_retries' => env('QA_CLAUDE_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | GitHub Configuration
    |--------------------------------------------------------------------------
    */
    'github' => [
        'token' => env('QA_GITHUB_TOKEN'),
        'repo' => env('QA_GITHUB_REPO'),
        'default_branch' => env('QA_GITHUB_DEFAULT_BRANCH', 'main'),
        'test_path' => env('QA_GITHUB_TEST_PATH', 'e2e/generated'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => env('QA_ROUTES_ENABLED', true),
        'prefix' => env('QA_ROUTES_PREFIX', 'api/qa'),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migrations Configuration
    |--------------------------------------------------------------------------
    */
    'migrations' => [
        'enabled' => env('QA_MIGRATIONS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('QA_QUEUE_CONNECTION'),
        'queue' => env('QA_QUEUE_NAME', 'qa'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack Notifications
    |--------------------------------------------------------------------------
    */
    'slack' => [
        'webhook_url' => env('QA_SLACK_WEBHOOK_URL'),
        'notify_on_success' => env('QA_SLACK_NOTIFY_SUCCESS', true),
        'notify_on_failure' => env('QA_SLACK_NOTIFY_FAILURE', true),
    ],
];