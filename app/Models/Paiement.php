<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Paiement extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'paiement';

    protected $fillable = [
        'porteur_proj_id', 'ligne', 'reference_ordre',
        'date_paiement', 'montant', 'is_annule',
        'motif_annulation', 'date_annulation', 'observations',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_paiement' => 'date', 'date_annulation' => 'date', 'is_annule' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['montant', 'ligne', 'is_annule', 'date_paiement'])
            ->logOnlyDirty()->useLogName('paiement');
    }

    public function porteurProj() { return $this->belongsTo(PorteurProj::class); }
    public function creePar()     { return $this->belongsTo(User::class, 'created_by'); }
}
