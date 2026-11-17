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
            if (!Schema::hasColumn('invoices', 'shipping_id')) {
                $table->string('shipping_id', 100)->nullable()->after('order_number')->index();
            }
            if (!Schema::hasColumn('invoices', 'is_manually_edited')) {
                $table->boolean('is_manually_edited')->default(false)->after('processing_status');
            }
        });

        Schema::create('invoice_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->string('field_name', 100)->index();
            $table->text('original_value')->nullable();
            $table->text('updated_value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'field_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_corrections');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['shipping_id', 'is_manually_edited']);
        });
    }
};
