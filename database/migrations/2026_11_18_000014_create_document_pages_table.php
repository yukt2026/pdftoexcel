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
        // Add text extraction and OCR tracking columns to documents
        Schema::table('documents', function (Blueprint $table) {
            $table->longText('raw_text')->nullable()->after('error_message');
            $table->json('page_wise_text')->nullable()->after('raw_text');
            $table->boolean('is_scanned')->default(false)->after('page_wise_text');
            $table->boolean('has_password')->default(false)->after('is_scanned');
            $table->string('ocr_status', 30)->default('none')->after('has_password');
            $table->string('ocr_provider', 50)->nullable()->after('ocr_status');
        });

        // Create page-wise granular storage table
        Schema::create('document_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->integer('page_number');
            $table->mediumText('extracted_text')->nullable();
            $table->integer('char_count')->default(0);
            $table->boolean('is_scanned')->default(false);
            $table->boolean('ocr_applied')->default(false);
            $table->string('ocr_provider', 50)->nullable();
            $table->string('status', 30)->default('completed'); // completed, failed, empty
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'page_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_pages');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'raw_text',
                'page_wise_text',
                'is_scanned',
                'has_password',
                'ocr_status',
                'ocr_provider',
            ]);
        });
    }
};
