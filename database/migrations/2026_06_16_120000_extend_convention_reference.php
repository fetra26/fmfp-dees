<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Certaines références de convention DEES dépassent 100 caractères
        Schema::table('convention', function (Blueprint $table) {
            $table->string('reference', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('convention', function (Blueprint $table) {
            $table->string('reference', 100)->change();
        });
    }
};
