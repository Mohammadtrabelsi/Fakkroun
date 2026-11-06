<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story 16 S11: one invoice per recorded pack purchase, numbered without
 * gaps per year. Invoices are never edited; a cancellation is a credit note
 * with its own number pointing at the invoice it cancels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16)->default('invoice'); // invoice | credit_note
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->string('number', 40)->unique();
            $table->foreignId('advertiser_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('advertising_ledger_entries')->restrictOnDelete();
            $table->foreignId('cancels_invoice_id')->nullable()->constrained('advertising_invoices')->restrictOnDelete();
            $table->string('package_name')->nullable();
            $table->unsignedBigInteger('impression_credits')->default(0);
            $table->unsignedBigInteger('sponsored_question_credits')->default(0);
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('payment_reference')->nullable();
            $table->text('note')->nullable();
            // Seller and buyer details as they were when issued.
            $table->json('seller');
            $table->json('buyer');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');

            $table->unique(['year', 'sequence']);
            $table->unique(['ledger_entry_id', 'kind']);
            $table->index(['advertiser_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_invoices');
    }
};
