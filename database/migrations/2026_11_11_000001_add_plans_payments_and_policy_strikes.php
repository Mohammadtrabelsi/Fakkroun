<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Player plans: free (with ads) or paid (ad-free).
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan', 16)->default('free')->after('status');
            $table->timestamp('plan_expires_at')->nullable()->after('plan');
        });

        // Provider-agnostic payment records. The provider itself is behind
        // App\Domain\Billing\Contracts\PaymentProvider.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 32);              // player_plan | advertising_pack
            $table->string('provider', 32);             // manual | <gateway>
            $table->string('provider_reference')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 16)->default('pending'); // pending | paid | failed | cancelled | refunded
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['user_id', 'status']);
        });

        // Advertiser policy breaches: 1st warning, 2nd call, 3rd suspension.
        Schema::create('advertiser_policy_strikes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertiser_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('advertising_campaigns')->nullOnDelete();
            $table->unsignedTinyInteger('level');
            $table->text('note');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('created_at');

            $table->unique(['advertiser_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertiser_policy_strikes');
        Schema::dropIfExists('payments');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['plan', 'plan_expires_at']);
        });
    }
};
