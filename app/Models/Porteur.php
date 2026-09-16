<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Porteur extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'porteur';

    protected $fillable = [
        'raison_sociale', 'sigle', 'nif', 'cnaps', 'forme_juridique',
        'secteur_id', 'region_id', 'adresse', 'ville', 'telephone', 'email',
        'responsable_nom', 'responsable_fonction', 'nb_salaries', 'notes',
        'created_by', 'updated_by',
    ];

    /**
     * Tient à jour la forme normalisée de la raison sociale.
     *
     * C'est ce champ, et lui seul, qui permet de retrouver une entreprise déjà
     * enregistrée lors d'un import : « ACM-NET », « Acm Net » et « ACM_NET »
     * désignent la même. L'import le passait bien à Porteur::create(), mais il
     * ne figurait pas dans $fillable — Laravel l'écartait en silence, la colonne
     * restait nulle, le rapprochement ne pouvait jamais aboutir et chaque import
     * recréait l'entreprise.
     *
     * Le calculer ici plutôt que chez l'appelant garantit qu'il est renseigné
     * quelle que soit l'origine — import ou saisie dans l'interface — et reste
     * cohérent avec la migration qui a initialisé la colonne.
     */
    protected static function booted(): void
    {
        static::saving(function (self $porteur) {
            $porteur->raison_sociale_normalisee = ImportMapping::normaliser($porteur->raison_sociale);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['raison_sociale', 'nif'])->logOnlyDirty()->useLogName('porteur');
    }

    public function secteur() { return $this->belongsTo(Secteur::class); }
    public function region()  { return $this->belongsTo(Region::class); }

    // FK explicite car Entreprise (sous-classe) ferait deviner Laravel "entreprise_id"
    public function porteurProjs() { return $this->hasMany(PorteurProj::class, 'porteur_id'); }
    public function projets() { return $this->hasManyThrough(Projet::class, PorteurProj::class, 'porteur_id', 'id', 'id', 'projet_id'); }
}
