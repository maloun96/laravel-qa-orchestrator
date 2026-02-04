<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qa_process_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('steps')->nullable();
            $table->text('expected_result')->nullable();
            $table->string('playwright_file_path')->nullable();
            $table->string('status')->default('pending');
            $table->json('execution_result')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }
};