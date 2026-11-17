<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('invoice_number', 100)->nullable()->index();
            $table->date('invoice_date')->nullable()->index();
            $table->date('due_date')->nullable();
            $table->string('po_number', 100)->nullable()->index();
            $table->string('currency', 10)->default('INR');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->string('payment_terms', 100)->nullable();
            $table->string('payment_status', 30)->default('unpaid')->index(); // unpaid, partially_paid, paid, overdue
            $table->string('processing_status', 30)->default('pending')->index(); // pending, processing, extracted, review_needed, validated, exported, failed
            $table->string('matching_status', 30)->default('unmatched')->index(); // unmatched, matched, mismatch, waived, not_applicable
            $table->string('extraction_method', 30)->default('hybrid'); // pdf_parser, ocr, gemini_ai, hybrid
            $table->decimal('ai_confidence_score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->longText('raw_extracted_text')->nullable();
            $table->json('ai_raw_response')->nullable();
            $table->json('meta_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
