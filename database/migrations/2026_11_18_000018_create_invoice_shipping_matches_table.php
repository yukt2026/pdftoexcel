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
        Schema::create('invoice_shipping_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('shipping_label_id')->nullable()->constrained('shipping_labels')->cascadeOnDelete();

            // Identifiers for quick lookup
            $table->string('order_number', 100)->nullable()->index();
            $table->string('awb_number', 100)->nullable()->index();
            $table->string('invoice_number', 100)->nullable()->index();

            // Status: matched, unmatched, needs_review, duplicate
            $table->string('status', 30)->default('matched')->index();

            // Matching Method & Priority
            // exact_order_number, exact_po_number, normalized_order_number, suffix_stripped_order_number, manual_match, manual_correction
            $table->string('matching_method', 50)->nullable()->index();
            $table->decimal('confidence_score', 5, 2)->default(90.00);

            // Secondary Verification Details (Buyer name, address, pin)
            $table->json('secondary_verification')->nullable();
            $table->json('discrepancy_details')->nullable();

            // Audit and Manual Corrections
            $table->boolean('is_manually_matched')->default(false)->index();
            $table->foreignId('matched_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Prevent duplicate AWB assignments to multiple active matches
            // Handled via application rules and index
            $table->index(['shipping_label_id', 'status']);
            $table->index(['invoice_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_shipping_matches');
    }
};
