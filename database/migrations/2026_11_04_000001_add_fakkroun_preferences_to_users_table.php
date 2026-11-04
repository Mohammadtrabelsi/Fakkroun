<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Interface language and question language are independent (spec §5.1).
            // Codes are validated against config('app.available_locales'), so a new
            // language never needs a schema change. Null = use the platform default.
            $table->string('locale', 8)->nullable();
            $table->string('question_locale', 8)->nullable();
            $table->string('avatar_path')->nullable();

            // Account status (spec §4): active | suspended.
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason', 500)->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('last_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropIndex(['status']);
            $table->dropColumn([
                'locale', 'question_locale', 'avatar_path',
                'status', 'suspended_at', 'suspension_reason', 'last_seen_at',
            ]);
        });
    }
};
