<?php

namespace App\Models;

use App\Models\Concerns\NormaliseReference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Convention extends Model
{
    use SoftDeletes, LogsActivity, NormaliseReference;

    protected $table = 'convention';

    protected $fillable = [
        'reference', 'reference_normalisee', 'date_signature', 'date_effet', 'date_expiration',
        'observations', 'created_by',
    ];

    protected $casts = [
        'date_signature' => 'date', 'date_effet' => 'date', 'date_expiration' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['reference'])->logOnlyDirty()->useLogName('convention');
    }

    public function projets() { return $this->hasMany(Projet::class); }
}
