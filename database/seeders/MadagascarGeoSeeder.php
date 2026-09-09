<?php

namespace Database\Seeders;

use App\Models\ImportMapping;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeder pour la géographie de Madagascar :
 * - 23 régions officielles
 * - ~117 districts
 * - ~1500 communes
 * - ~17 000 fokontany
 *
 * Source : https://github.com/julkwel/madagascar-map
 * Fichiers JSON copiés localement dans database/data/madagascar/
 *
 * Peut être relancé sans casser les données existantes (updateOrInsert).
 */
class MadagascarGeoSeeder extends Seeder
{
    private const DATA_DIR = 'database/data/madagascar';

    public function run(): void
    {
        // Utilise fokontany_par_commune_data comme source (contient tout : region, district, commune, fokontany)
        $chemin = base_path(self::DATA_DIR . '/liste_fokontany_par_commune_data.json');
        if (! file_exists($chemin)) {
            $this->command->error("Fichier JSON introuvable : $chemin");
            return;
        }

        $json = json_decode(file_get_contents($chemin), true);
        if (! is_array($json)) {
            $this->command->error("JSON invalide : $chemin");
            return;
        }

        // Suppression de la ligne d'en-tête si présente (première entrée est "Region": {...})
        unset($json['Region']);

        $this->command->info('▸ Seed géographie de Madagascar…');

        Schema::disableForeignKeyConstraints();

        // ─── 1) RÉGIONS ────────────────────────────────────────
        $regionsById = [];
        $codeIdx = 1;
        foreach ($json as $regionNom => $communes) {
            $libelle = ImportMapping::formaterLibelle($regionNom) ?: $regionNom;
            $libelle = Str::limit($libelle, 150, '');

            $existant = DB::table('region')->where('libelle', $libelle)->first();
            if ($existant) {
                $regionsById[$regionNom] = $existant->id;
                continue;
            }

            $code = 'R' . str_pad($codeIdx++, 2, '0', STR_PAD_LEFT);
            while (DB::table('region')->where('code', $code)->exists()) {
                $code = 'R' . str_pad($codeIdx++, 2, '0', STR_PAD_LEFT);
            }

            $regionsById[$regionNom] = DB::table('region')->insertGetId([
                'code' => $code,
                'libelle' => $libelle,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('  ✓ Régions : ' . count($regionsById));

        // ─── 2) DISTRICTS + COMMUNES + FOKONTANY ────────────────
        $districtsCount = 0;
        $communesCount = 0;
        $fokontanysCount = 0;

        foreach ($json as $regionNom => $communes) {
            $regionId = $regionsById[$regionNom] ?? null;
            if (! $regionId || ! is_array($communes)) continue;

            // Cache districts pour cette région
            $districtsRegion = [];

            foreach ($communes as $communeNom => $fokontanys) {
                if (! is_array($fokontanys)) continue;

                // Extraire district depuis la 1ère entrée fokontany
                $districtNom = null;
                foreach ($fokontanys as $fok) {
                    if (is_array($fok) && filled($fok['district'] ?? null)) {
                        $districtNom = $fok['district'];
                        break;
                    }
                }

                // Créer/retrouver le district
                $districtId = null;
                if (filled($districtNom)) {
                    $districtNorm = ImportMapping::normaliser($districtNom);
                    if (! isset($districtsRegion[$districtNorm])) {
                        $lib = Str::limit(ImportMapping::formaterLibelle($districtNom) ?: $districtNom, 150, '');
                        $existDistrict = DB::table('district')
                            ->where('region_id', $regionId)
                            ->where('libelle_normalise', $districtNorm)
                            ->first();
                        if ($existDistrict) {
                            $districtsRegion[$districtNorm] = $existDistrict->id;
                        } else {
                            $districtsRegion[$districtNorm] = DB::table('district')->insertGetId([
                                'region_id' => $regionId,
                                'libelle' => $lib,
                                'libelle_normalise' => $districtNorm,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $districtsCount++;
                        }
                    }
                    $districtId = $districtsRegion[$districtNorm];
                }

                // Créer/retrouver la commune
                $communeNorm = ImportMapping::normaliser($communeNom);
                $libCommune = Str::limit(ImportMapping::formaterLibelle($communeNom) ?: $communeNom, 150, '');
                $existCommune = DB::table('commune')
                    ->where('region_id', $regionId)
                    ->where('libelle_normalise', $communeNorm)
                    ->first();
                if ($existCommune) {
                    $communeId = $existCommune->id;
                } else {
                    $communeId = DB::table('commune')->insertGetId([
                        'region_id' => $regionId,
                        'district_id' => $districtId,
                        'libelle' => $libCommune,
                        'libelle_normalise' => $communeNorm,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $communesCount++;
                }

                // Créer les fokontany en batch (INSERT IGNORE-like via chunks)
                $fokontanyBatch = [];
                $now = now();
                foreach ($fokontanys as $fok) {
                    $fokNom = is_array($fok) ? ($fok['fokontany'] ?? null) : null;
                    if (blank($fokNom) || $fokNom === 'Fokontany') continue;

                    $fokNorm = ImportMapping::normaliser($fokNom);
                    $libFok = Str::limit(ImportMapping::formaterLibelle($fokNom) ?: $fokNom, 150, '');

                    $fokontanyBatch[] = [
                        'commune_id' => $communeId,
                        'district_id' => $districtId,
                        'region_id' => $regionId,
                        'libelle' => $libFok,
                        'libelle_normalise' => $fokNorm,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (! empty($fokontanyBatch)) {
                    // Insertion par lots de 200 pour performance
                    foreach (array_chunk($fokontanyBatch, 200) as $chunk) {
                        DB::table('fokontany')->insert($chunk);
                    }
                    $fokontanysCount += count($fokontanyBatch);
                }
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->command->info('  ✓ Districts : ' . $districtsCount);
        $this->command->info('  ✓ Communes : ' . $communesCount);
        $this->command->info('  ✓ Fokontany : ' . $fokontanysCount);
        $this->command->info('✔ Géographie de Madagascar seedée.');
    }
}
