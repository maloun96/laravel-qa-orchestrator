<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_processes', function (Blueprint $table) {
            $table->id();
            $table->string('jira_issue_key')->unique();
            $table->string('jira_issue_url')->nullable();
            $table->string('jira_project_key')->nullable();
            $table->string('status')->default('pending');
            $table->string('github_pr_url')->nullable();
            $table->integer('github_pr_number')->nullable();
            $table->string('github_branch')->nullable();
            $table->bigInteger('workflow_run_id')->nullable();
            $table->json('test_results')->nullable();
            $table->text('error_message')->nullable();
            $table->text('jira_summary')->nullable();
            $table->text('jira_description')->nullable();
            $table->json('jira_acceptance_criteria')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('github_pr_number');
            $table->index('workflow_run_id');
        });
    }
};