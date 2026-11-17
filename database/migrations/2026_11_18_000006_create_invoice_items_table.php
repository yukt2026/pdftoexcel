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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('item_code', 100)->nullable()->index();
            $table->text('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->string('category', 100)->nullable();
            $table->unsignedBigInteger('matched_po_item_id')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
