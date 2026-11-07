<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Story 16 S1: topic keywords on a creative, matched against the question.
        Schema::table('advertising_creatives', function (Blueprint $table) {
            $table->json('keywords')->nullable()->after('alt_text');
        });

        // Story 16 S13: the reviewer confirms the advertising policy was checked.
        Schema::table('advertising_campaigns', function (Blueprint $table) {
            $table->timestamp('policy_checked_at')->nullable();
            $table->foreignId('policy_checked_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Story 16 S4: "how often has this player seen this creative today".
        Schema::table('ad_deliveries', function (Blueprint $table) {
            $table->index(['player_id', 'creative_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ad_deliveries', function (Blueprint $table) {
            $table->dropIndex(['player_id', 'creative_id', 'issued_at']);
        });
        Schema::table('advertising_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('policy_checked_by');
            $table->dropColumn('policy_checked_at');
        });
        Schema::table('advertising_creatives', function (Blueprint $table) {
            $table->dropColumn('keywords');
        });
    }
};
