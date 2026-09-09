<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute des colonnes "reference_normalisee" pour le matching souple à l'import :
 *   STELLARIX_2026_001 = STELLARIX-2026-001 = stellarix 2026 001 = STELLARIX2026001
 *
 * Elles sont remplies automatiquement par les modèles (mutateur setReferenceAttribute).
 * Backfill : on remplit les valeurs existantes en fin de migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- projet.reference_normalisee ---
        Schema::table('projet', function (Blueprint $table) {
            $table->string('reference_normalisee', 100)
                ->nullable()
                ->after('reference')
                ->index();
        });

        // --- convention.reference_normalisee ---
        Schema::table('convention', function (Blueprint $table) {
            $table->string('reference_normalisee', 150)
                ->nullable()
                ->after('reference')
                ->index();
        });

        // --- porteur_proj.reference_convention_normalisee ---
        Schema::table('porteur_proj', function (Blueprint $table) {
            $table->string('reference_convention_normalisee', 255)
                ->nullable()
                ->after('reference_convention')
                ->index();
        });

        // Backfill : on normalise l'existant
        $normaliser = function (?string $ref): ?string {
            if ($ref === null || trim($ref) === '') return null;
            $ref = mb_strtoupper(trim($ref));
            $ref = preg_replace('/[\s\-_\/\.]+/', '', $ref);
            return $ref !== '' ? $ref : null;
        };

        DB::table('projet')->orderBy('id')->chunk(500, function ($rows) use ($normaliser) {
            foreach ($rows as $row) {
                DB::table('projet')
                    ->where('id', $row->id)
                    ->update(['reference_normalisee' => $normaliser($row->reference)]);
            }
        });

        DB::table('convention')->orderBy('id')->chunk(500, function ($rows) use ($normaliser) {
            foreach ($rows as $row) {
                DB::table('convention')
                    ->where('id', $row->id)
                    ->update(['reference_normalisee' => $normaliser($row->reference)]);
            }
        });

        DB::table('porteur_proj')->orderBy('id')->chunk(500, function ($rows) use ($normaliser) {
            foreach ($rows as $row) {
                DB::table('porteur_proj')
                    ->where('id', $row->id)
                    ->update(['reference_convention_normalisee' => $normaliser($row->reference_convention)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('projet', function (Blueprint $table) {
            $table->dropIndex(['reference_normalisee']);
            $table->dropColumn('reference_normalisee');
        });

        Schema::table('convention', function (Blueprint $table) {
            $table->dropIndex(['reference_normalisee']);
            $table->dropColumn('reference_normalisee');
        });

        Schema::table('porteur_proj', function (Blueprint $table) {
            $table->dropIndex(['reference_convention_normalisee']);
            $table->dropColumn('reference_convention_normalisee');
        });
    }
};
