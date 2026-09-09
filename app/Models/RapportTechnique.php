<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class RapportTechnique extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'rapport_technique';

    protected $fillable = [
        'projet_id', 'reference', 'date_rapport', 'type_rapport', 'statut',
        'synthese', 'conclusions', 'evaluateur_id', 'date_validation',
        'valide_par', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_rapport' => 'date', 'date_validation' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['statut', 'evaluateur_id'])->logOnlyDirty()->useLogName('rapport_technique');
    }

    public function projet() { return $this->belongsTo(Projet::class); }
    public function evaluateur() { return $this->belongsTo(User::class, 'evaluateur_id'); }
    public function validePar() { return $this->belongsTo(User::class, 'valide_par'); }
    public function reserves() { return $this->hasMany(Reserve::class); }
}
