<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal des conflits détectés à l'import.
 * Chaque conflit = 1 champ divergent entre l'Excel et la valeur déjà en base.
 * L'utilisateur peut ensuite trancher : garder DB, prendre Excel, ou ignorer.
 */
class ImportConflict extends Model
{
    use HasFactory;

    protected $table = 'import_conflicts';

    protected $fillable = [
        'entite_type',
        'entite_id',
        'champ',
        'valeur_db',
        'valeur_excel',
        'ligne_excel',
        'fichier',
        'statut',
        'note_resolution',
        'resolu_par',
        'resolu_le',
    ];

    protected $casts = [
        'resolu_le' => 'datetime',
    ];

    public const STATUT_EN_ATTENTE  = 'en_attente';
    public const STATUT_GARDE_DB    = 'garde_db';
    public const STATUT_PRIS_EXCEL  = 'pris_excel';
    public const STATUT_IGNORE      = 'ignore';

    public function resoluPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolu_par');
    }
}
