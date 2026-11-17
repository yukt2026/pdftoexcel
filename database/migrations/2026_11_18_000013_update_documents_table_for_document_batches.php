<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('id')->constrained('document_batches')->nullOnDelete();
            $table->string('document_type', 30)->default('invoice')->after('user_id')->index();
            $table->string('file_hash', 64)->nullable()->after('file_size')->index();
            $table->integer('total_pages')->default(1)->after('file_hash');
            $table->string('extraction_status', 30)->default('pending')->after('total_pages')->index();
        });

        // Sync existing columns if records exist
        DB::table('documents')->update([
            'file_hash' => DB::raw('hash'),
            'total_pages' => DB::raw('page_count'),
            'extraction_status' => DB::raw('status'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn(['batch_id', 'document_type', 'file_hash', 'total_pages', 'extraction_status']);
        });
    }
};
