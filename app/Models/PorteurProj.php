<?php

namespace App\Models;

use App\Models\Concerns\NormaliseReference;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PorteurProj extends Model
{
    use SoftDeletes, LogsActivity, NormaliseReference;

    protected $table = 'porteur_proj';

    /** Le trait NormaliseReference lira ces constantes pour savoir sur quelle colonne travailler. */
    public const CHAMP_REFERENCE            = 'reference_convention';
    public const CHAMP_REFERENCE_NORMALISEE = 'reference_convention_normalisee';

    protected $fillable = [
        'projet_id', 'porteur_id', 'reference_convention', 'reference_convention_normalisee',
        'montant_total', 'financement_demande', 'dt_mobilise',
        'fonds_additionnel', 'fonds_mutualise', 'financement_autre',
        'appreciation_evaluateur', 'statut_validation', 'motifs',
        'date_notification', 'date_envoi_convention', 'date_reception_convention',
        'date_debut', 'date_fin',
        'dano_type', 'dano_description',
        'situation_alloc', 'niveau_alerte',
        'date_relance_1', 'date_relance_2', 'date_mise_en_demeure', 'date_resiliation',
        'date_formation_contractants', 'date_suivi_terrain', 'observation_suivi',
        'date_arrivee_rapport', 'evaluateur_id', 'date_transfert_evaluateur',
        'date_debut_traitement', 'reserve_description', 'date_envoi_reserve',
        'date_relance_reserve_1', 'date_relance_reserve_2', 'situation_reserves',
        'date_validation_evaluateur', 'date_transmission_daf', 'observations_evaluation',
        'observations', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_notification' => 'date', 'date_envoi_convention' => 'date',
        'date_reception_convention' => 'date', 'date_debut' => 'date', 'date_fin' => 'date',
        'date_relance_1' => 'date', 'date_relance_2' => 'date',
        'date_mise_en_demeure' => 'date', 'date_resiliation' => 'date',
        'date_formation_contractants' => 'date', 'date_suivi_terrain' => 'date',
        'date_arrivee_rapport' => 'date', 'date_transfert_evaluateur' => 'date',
        'date_debut_traitement' => 'date', 'date_envoi_reserve' => 'date',
        'date_relance_reserve_1' => 'date', 'date_relance_reserve_2' => 'date',
        'date_validation_evaluateur' => 'date', 'date_transmission_daf' => 'date',
    ];

    /**
     * Recalcule le niveau d'alerte dès qu'un champ qui le détermine change.
     *
     * Le job ne passe que la nuit. Or rouvrir un dossier clôturé — en le
     * repassant par exemple en « attente pièces régul. » — doit le faire
     * réapparaître immédiatement dans les alertes, avec le niveau correspondant
     * à son retard. Sans ce hook, la DEES modifierait un statut et ne verrait
     * rien changer avant le lendemain.
     *
     * Le calcul n'a lieu que si statut_validation ou date_fin a bougé : sur un
     * import de plusieurs milliers de lignes, le déclencher à chaque
     * enregistrement coûterait une requête par ligne pour rien.
     *
     * Un niveau posé explicitement dans la même opération est respecté : c'est
     * ainsi que le job écrit son résultat sans que le hook le recalcule aussitôt.
     */
    protected static function booted(): void
    {
        static::saving(function (self $pp) {
            if ($pp->isDirty('niveau_alerte')) {
                return;
            }

            if (! $pp->exists || ! $pp->isDirty(['statut_validation', 'date_fin'])) {
                return;
            }

            $pp->niveau_alerte = \App\Services\CalculAlerte::niveauPour($pp);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['statut_validation', 'niveau_alerte', 'situation_alloc'])
            ->logOnlyDirty()->useLogName('porteur_proj');
    }

    // Accesseur : allocation consommée = J1 + J2 + J3 non annulés
    protected function allocationConsommee(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->paiements()->where('is_annule', false)->sum('montant'),
        );
    }

    /**
     * Le projet est-il verrouillé ? (statut CLOTURE ou ANNULE)
     * Un projet verrouillé n'est éditable que par le Super Admin.
     */
    public function estVerrouille(): bool
    {
        $codeStatut = $this->projet?->statut?->code;
        return in_array($codeStatut, ['cloture', 'annule'], true);
    }

    public function projet()      { return $this->belongsTo(Projet::class); }
    public function porteur()     { return $this->belongsTo(Porteur::class); }
    public function evaluateur()  { return $this->belongsTo(User::class, 'evaluateur_id'); }
    public function partenaires() { return $this->hasMany(Partenaire::class); }
    public function benefs()      { return $this->hasMany(Benef::class); }
    public function formations()  { return $this->hasMany(Formation::class); }
    public function paiements()   { return $this->hasMany(Paiement::class); }

    public function benefPrevu()   { return $this->hasOne(Benef::class)->where('type', 'prevu'); }
    public function benefRealise() { return $this->hasOne(Benef::class)->where('type', 'realise'); }
    public function formationPrevue()   { return $this->hasOne(Formation::class)->where('type', 'prevu'); }
    public function formationRealisee() { return $this->hasOne(Formation::class)->where('type', 'realise'); }
}
