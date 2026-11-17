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
        Schema::create('shipping_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('document_type', 50)->default('waybill'); // waybill, bill_of_lading, packing_list, delivery_challan
            $table->string('tracking_number', 100)->nullable()->index();
            $table->string('carrier_name', 100)->nullable();
            $table->date('shipped_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->text('origin_address')->nullable();
            $table->text('destination_address')->nullable();
            $table->decimal('total_weight', 10, 2)->nullable();
            $table->string('weight_unit', 10)->default('kg');
            $table->integer('total_packages')->nullable();
            $table->decimal('shipping_cost', 15, 2)->nullable();
            $table->string('status', 30)->default('pending')->index(); // pending, in_transit, delivered, matched, discrepancy
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_documents');
    }
};
