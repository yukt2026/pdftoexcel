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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('admin')->after('password');
            $table->string('status', 20)->default('active')->after('role');
            $table->string('phone', 30)->nullable()->after('status');
            $table->string('avatar')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'status', 'phone', 'avatar']);
        });
    }
};
