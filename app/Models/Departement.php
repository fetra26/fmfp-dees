<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Departement extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'departement';

    protected $fillable = [
        'nom', 'code', 'description', 'is_active',
        'responsable_id', 'created_by', 'dashboard_widgets',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'dashboard_widgets' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nom', 'code', 'is_active', 'responsable_id'])
            ->logOnlyDirty()
            ->useLogName('departement');
    }

    public function users()       { return $this->hasMany(User::class, 'departement_id'); }
    public function responsable() { return $this->belongsTo(User::class, 'responsable_id'); }
    public function createdBy()   { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getNomCompletAttribute(): string
    {
        return $this->code . ' — ' . $this->nom;
    }
}
