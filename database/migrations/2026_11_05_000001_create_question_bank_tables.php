<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Centralised question bank (spec §7). Originals and translations are kept
 * apart: `questions` holds language-independent data, `question_choices`
 * gives every answer a stable id, and `question_translations` holds the text
 * of each language with its own approval status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Categories are soft-deleted; a hard delete must not orphan questions.
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin_language', 8);
            $table->string('type', 16)->default('mcq');
            $table->string('difficulty', 8);
            // References question_choices.id. Kept without a database FK (the two
            // tables reference each other); QuestionService guarantees it points
            // at one of this question's own choices.
            $table->unsignedBigInteger('correct_choice_id')->nullable();
            $table->string('source', 16);
            $table->string('reference_url', 2048)->nullable();
            $table->text('reference_text')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            // Sponsored questions arrive in Phase 5; they follow the same rules.
            $table->boolean('is_sponsored')->default(false);
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('import_batch_id')->nullable()->index();
            // Usage statistics, updated in batches after games (Phase 3).
            $table->unsignedInteger('times_played')->default(0);
            $table->unsignedInteger('times_correct')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category_id', 'difficulty']);
            $table->index(['creator_id', 'created_at']);
        });

        Schema::create('question_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique(['question_id', 'position']);
        });

        Schema::create('question_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('language', 8);
            $table->text('body');
            // {"<choice id>": "text", ...} — keys must match question_choices exactly.
            $table->json('choices');
            $table->text('explanation')->nullable();
            // sha256 of the normalised body + choices, for exact duplicate detection.
            $table->string('content_hash', 64);
            $table->string('status', 16)->default('pending');
            $table->boolean('is_machine_translated')->default(false);
            $table->foreignId('translated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['question_id', 'language']);
            $table->index(['language', 'content_hash']);
            $table->index(['language', 'status']);
        });

        Schema::create('question_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            // Full content of that version (question fields + every translation),
            // so game history can show exactly what was asked.
            $table->json('snapshot');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['question_id', 'version']);
        });

        Schema::create('question_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('translation_id')->nullable()->constrained('question_translations')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['question_id', 'created_at']);
        });

        Schema::create('question_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('language', 8)->nullable();
            $table->string('reason', 32);
            $table->text('details')->nullable();
            $table->string('status', 16)->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['question_id', 'reporter_id', 'status']);
        });

        // One row per playable (question, language) pair: the question is
        // published and that translation is approved. The selection engine
        // (Phase 3) reads only this table, with an index seek on random_key.
        Schema::create('question_language_pool', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('language', 8);
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('subcategory_id')->nullable();
            $table->string('difficulty', 8);
            $table->boolean('is_sponsored')->default(false);
            $table->double('random_key');
            $table->timestamp('last_served_at')->nullable();

            $table->unique(['question_id', 'language']);
            $table->index(['language', 'category_id', 'difficulty', 'random_key'], 'pool_selection_index');
            $table->index(['language', 'subcategory_id', 'difficulty', 'random_key'], 'pool_subcategory_index');
        });

        Schema::create('question_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format', 8);
            $table->string('original_name');
            $table->string('path');
            // {"field": "file header", ...}
            $table->json('column_mapping');
            $table->json('options')->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('question_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('question_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('messages');
            $table->timestamp('created_at')->nullable();

            $table->index(['import_batch_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_import_errors');
        Schema::dropIfExists('question_import_batches');
        Schema::dropIfExists('question_language_pool');
        Schema::dropIfExists('question_reports');
        Schema::dropIfExists('question_moderation_logs');
        Schema::dropIfExists('question_revisions');
        Schema::dropIfExists('question_translations');
        Schema::dropIfExists('question_choices');
        Schema::dropIfExists('questions');
    }
};
