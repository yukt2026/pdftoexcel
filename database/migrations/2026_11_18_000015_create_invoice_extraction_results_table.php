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
        Schema::create('invoice_extraction_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Extracted Invoice-Level Core Fields
            $table->string('invoice_number')->nullable()->index();
            $table->string('invoice_date')->nullable();
            $table->string('po_number')->nullable();
            $table->string('order_number')->nullable()->index();
            $table->string('buyer_name')->nullable();
            $table->text('buyer_address')->nullable();
            $table->string('buyer_state')->nullable();
            $table->string('place_of_supply')->nullable();

            // Financial Summaries
            $table->decimal('total_taxable_value', 12, 2)->default(0.00);
            $table->decimal('total_cgst', 12, 2)->default(0.00);
            $table->decimal('total_sgst', 12, 2)->default(0.00);
            $table->decimal('total_igst', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);

            // Storage for Raw & Structured Payloads
            $table->json('extracted_data'); // Structured JSON with invoice & product levels
            $table->longText('raw_text')->nullable(); // Original extracted text input
            $table->json('ai_raw_response')->nullable(); // Raw JSON returned from Gemini
            $table->string('ai_model', 50)->nullable(); // e.g. gemini-1.5-flash

            // Extraction Metrics & Validation
            $table->decimal('overall_confidence', 5, 2)->default(90.00);
            $table->json('field_confidence_scores')->nullable();
            $table->string('status', 30)->default('completed')->index(); // completed, failed, review_required
            $table->json('validation_errors')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_extraction_results');
    }
};
