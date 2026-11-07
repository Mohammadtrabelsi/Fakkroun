<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advertising and billing (spec §17–§20). Money is stored as integer minor
 * units + ISO currency. Impressions and sponsored-question deliveries are
 * separate consumption units with separate wallets; every billable event has
 * a unique billing_reference, and the ledger is append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 24)->default('company'); // ngo | school | university | association | company | other
            $table->string('contact_email')->nullable();
            $table->string('website', 2048)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('advertising_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('impression_credits');
            $table->unsignedBigInteger('sponsored_question_credits');
            $table->unsignedBigInteger('price_minor');
            $table->string('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_custom')->default(false);
            $table->timestamps();
        });

        Schema::create('advertiser_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertiser_id')->constrained()->cascadeOnDelete();
            $table->string('credit_type', 24); // impression | sponsored_question
            $table->unsignedBigInteger('balance')->default(0);
            $table->timestamps();

            $table->unique(['advertiser_id', 'credit_type']);
        });

        Schema::create('advertising_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertiser_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // draft | pending_review | active | paused | exhausted | completed | rejected
            $table->string('status', 16)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('target_category_ids')->nullable();
            $table->json('target_languages')->nullable();
            $table->unsignedSmallInteger('max_per_player_per_game')->default(2);
            $table->unsignedBigInteger('budget_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
        });

        Schema::create('advertising_creatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('advertising_campaigns')->cascadeOnDelete();
            $table->string('format', 24)->default('banner'); // banner | logo | post_game
            $table->string('language', 8)->nullable(); // null = every language
            $table->string('headline', 120);
            $table->string('body', 280)->nullable();
            $table->string('image_path')->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->string('alt_text', 200)->nullable();
            $table->string('status', 16)->default('pending'); // pending | approved | rejected
            $table->timestamps();
        });

        Schema::create('advertising_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertiser_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('advertising_campaigns')->nullOnDelete();
            // payment | credit | consumption | adjustment | reversal
            $table->string('entry_type', 16);
            // impression | sponsored_question; null for payments (money only)
            $table->string('credit_type', 24)->nullable();
            $table->string('format', 24)->nullable();
            // Credits: positive adds, negative consumes. Payments carry 0.
            $table->bigInteger('quantity')->default(0);
            $table->bigInteger('unit_price_minor')->default(0);
            $table->bigInteger('amount_minor')->default(0);
            $table->string('currency', 3);
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->unsignedBigInteger('game_question_id')->nullable();
            $table->unsignedBigInteger('question_id')->nullable();
            $table->unsignedBigInteger('player_id')->nullable();
            $table->string('billing_reference', 100)->unique();
            $table->unsignedBigInteger('reverses_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at');

            $table->index(['advertiser_id', 'created_at']);
            // Explicit name: the auto-generated one exceeds MySQL's 64-character identifier limit.
            $table->index(['campaign_id', 'credit_type', 'created_at'], 'adv_ledger_campaign_credit_created_idx');
        });

        Schema::create('ad_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('advertising_campaigns')->cascadeOnDelete();
            $table->foreignId('creative_id')->constrained('advertising_creatives')->cascadeOnDelete();
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('game_question_id');
            $table->foreignId('player_id')->constrained('users')->cascadeOnDelete();
            $table->string('placement', 16)->default('reveal');
            // Single-use proof the server offered this ad to this player.
            $table->string('token', 64)->unique();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');

            // One offer per player, question and placement (refresh-safe).
            $table->unique(['player_id', 'game_question_id', 'placement']);
            $table->index(['campaign_id', 'issued_at']);
        });

        Schema::create('advertising_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->unique()->constrained('ad_deliveries')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('advertising_campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('creative_id');
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('game_question_id');
            $table->string('language', 8)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('billing_reference', 100)->unique();
            $table->unsignedBigInteger('ledger_entry_id');
            $table->timestamp('displayed_at');

            $table->index(['campaign_id', 'displayed_at']);
        });

        Schema::create('sponsored_question_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_question_id')->unique();
            $table->foreignId('campaign_id')->constrained('advertising_campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('room_id');
            $table->unsignedInteger('players_count');
            $table->string('language', 8)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('billing_reference', 100)->unique();
            $table->unsignedBigInteger('ledger_entry_id');
            $table->timestamp('delivered_at');

            $table->index(['campaign_id', 'delivered_at']);
        });
    }

    public function down(): void
    {
        foreach (['sponsored_question_deliveries', 'advertising_impressions', 'ad_deliveries', 'advertising_ledger_entries', 'advertising_creatives', 'advertising_campaigns', 'advertiser_wallets', 'advertising_packages', 'advertisers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
