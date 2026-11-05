<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI generation audit trail (spec §10.3): provider, model, parameters,
 * prompt version, timestamps, status, validation results and cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generation_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 16)->default('batch'); // batch | draft
            $table->string('provider', 32);
            $table->string('model', 64);
            $table->string('prompt_version', 16);
            $table->json('parameters');
            $table->string('status', 16)->default('pending'); // pending | running | completed | failed | stopped
            $table->unsignedInteger('requested_count');
            $table->unsignedInteger('generated_count')->default(0);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->decimal('estimated_cost', 10, 4)->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'created_at']);
        });

        Schema::create('ai_generation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('ai_generation_batches')->cascadeOnDelete();
            $table->json('payload');
            $table->string('outcome', 16); // accepted | duplicate | invalid
            $table->json('messages')->nullable();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['batch_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_items');
        Schema::dropIfExists('ai_generation_batches');
    }
};
