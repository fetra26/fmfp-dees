<?php

namespace App\Filament\Imports;

use App\Models\Convention;
use App\Models\Dano;
use App\Models\Entreprise;
use App\Models\Paiement;
use App\Models\Projet;
use App\Models\RapportTechnique;
use App\Models\Region;
use App\Models\Relance;
use App\Models\Reserve;
use App\Models\Secteur;
use App\Models\StatutProjet;
use App\Models\SuiviTerrain;
use App\Models\TypeDano;
use App\Models\TypeRelance;
use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ProjetImporter extends Importer
{
    protected static ?string $model = Projet::class;

    public static function getColumns(): array
    {
        return [
            // Porteur
            ImportColumn::make('porteur')->label('Porteur'),
            ImportColumn::make('cnaps_porteur')->label('CNaPS porteur'),
            ImportColumn::make('nb_salaries_porteur')->label('Nombre salarié du porteur'),
            ImportColumn::make('partenaire')->label('Partenaire'),
            ImportColumn::make('cnaps_partenaire')->label('cnaps partenaire'),
            ImportColumn::make('contact')->label('Contact'),
            ImportColumn::make('tel')->label('Tel'),
            ImportColumn::make('adresse')->label('ADRESSE'),
            ImportColumn::make('region')->label('Région'),

            // Projet
            ImportColumn::make('intitule')->label('Intitulé'),
            ImportColumn::make('statut')->label('Statut'),
            ImportColumn::make('date_notification')->label('DATE NOTIFICATION'),
            ImportColumn::make('date_debut')->label('Date début'),
            ImportColumn::make('date_fin')->label('Date fin'),
            ImportColumn::make('motifs')->label('MOTIFS'),

            // Bénéficiaires prévus
            ImportColumn::make('prev_benef_total')->label('Nb bénéf total'),
            ImportColumn::make('prev_benef_h')->label('H'),
            ImportColumn::make('prev_benef_f')->label('F'),
            ImportColumn::make('prev_benef_jeunes')->label('Jeunes'),
            ImportColumn::make('prev_benef_fpe')->label('FPE'),
            ImportColumn::make('prev_benef_cadres')->label('FEMME CADRES'),

            // Bénéficiaires réalisés
            ImportColumn::make('real_benef_total')->label('Nb bénéf total formé'),
            ImportColumn::make('real_benef_h_reel')->label('H formé'),
            ImportColumn::make('real_benef_f_reel')->label('F formé'),
            ImportColumn::make('real_benef_jeunes_reel')->label('Jeunes formés'),
            ImportColumn::make('real_benef_fpe_reel')->label('FPE formés'),
            ImportColumn::make('real_benef_cadres_reel')->label('Femmes cadres formés'),

            // Convention / Financement
            ImportColumn::make('montant_total')->label('Montant total'),
            ImportColumn::make('financement_demande')->label('Financement demandé'),
            ImportColumn::make('dt_mobilise')->label('DT mobilisé'),
            ImportColumn::make('fonds_additionnels')->label('Fonds ADDITIONNELS'),
            ImportColumn::make('fonds_mutualise')->label('Fonds mutualisé'),
            ImportColumn::make('date_envoi_convention')->label("DATE D'ENVOI CONVENITON"),
            ImportColumn::make('date_reception_convention')->label('Date reception convention'),

            // DANO
            ImportColumn::make('dano')->label('DANO'),

            // Paiements
            ImportColumn::make('date_paiement_j1')->label('Date de paiement J1'),
            ImportColumn::make('montant_j1')->label('Montant payé J1'),
            ImportColumn::make('date_paiement_j2')->label('Date paiement J2'),
            ImportColumn::make('montant_j2')->label('Montant payé J2'),
            ImportColumn::make('date_paiement_j3')->label('date paiement j3'),
            ImportColumn::make('montant_j3')->label('Montant payé J3'),

            // Alertes & Relances
            ImportColumn::make('situation_alerte')->label('Situation alerte'),
            ImportColumn::make('date_relance_1')->label('Date de relance 1 des alertes rouges'),
            ImportColumn::make('date_relance_2')->label('Date de relance 2 des alertes rouges'),
            ImportColumn::make('date_mise_en_demeure')->label('Date de reception de la lettre de mise en demeure'),
            ImportColumn::make('date_resiliation')->label('Date de reception de la lettre de résiliation'),

            // Suivi terrain
            ImportColumn::make('date_formation_contractants')->label('Date formation des contractants'),
            ImportColumn::make('date_suivi_terrain')->label('Date suivi terrain'),
            ImportColumn::make('observation_suivi')->label('Observation suivi terrain'),

            // Rapport technique & Évaluation
            ImportColumn::make('date_arrivee_rapport')->label("Date d'arrivé du rapport technique à la DEES"),
            ImportColumn::make('evaluateur')->label('Evaluateur'),
            ImportColumn::make('date_transfert_evaluateur')->label('DATE DE TRANSFERT VERS EVALUATEUR'),
            ImportColumn::make('appreciation_evaluateur')->label('Appréciation évaluateur'),
            ImportColumn::make('date_validation_evaluateur')->label('DATE DE VALIDATION EVALUATEUR'),

            // Réserves
            ImportColumn::make('reserve')->label('Réserve du projet'),
            ImportColumn::make('situation_reserves')->label('Situation des réserves'),

            // Divers
            ImportColumn::make('observation')->label('Observation'),

            // Prestataire / Modules
            ImportColumn::make('prestataire')->label('Prestataire'),
            ImportColumn::make('modules_formation')->label('Modules de formation'),
            ImportColumn::make('volume_horaire_total')->label('Volume horaire total'),
        ];
    }

    public function resolveRecord(): ?Projet
    {
        $porteurNom = $this->normaliserPorteur($this->data['porteur'] ?? '');

        if (blank($porteurNom) && blank($this->data['intitule'] ?? '')) {
            return null; // Ligne vide → ignorer
        }

        // Trouver ou créer l'entreprise porteur
        $porteur = $this->resoudreEntreprise($porteurNom);

        // Dédoublonnage : même porteur + même intitulé
        return Projet::firstOrNew([
            'porteur_id' => $porteur?->id,
            'intitule'   => Str::limit(trim($this->data['intitule'] ?? ''), 300),
        ]);
    }

    protected function afterSave(): void
    {
        $projet = $this->record;

        $this->importerPartenaires($projet);
        $this->importerConvention($projet);
        $this->importerPaiements($projet);
        $this->importerDano($projet);
        $this->importerSuiviTerrain($projet);
        $this->importerRelances($projet);
        $this->importerRapportTechnique($projet);
        $this->importerReserve($projet);
        $this->importerModulesPrestataires($projet);
    }

    // =========================================================
    // Champs directs du Projet (via fillRecord natif de Filament)
    // =========================================================

    public function resolveColumnData(): void
    {
        $d = $this->data;

        $statut = StatutProjet::where('code', $this->normaliserStatut($d['statut'] ?? ''))
            ->orWhere('libelle', 'LIKE', '%' . ($d['statut'] ?? '') . '%')
            ->first();

        $region = $this->resoudreRegion($d['region'] ?? '');

        $this->data['statut_projet_id']   = $statut?->id ?? StatutProjet::where('code', 'en_cours')->first()?->id;
        $this->data['region_id']          = $region?->id;
        $this->data['date_approbation']   = $this->parseDate($d['date_notification'] ?? '');
        $this->data['date_debut_prevue']  = $this->parseDate($d['date_debut'] ?? '');
        $this->data['date_fin_prevue']    = $this->parseDate($d['date_fin'] ?? '');
        $this->data['prev_benef_total']   = $this->parseInt($d['prev_benef_total'] ?? 0);
        $this->data['prev_benef_h']       = $this->parseInt($d['prev_benef_h'] ?? 0);
        $this->data['prev_benef_f']       = $this->parseInt($d['prev_benef_f'] ?? 0);
        $this->data['prev_benef_jeunes']  = $this->parseInt($d['prev_benef_jeunes'] ?? 0);
        $this->data['prev_benef_fpe']     = $this->parseInt($d['prev_benef_fpe'] ?? 0);
        $this->data['prev_benef_cadres']  = $this->parseInt($d['prev_benef_cadres'] ?? 0);
        $this->data['real_benef_total']   = $this->parseInt($d['real_benef_total'] ?? 0);
        $this->data['real_benef_h']       = $this->parseInt($d['real_benef_h_reel'] ?? 0);
        $this->data['real_benef_f']       = $this->parseInt($d['real_benef_f_reel'] ?? 0);
        $this->data['real_benef_jeunes']  = $this->parseInt($d['real_benef_jeunes_reel'] ?? 0);
        $this->data['real_benef_fpe']     = $this->parseInt($d['real_benef_fpe_reel'] ?? 0);
        $this->data['real_benef_cadres']  = $this->parseInt($d['real_benef_cadres_reel'] ?? 0);
        $this->data['niveau_alerte']      = $this->normaliserAlerte($d['situation_alerte'] ?? '');
        $this->data['observations']       = trim($d['observation'] ?? '');

        // Générer une référence si absente
        if (! $this->record->exists || blank($this->record->reference)) {
            $this->data['reference'] = $this->genererReference();
        }
    }

    // =========================================================
    // Helpers : création des entités liées
    // =========================================================

    protected function importerPartenaires(Projet $projet): void
    {
        $texte = trim($this->data['partenaire'] ?? '');
        if (blank($texte)) return;

        $noms = array_filter(array_map('trim', preg_split('/[;,\/\n]+/', $texte)));
        foreach ($noms as $nom) {
            $nom = $this->normaliserPorteur($nom);
            if (blank($nom)) continue;
            $entreprise = $this->resoudreEntreprise($nom);
            if ($entreprise) {
                $projet->entreprises()->syncWithoutDetaching([$entreprise->id => ['role_dans_projet' => 'Partenaire']]);
            }
        }
    }

    protected function importerConvention(Projet $projet): void
    {
        $montantTotal  = $this->parseMontant($this->data['montant_total'] ?? 0);
        $montantJ1     = $this->parseMontant($this->data['financement_demande'] ?? 0);
        $dtMobilise    = $this->parseMontant($this->data['dt_mobilise'] ?? 0);
        $fondsAdd      = $this->parseMontant($this->data['fonds_additionnels'] ?? 0);
        $fondsMut      = $this->parseMontant($this->data['fonds_mutualise'] ?? 0);
        $dateSignature = $this->parseDate($this->data['date_envoi_convention'] ?? '');
        $dateEffet     = $this->parseDate($this->data['date_reception_convention'] ?? '');

        if ($montantTotal === 0 && blank($dateSignature)) return;

        Convention::updateOrCreate(
            ['projet_id' => $projet->id, 'numero' => 'CONV-' . $projet->id],
            [
                'montant_total'       => $montantTotal,
                'montant_j1'          => $montantJ1,
                'montant_j2'          => $dtMobilise,
                'fonds_additionnel'   => $fondsAdd,
                'fonds_mutualise'     => $fondsMut,
                'date_signature'      => $dateSignature,
                'date_effet'          => $dateEffet,
                'created_by'          => auth()->id(),
            ]
        );
    }

    protected function importerPaiements(Projet $projet): void
    {
        $convention = Convention::where('projet_id', $projet->id)->first();

        $paiements = [
            'J1' => [$this->data['date_paiement_j1'] ?? '', $this->data['montant_j1'] ?? 0],
            'J2' => [$this->data['date_paiement_j2'] ?? '', $this->data['montant_j2'] ?? 0],
            'J3' => [$this->data['date_paiement_j3'] ?? '', $this->data['montant_j3'] ?? 0],
        ];

        foreach ($paiements as $ligne => [$dateBrute, $montantBrut]) {
            $date    = $this->parseDate($dateBrute);
            $montant = $this->parseMontant($montantBrut);
            if (blank($date) || $montant === 0) continue;

            Paiement::firstOrCreate(
                ['projet_id' => $projet->id, 'ligne' => $ligne, 'date_paiement' => $date],
                [
                    'convention_id' => $convention?->id,
                    'montant'       => $montant,
                    'created_by'    => auth()->id(),
                ]
            );
        }
    }

    protected function importerDano(Projet $projet): void
    {
        $contenu = trim($this->data['dano'] ?? '');
        if (blank($contenu)) return;

        $typeDano = TypeDano::where('code', 'approbation')->first();
        Dano::firstOrCreate(
            ['projet_id' => $projet->id, 'contenu' => $contenu],
            ['type_dano_id' => $typeDano?->id, 'created_by' => auth()->id()]
        );
    }

    protected function importerSuiviTerrain(Projet $projet): void
    {
        $dateFormation = $this->parseDate($this->data['date_formation_contractants'] ?? '');
        $dateSuivi     = $this->parseDate($this->data['date_suivi_terrain'] ?? '');
        $observation   = trim($this->data['observation_suivi'] ?? '');

        if (filled($dateSuivi)) {
            SuiviTerrain::firstOrCreate(
                ['projet_id' => $projet->id, 'date_visite' => $dateSuivi],
                ['constats' => $observation, 'created_by' => auth()->id()]
            );
        }

        if (filled($dateFormation)) {
            SuiviTerrain::firstOrCreate(
                ['projet_id' => $projet->id, 'date_visite' => $dateFormation],
                ['lieu' => 'Formation des contractants', 'created_by' => auth()->id()]
            );
        }
    }

    protected function importerRelances(Projet $projet): void
    {
        $typeRelance = TypeRelance::where('code', 'courrier')->first();

        $relances = [
            $this->data['date_relance_1'] ?? '',
            $this->data['date_relance_2'] ?? '',
            $this->data['date_mise_en_demeure'] ?? '',
        ];

        foreach ($relances as $i => $dateBrute) {
            $date = $this->parseDate($dateBrute);
            if (blank($date)) continue;

            Relance::firstOrCreate(
                ['projet_id' => $projet->id, 'date_relance' => $date],
                ['type_relance_id' => $typeRelance?->id, 'created_by' => auth()->id()]
            );
        }
    }

    protected function importerRapportTechnique(Projet $projet): void
    {
        $dateRapport  = $this->parseDate($this->data['date_arrivee_rapport'] ?? '');
        $appreciation = trim($this->data['appreciation_evaluateur'] ?? '');
        $nomEval      = trim($this->data['evaluateur'] ?? '');
        $dateValid    = $this->parseDate($this->data['date_validation_evaluateur'] ?? '');

        if (blank($dateRapport) && blank($appreciation)) return;

        $evaluateur = null;
        if (filled($nomEval)) {
            $evaluateur = User::where('name', 'LIKE', "%$nomEval%")
                ->orWhere('first_name', 'LIKE', "%$nomEval%")
                ->first();
        }

        RapportTechnique::firstOrCreate(
            ['projet_id' => $projet->id, 'date_rapport' => $dateRapport ?? now()->toDateString()],
            [
                'synthese'        => $appreciation,
                'evaluateur_id'   => $evaluateur?->id,
                'date_validation' => $dateValid,
                'statut'          => filled($dateValid) ? 'valide' : (filled($dateRapport) ? 'soumis' : 'brouillon'),
                'type_rapport'    => 'final',
                'created_by'      => auth()->id(),
            ]
        );
    }

    protected function importerReserve(Projet $projet): void
    {
        $description = trim($this->data['reserve'] ?? '');
        $situation   = trim($this->data['situation_reserves'] ?? '');
        if (blank($description)) return;

        $rapport = $projet->rapportsTechniques()->latest()->first();

        Reserve::firstOrCreate(
            ['projet_id' => $projet->id, 'description' => $description],
            [
                'rapport_technique_id' => $rapport?->id,
                'statut'               => $this->normaliserStatutReserve($situation),
                'created_by'           => auth()->id(),
            ]
        );
    }

    protected function importerModulesPrestataires(Projet $projet): void
    {
        $nomPrestataire = trim($this->data['prestataire'] ?? '');
        $modules        = trim($this->data['modules_formation'] ?? '');
        $volumeTotal    = $this->parseInt($this->data['volume_horaire_total'] ?? 0);

        if (filled($nomPrestataire)) {
            \App\Models\Prestataire::firstOrCreate(
                ['projet_id' => $projet->id, 'nom' => $nomPrestataire],
                ['prev_montant' => 0, 'created_by' => auth()->id()]
            );
        }

        if (filled($modules)) {
            $listeModules = array_filter(array_map('trim', preg_split('/[;\n]+/', $modules)));
            foreach ($listeModules as $module) {
                \App\Models\Module::firstOrCreate(
                    ['projet_id' => $projet->id, 'intitule' => Str::limit($module, 300)],
                    ['prev_duree_heures' => $volumeTotal, 'created_by' => auth()->id()]
                );
            }
        }
    }

    // =========================================================
    // Normalisation
    // =========================================================

    protected function normaliserPorteur(string $nom): string
    {
        $nom = mb_strtoupper(trim($nom));
        // Supprimer les suffixes juridiques courants
        $suffixes = ['SARL', 'SA', 'SAS', 'EURL', 'SNC', 'SCI', 'SCOP', 'GIE', 'ASSOCIATION', 'ONG'];
        foreach ($suffixes as $s) {
            $nom = preg_replace('/[\s,]+' . preg_quote($s, '/') . '[\s,]*$/i', '', $nom);
        }
        return trim($nom);
    }

    protected function resoudreEntreprise(string $nom): ?Entreprise
    {
        if (blank($nom)) return null;

        return Entreprise::withTrashed()
            ->where('raison_sociale', $nom)
            ->first()
            ?? Entreprise::create(['raison_sociale' => $nom, 'created_by' => auth()->id()]);
    }

    protected function resoudreRegion(string $texte): ?Region
    {
        if (blank($texte)) return null;

        // Correction orthographique connue
        $corrections = [
            'VAKINAKARATRA' => 'VAKINANKARATRA',
            'ANTANANARIVO'  => 'Analamanga',
        ];

        $texte = $corrections[mb_strtoupper(trim($texte))] ?? $texte;

        return Region::where('libelle', 'LIKE', '%' . trim($texte) . '%')
            ->orWhere('code', mb_strtoupper(trim($texte)))
            ->first();
    }

    protected function normaliserStatut(string $texte): string
    {
        $map = [
            'validé'   => 'approuve',
            'valide'   => 'approuve',
            'refusé'   => 'annule',
            'refuse'   => 'annule',
            'en cours' => 'en_cours',
            'clôturé'  => 'cloture',
            'cloture'  => 'cloture',
            'suspendu' => 'suspendu',
        ];
        return $map[mb_strtolower(trim($texte))] ?? 'en_cours';
    }

    protected function normaliserAlerte(string $texte): string
    {
        $t = mb_strtolower(trim($texte));
        if (str_contains($t, 'rouge')) return 'rouge';
        if (str_contains($t, 'orange')) return 'orange';
        return 'verte';
    }

    protected function normaliserStatutReserve(string $texte): string
    {
        $t = mb_strtolower(trim($texte));
        if (str_contains($t, 'lev')) return 'levee';
        if (str_contains($t, 'cours')) return 'en_cours';
        return 'ouverte';
    }

    protected function parseDate(string $valeur): ?string
    {
        if (blank($valeur)) return null;
        try {
            return Carbon::parse(trim($valeur))->toDateString();
        } catch (\Exception) {
            return null;
        }
    }

    protected function parseInt(mixed $valeur): int
    {
        return (int) preg_replace('/[^0-9]/', '', (string) $valeur);
    }

    protected function parseMontant(mixed $valeur): int
    {
        // Nettoie les espaces, virgules, points de milliers → entier Ariary
        $str = preg_replace('/[\s\xc2\xa0]/', '', (string) $valeur); // espaces insécables
        $str = str_replace(',', '.', $str);
        return (int) round((float) preg_replace('/[^0-9.]/', '', $str));
    }

    protected function genererReference(): string
    {
        $annee = now()->year;
        $dernier = Projet::whereYear('created_at', $annee)->count() + 1;
        return sprintf('PRJ-%d-%04d', $annee, $dernier);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import terminé : ' . number_format($import->successful_rows) . ' projet(s) importé(s).';
        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ligne(s) en erreur.';
        }
        return $body;
    }
}
