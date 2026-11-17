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
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('document_id')->nullable()->after('customer_id')->constrained('documents')->nullOnDelete();
            $table->string('order_number', 100)->nullable()->after('po_number')->index();
            $table->string('buyer_name')->nullable()->after('order_number');
            $table->text('buyer_address')->nullable()->after('buyer_name');
            $table->string('buyer_state', 100)->nullable()->after('buyer_address');
            $table->string('place_of_supply', 100)->nullable()->after('buyer_state');
            $table->decimal('cgst_amount', 15, 2)->default(0.00)->after('tax_amount');
            $table->decimal('sgst_amount', 15, 2)->default(0.00)->after('cgst_amount');
            $table->decimal('igst_amount', 15, 2)->default(0.00)->after('sgst_amount');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('item_rate', 15, 4)->default(0.00)->after('unit_price');
            $table->decimal('taxable_value', 15, 2)->default(0.00)->after('discount');
            $table->decimal('cgst', 15, 2)->default(0.00)->after('tax_amount');
            $table->decimal('sgst', 15, 2)->default(0.00)->after('cgst');
            $table->decimal('igst', 15, 2)->default(0.00)->after('sgst');
            $table->boolean('is_extra_charge')->default(false)->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['item_rate', 'taxable_value', 'cgst', 'sgst', 'igst', 'is_extra_charge']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->dropColumn([
                'document_id',
                'order_number',
                'buyer_name',
                'buyer_address',
                'buyer_state',
                'place_of_supply',
                'cgst_amount',
                'sgst_amount',
                'igst_amount',
            ]);
        });
    }
};
