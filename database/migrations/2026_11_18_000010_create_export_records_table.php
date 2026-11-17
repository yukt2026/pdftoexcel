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
        Schema::create('export_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('export_name');
            $table->string('format', 10)->default('xlsx'); // xlsx, csv, xls
            $table->string('file_path');
            $table->integer('total_records')->default(0);
            $table->json('filter_criteria')->nullable();
            $table->string('status', 30)->default('processing'); // processing, completed, failed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_records');
    }
};
