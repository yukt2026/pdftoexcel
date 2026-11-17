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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('file_type', 30)->default('pdf');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->integer('page_count')->default(1);
            $table->string('mime_type');
            $table->string('hash', 64)->nullable()->index();
            $table->string('status', 30)->default('uploaded')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
