<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Formation extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'formation';
    protected $fillable = ['porteur_proj_id', 'type', 'volume_horaire_total', 'observations', 'created_by'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['type', 'volume_horaire_total'])->logOnlyDirty()->useLogName('formation');
    }

    public function porteurProj()  { return $this->belongsTo(PorteurProj::class); }
    public function formMods()     { return $this->hasMany(FormMod::class); }
    public function prestaForms()  { return $this->hasMany(PrestaForm::class); }
    public function modules()      { return $this->belongsToMany(Module::class, 'form_mod')->withPivot(['formateur_id', 'volume_horaire']); }
    public function prestataires() { return $this->belongsToMany(Prestataire::class, 'presta_form'); }
    public function formateurs()   { return $this->belongsToMany(Formateur::class, 'form_mod', 'formation_id', 'formateur_id'); }
}
