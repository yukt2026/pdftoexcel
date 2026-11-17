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
        Schema::create('shipping_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('document_page_id')->nullable()->constrained('document_pages')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            // 8 Core Required Extraction Fields
            $table->string('awb_number', 100)->index(); // Shipping ID / AWB Number
            $table->string('original_awb_number', 100)->nullable(); // Preserved original identifier
            $table->string('order_number', 100)->nullable()->index(); // Order Number
            $table->string('buyer_name')->nullable(); // Buyer Name
            $table->text('shipping_address')->nullable(); // Complete Shipping Address
            $table->string('state', 100)->nullable(); // State
            $table->string('pincode', 20)->nullable()->index(); // Pincode
            $table->string('courier_name', 100)->nullable(); // Courier Name (e.g. E-Kart Logistics, Delhivery)
            $table->string('marketplace_name', 100)->nullable(); // Marketplace Name (e.g. Flipkart, Meesho, Amazon)

            // Extraction Metadata & Rules
            $table->string('label_format', 50)->default('standard_courier')->index(); // flipkart_ekart, delhivery, standard_courier
            $table->integer('page_number')->default(1);
            $table->string('extraction_method', 30)->default('deterministic'); // deterministic, gemini_ai, hybrid, ocr
            $table->decimal('confidence_score', 5, 2)->default(90.00);
            $table->json('field_confidence_scores')->nullable();

            // Manual Correction Tracking
            $table->boolean('is_manually_corrected')->default(false)->index();
            $table->text('correction_notes')->nullable();

            // Barcode & QR code support
            $table->string('barcode_data')->nullable();
            $table->text('qr_data')->nullable();

            // Raw Payloads & Status
            $table->longText('raw_text')->nullable();
            $table->json('raw_data')->nullable();
            $table->string('status', 30)->default('extracted')->index(); // extracted, matched, unmatched, discrepancy

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_labels');
    }
};
