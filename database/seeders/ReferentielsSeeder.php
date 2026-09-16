<?php

namespace Database\Seeders;

use App\Models\ImportMapping;
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

        // ─── SECTEURS (nomenclature officielle FMFP — 11 secteurs) ────
        // Liste FERMÉE : l'import ne doit jamais en créer d'autres, sous peine
        // de refragmenter les statistiques du tableau de bord, qui agrège par
        // libellé. Toute graphie inattendue passe par le wizard de préflight.
        //
        // Le code est en UPPER_SNAKE_CASE : normalisé, il rejoint son propre
        // libellé (MULTI_EDUCATION et « Multi éducation » donnent tous deux
        // MULTIEDUCATION), ce qui fait reconnaître automatiquement la plupart
        // des graphies du fichier.
        $secteurs = [
            ['code' => 'THA',             'libelle' => 'Textile / Habillement et Accessoires'],
            ['code' => 'THR',             'libelle' => 'Tourisme / Hôtellerie / Restauration'],
            ['code' => 'TIC',             'libelle' => "Technologies de l'Information et de la Communication"],
            ['code' => 'BTP_RS',          'libelle' => 'Bâtiment / Travaux Publics / Ressources Stratégiques'],
            ['code' => 'DR',              'libelle' => 'Développement Rural'],
            ['code' => 'MULTI_EDUCATION', 'libelle' => 'Multi éducation'],
            ['code' => 'MULTI_SANTE',     'libelle' => 'Multi santé'],
            ['code' => 'MULTI_TRANSPORT', 'libelle' => 'Multi transport'],
            ['code' => 'MULTI_INDUSTRIE', 'libelle' => 'Multi industrie'],
            ['code' => 'MULTI_COMMERCE',  'libelle' => 'Multi commerce'],
            ['code' => 'MULTI_AUTRE',     'libelle' => 'Multi autre'],
        ];
        foreach ($secteurs as $s) {
            DB::table('secteur')->updateOrInsert(['code' => $s['code']], array_merge($s, [
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        // ─── Retrait des secteurs hors nomenclature ───────────────
        // Les anciens secteurs génériques (AGR, ART, COM…) et ceux inventés
        // par les imports successifs faussaient la répartition. On ne supprime
        // QUE ceux qu'aucun projet ni porteur ne référence : un secteur encore
        // utilisé est conservé et signalé, à rattacher manuellement.
        $codesOfficiels = array_column($secteurs, 'code');
        $horsNomenclature = DB::table('secteur')->whereNotIn('code', $codesOfficiels)->get(['id', 'code', 'libelle']);
        $supprimes = 0;
        $conserves = [];

        foreach ($horsNomenclature as $obsolete) {
            $utilise = DB::table('projet')->where('secteur_id', $obsolete->id)->exists()
                || DB::table('porteur')->where('secteur_id', $obsolete->id)->exists();

            if ($utilise) {
                $conserves[] = $obsolete->libelle;
                continue;
            }

            DB::table('secteur')->where('id', $obsolete->id)->delete();
            $supprimes++;
        }

        if ($supprimes > 0) {
            $this->command->info("  ↳ {$supprimes} secteur(s) hors nomenclature supprimé(s).");
        }
        if ($conserves !== []) {
            $this->command->warn('  ↳ Conservés car encore référencés : ' . implode(', ', $conserves));
        }

        $this->seedAliasSecteurs();

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

    /**
     * Alias de rapprochement des secteurs.
     *
     * Les fichiers de la DEES nomment souvent un secteur par un seul mot, là où
     * la nomenclature officielle en fait un « Multi » : « SANTE » désigne
     * « Multi santé », « BTP » désigne « BTP/RS ». Sans ces alias, chaque
     * variante repartirait en valeur inconnue à traiter à la main.
     *
     * Ces correspondances alimentent import_mapping, la mémoire que consultent
     * déjà le PreflightScanner et l'import. Un arbitrage rendu ici n'est donc
     * plus jamais redemandé.
     *
     * Note : les graphies qui ne diffèrent que par la casse, les accents, les
     * espaces ou les séparateurs n'ont PAS besoin d'alias — la normalisation
     * les ramène d'elle-même au code (« btp-rs », « BTP / RS » et « BTP_RS »
     * donnent tous BTPRS).
     */
    private function seedAliasSecteurs(): void
    {
        $alias = [
            // ── Mot seul → le « Multi » correspondant (règle donnée par la DEES)
            // « MULTI » seul, sans domaine précisé, n'est pas rattachable à un
            // secteur particulier : il tombe dans le fourre-tout prévu pour ça.
            'MULTI_AUTRE'     => ['AUTRE', 'AUTRES', 'DIVERS', 'MULTI', 'MULTI AUTRES'],
            'MULTI_SANTE'     => ['SANTE', 'SANTE SOCIAL', 'SANITAIRE'],
            'MULTI_EDUCATION' => ['EDUCATION', 'EDUCATION FORMATION', 'ENSEIGNEMENT', 'FORMATION'],
            'MULTI_TRANSPORT' => ['TRANSPORT', 'TRANSPORT LOGISTIQUE', 'LOGISTIQUE'],
            'MULTI_INDUSTRIE' => ['INDUSTRIE', 'INDUSTRIE MANUFACTURE', 'MANUFACTURE'],
            'MULTI_COMMERCE'  => ['COMMERCE', 'COMMERCE DISTRIBUTION', 'DISTRIBUTION'],

            // ── Forme abrégée → secteur complet
            'BTP_RS'          => ['BTP', 'BATIMENT', 'TRAVAUX PUBLICS', 'GENIE CIVIL', 'RESSOURCES STRATEGIQUES'],
            'THA'             => ['TEXTILE', 'HABILLEMENT', 'TEXTILE HABILLEMENT', 'ACCESSOIRES'],
            'THR'             => ['TOURISME', 'HOTELLERIE', 'RESTAURATION', 'TOURISME HOTELLERIE'],
            'TIC'             => ['NUMERIQUE', 'INFORMATIQUE', 'TELECOMMUNICATION', 'TECHNOLOGIE'],
            'DR'              => ['RURAL', 'DEVELOPPEMENT RURAL', 'AGRICULTURE', 'ELEVAGE', 'PECHE', 'AGRICULTURE ELEVAGE PECHE'],
        ];

        $secteurs = DB::table('secteur')->pluck('id', 'code');
        $crees = 0;

        foreach ($alias as $code => $variantes) {
            if (! isset($secteurs[$code])) {
                continue;
            }

            $libelle = DB::table('secteur')->where('code', $code)->value('libelle');

            foreach ($variantes as $variante) {
                $normalisee = ImportMapping::normaliser($variante);
                if ($normalisee === null) {
                    continue;
                }

                DB::table('import_mapping')->updateOrInsert(
                    ['referentiel' => 'secteur', 'valeur_saisie_normalisee' => $normalisee],
                    [
                        'valeur_saisie' => $variante,
                        'action'        => 'map',
                        'target_id'     => $secteurs[$code],
                        'target_label'  => $libelle,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]
                );
                $crees++;
            }
        }

        $this->command->info("✔ {$crees} alias de secteurs enregistrés (SANTE → Multi santé, BTP → BTP/RS, …).");
    }
}
