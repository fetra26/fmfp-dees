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
