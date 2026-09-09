<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('porteur_proj', function (Blueprint $table) {
            $table->string('reference_convention', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('porteur_proj', function (Blueprint $table) {
            $table->string('reference_convention', 100)->nullable()->change();
        });
    }
};
