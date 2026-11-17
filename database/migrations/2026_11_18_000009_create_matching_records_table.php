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
        Schema::create('matching_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_document_id')->nullable()->constrained('shipping_documents')->nullOnDelete();
            $table->string('po_reference', 100)->nullable()->index();
            $table->string('match_type', 30)->default('2_way'); // 2_way, 3_way, manual
            $table->string('status', 30)->default('pending')->index(); // exact_match, within_tolerance, quantity_mismatch, price_mismatch, missing_doc, rejected, approved
            $table->json('discrepancy_details')->nullable();
            $table->decimal('tolerance_percentage', 5, 2)->default(0);
            $table->foreignId('matched_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matching_records');
    }
};
