<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Relance extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'relance';

    protected $fillable = [
        'projet_id', 'type_relance_id', 'date_relance', 'contenu',
        'is_repondu', 'date_reponse', 'reponse', 'created_by',
    ];

    protected $casts = [
        'date_relance' => 'date', 'date_reponse' => 'date', 'is_repondu' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['date_relance', 'is_repondu'])->logOnlyDirty()->useLogName('relance');
    }

    public function projet() { return $this->belongsTo(Projet::class); }
    public function type() { return $this->belongsTo(TypeRelance::class, 'type_relance_id'); }
}
