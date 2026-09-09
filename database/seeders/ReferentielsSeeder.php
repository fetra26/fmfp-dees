<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferentielsSeeder extends Seeder
{
    public function run(): void
    {
        // ─── GUICHETS (vrais codes DEES) ─────────────────────────
        $guichets = [
            ['code' => 'RIE',           'libelle' => 'RIE'],
            ['code' => 'PIS',           'libelle' => 'PIS'],
            ['code' => 'PII',           'libelle' => 'PII'],
            ['code' => 'INP',           'libelle' => 'INP'],
            ['code' => 'EQUITE',        'libelle' => 'EQUITE'],
            ['code' => 'ALTERNANCE',    'libelle' => 'ALTERNANCE'],
            ['code' => 'APPRENTISSAGE', 'libelle' => 'APPRENTISSAGE'],
        ];
        foreach ($guichets as $g) {
            DB::table('guichet')->updateOrInsert(['code' => $g['code']], array_merge($g, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // ─── RÉGIONS (23 régions Madagascar - source torolalana.gov.mg, ordre officiel par ex-province) ──────────
        // NB : depuis 2021, la région Vatovavy-Fitovinany a été scindée en Vatovavy et Fitovinany.
        $regions = [
            // Ex-province Antananarivo
            ['code' => 'R01', 'libelle' => 'Analamanga',        'chef_lieu' => 'Antananarivo'],
            ['code' => 'R02', 'libelle' => 'Vakinankaratra',    'chef_lieu' => 'Antsirabe'],
            ['code' => 'R03', 'libelle' => 'Itasy',             'chef_lieu' => 'Miarinarivo'],
            ['code' => 'R04', 'libelle' => 'Bongolava',         'chef_lieu' => 'Tsiroanomandidy'],
            // Ex-province Fianarantsoa
            ['code' => 'R05', 'libelle' => 'Haute Matsiatra',   'chef_lieu' => 'Fianarantsoa'],
            ['code' => 'R06', 'libelle' => 'Amoron\'i Mania',   'chef_lieu' => 'Ambositra'],
            ['code' => 'R07', 'libelle' => 'Vatovavy',          'chef_lieu' => 'Mananjary'],
            ['code' => 'R08', 'libelle' => 'Fitovinany',        'chef_lieu' => 'Manakara'],
            ['code' => 'R09', 'libelle' => 'Atsimo-Atsinanana', 'chef_lieu' => 'Farafangana'],
            ['code' => 'R10', 'libelle' => 'Ihorombe',          'chef_lieu' => 'Ihosy'],
            // Ex-province Toamasina
            ['code' => 'R11', 'libelle' => 'Atsinanana',        'chef_lieu' => 'Toamasina'],
            ['code' => 'R12', 'libelle' => 'Analanjirofo',      'chef_lieu' => 'Fenoarivo Atsinanana'],
            ['code' => 'R13', 'libelle' => 'Alaotra-Mangoro',   'chef_lieu' => 'Ambatondrazaka'],
            // Ex-province Mahajanga
            ['code' => 'R14', 'libelle' => 'Boeny',             'chef_lieu' => 'Mahajanga'],
            ['code' => 'R15', 'libelle' => 'Sofia',             'chef_lieu' => 'Antsohihy'],
            ['code' => 'R16', 'libelle' => 'Betsiboka',         'chef_lieu' => 'Maevatanana'],
            ['code' => 'R17', 'libelle' => 'Melaky',            'chef_lieu' => 'Maintirano'],
            // Ex-province Toliara
            ['code' => 'R18', 'libelle' => 'Atsimo-Andrefana',  'chef_lieu' => 'Toliara'],
            ['code' => 'R19', 'libelle' => 'Androy',            'chef_lieu' => 'Ambovombe'],
            ['code' => 'R20', 'libelle' => 'Anosy',             'chef_lieu' => 'Tolagnaro'],
            ['code' => 'R21', 'libelle' => 'Menabe',            'chef_lieu' => 'Morondava'],
            // Ex-province Antsiranana
            ['code' => 'R22', 'libelle' => 'Diana',             'chef_lieu' => 'Antsiranana'],
            ['code' => 'R23', 'libelle' => 'Sava',              'chef_lieu' => 'Sambava'],
        ];
        foreach ($regions as $r) {
            DB::table('region')->updateOrInsert(['code' => $r['code']], [
                'code' => $r['code'], 'libelle' => $r['libelle'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ─── SECTEURS ─────────────────────────────────────────────
        $secteurs = [
            ['code' => 'AGR', 'libelle' => 'Agriculture / Élevage / Pêche'],
            ['code' => 'ART', 'libelle' => 'Artisanat / Transformation'],
            ['code' => 'BTP', 'libelle' => 'BTP / Génie civil'],
            ['code' => 'COM', 'libelle' => 'Commerce / Distribution'],
            ['code' => 'IND', 'libelle' => 'Industrie / Manufacture'],
            ['code' => 'NUM', 'libelle' => 'Numérique / TIC'],
            ['code' => 'SAN', 'libelle' => 'Santé / Social'],
            ['code' => 'TOU', 'libelle' => 'Tourisme / Hôtellerie'],
            ['code' => 'TRA', 'libelle' => 'Transport / Logistique'],
            ['code' => 'ENV', 'libelle' => 'Environnement / Énergie'],
            ['code' => 'EDU', 'libelle' => 'Éducation / Formation'],
            ['code' => 'EQU', 'libelle' => 'Équité'],
            ['code' => 'AUT', 'libelle' => 'Autres'],
        ];
        foreach ($secteurs as $s) {
            DB::table('secteur')->updateOrInsert(['code' => $s['code']], array_merge($s, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        // ─── STATUTS PROJET (vrais statuts DEES) ──────────────────
        $statuts = [
            ['code' => 'stand_by',              'libelle' => 'Stand by',                  'ordre' => 1],
            ['code' => 'attente_pieces_regul',  'libelle' => 'Attente pièces régul.',     'ordre' => 2],
            ['code' => 'validation_financiere', 'libelle' => 'Validation financière',     'ordre' => 3],
            ['code' => 'formation_encours',     'libelle' => 'Formation en cours',        'ordre' => 4],
            ['code' => 'cloture',               'libelle' => 'Clôturé',                   'ordre' => 5],
            ['code' => 'annule',                'libelle' => 'Annulé',                    'ordre' => 6],
            // Statuts historiques (conservés pour anciens imports)
            ['code' => 'incomplet',             'libelle' => 'Incomplet',                 'ordre' => 90],
            ['code' => 'valide',                'libelle' => 'Validé',                    'ordre' => 91],
            ['code' => 'refuse',                'libelle' => 'Refusé',                    'ordre' => 92],
        ];
        foreach ($statuts as $s) {
            DB::table('statut_projet')->updateOrInsert(['code' => $s['code']], array_merge($s, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        // ─── TYPES DANO (vrais types DEES) ────────────────────────
        $typesDano = [
            ['code' => 'changement_date',     'libelle' => 'Changement de date'],
            ['code' => 'changement_formateur','libelle' => 'Changement de formateur'],
            ['code' => 'reamenagement_budget','libelle' => 'Réaménagement budgétaire'],
        ];
        foreach ($typesDano as $t) {
            DB::table('type_dano')->updateOrInsert(['code' => $t['code']], array_merge($t, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        // ─── TYPES RELANCE ────────────────────────────────────────
        $typesRelance = [
            ['code' => 'preventive',   'libelle' => 'Relance préventive (alerte verte)'],
            ['code' => 'mise_demeure', 'libelle' => 'Lettre de mise en demeure (alerte orange)'],
            ['code' => 'resiliation',  'libelle' => 'Procédure de résiliation (alerte rouge)'],
            ['code' => 'courrier',     'libelle' => 'Courrier officiel'],
            ['code' => 'email',        'libelle' => 'Email'],
            ['code' => 'telephone',    'libelle' => 'Téléphone'],
        ];
        foreach ($typesRelance as $t) {
            DB::table('type_relance')->updateOrInsert(['code' => $t['code']], array_merge($t, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        // ─── SITUATIONS ALLOCATION ────────────────────────────────
        $situationsAlloc = [
            ['code' => 'non_verse',  'libelle' => 'Non versé'],
            ['code' => 'partiel',    'libelle' => 'Partiellement versé'],
            ['code' => 'total',      'libelle' => 'Totalement versé'],
            ['code' => 'solde',      'libelle' => 'Soldé / Clôturé'],
            ['code' => 'annule',     'libelle' => 'Annulé'],
            ['code' => 'remboursm',  'libelle' => 'Remboursement'],
        ];
        foreach ($situationsAlloc as $s) {
            DB::table('situation_alloc')->updateOrInsert(['code' => $s['code']], array_merge($s, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        $this->command->info('✔ Référentiels seedés (guichets DEES, régions, secteurs, statuts DEES, types DANO).');
    }
}
