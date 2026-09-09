<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectEvaluator extends Model
{
    protected $table = 'project_evaluator';

    protected $fillable = [
        'projet_id', 'evaluateur_id', 'assigne_par', 'assigne_at', 'complete_at', 'notes',
    ];

    protected $casts = ['assigne_at' => 'datetime', 'complete_at' => 'datetime'];

    public function projet() { return $this->belongsTo(Projet::class); }
    public function evaluateur() { return $this->belongsTo(User::class, 'evaluateur_id'); }
    public function assignePar() { return $this->belongsTo(User::class, 'assigne_par'); }
}
