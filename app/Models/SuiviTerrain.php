<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class SuiviTerrain extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'suivi_terrain';

    protected $fillable = [
        'projet_id', 'date_visite', 'lieu', 'constats',
        'recommandations', 'taux_execution_observe', 'created_by', 'updated_by',
    ];

    protected $casts = ['date_visite' => 'date'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['date_visite', 'taux_execution_observe'])->logOnlyDirty()->useLogName('suivi_terrain');
    }

    public function projet() { return $this->belongsTo(Projet::class); }
    public function creePar() { return $this->belongsTo(User::class, 'created_by'); }
}
