<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // One level of nesting: a category with a parent is a subcategory.
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('categories')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('icon', 64)->nullable();
            $table->string('color', 7)->nullable();

            // Question rules (spec §6, §7.4) — data, not code.
            $table->string('validation_policy', 16)->default('admin');
            $table->boolean('requires_reference')->default(false);
            $table->boolean('allow_community_submissions')->default(true);

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('category_moderator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_moderator');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'sort_order']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn([
                'sort_order', 'icon', 'color',
                'validation_policy', 'requires_reference', 'allow_community_submissions',
            ]);
        });
    }
};
