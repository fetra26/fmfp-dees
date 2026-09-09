<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- convention ---
        Schema::create('convention', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 100)->unique();
            $table->date('date_signature')->nullable();
            $table->date('date_effet')->nullable();
            $table->date('date_expiration')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        // --- projet ---
        Schema::create('projet', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->unique();
            $table->string('intitule', 300);
            $table->foreignId('convention_id')->nullable()->constrained('convention')->nullOnDelete();
            $table->foreignId('statut_projet_id')->nullable()->constrained('statut_projet')->nullOnDelete();
            $table->foreignId('guichet_id')->nullable()->constrained('guichet')->nullOnDelete();
            $table->foreignId('vague_id')->nullable()->constrained('vague')->nullOnDelete();
            $table->foreignId('secteur_id')->nullable()->constrained('secteur')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained('region')->nullOnDelete();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projet');
        Schema::dropIfExists('convention');
    }
};
