<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story 16 S12: a themed block of consecutive sponsored questions in a game,
 * "presented by" one campaign, billed once per room from its own credit
 * balance (sponsored_round), separate from impressions and single
 * sponsored questions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_packages', function (Blueprint $table) {
            $table->unsignedBigInteger('sponsored_round_credits')->default(0)->after('sponsored_question_credits');
        });

        Schema::table('advertising_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('sponsored_round_credits')->default(0)->after('sponsored_question_credits');
        });

        Schema::table('game_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('sponsored_round_campaign_id')->nullable();
        });

        Schema::create('sponsored_round_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_id')->unique();
            $table->foreignId('campaign_id')->constrained('advertising_campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('room_id');
            $table->unsignedSmallInteger('questions_count');
            $table->unsignedSmallInteger('players_count');
            $table->string('language', 8);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('billing_reference', 100)->unique();
            $table->unsignedBigInteger('ledger_entry_id');
            $table->timestamp('delivered_at');

            $table->index(['campaign_id', 'delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsored_round_deliveries');
        Schema::table('game_questions', fn (Blueprint $table) => $table->dropColumn('sponsored_round_campaign_id'));
        Schema::table('advertising_invoices', fn (Blueprint $table) => $table->dropColumn('sponsored_round_credits'));
        Schema::table('advertising_packages', fn (Blueprint $table) => $table->dropColumn('sponsored_round_credits'));
    }
};
