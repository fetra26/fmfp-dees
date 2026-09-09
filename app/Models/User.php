<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles, LogsActivity;

    protected $fillable = [
        'matricule',
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'organization_type',
        'external_organization',
        'departement_id',
        'dashboard_widgets_override',
        'is_active',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'          => 'datetime',
            'last_login_at'              => 'datetime',
            'password'                   => 'hashed',
            'is_active'                  => 'boolean',
            'preferences'                => 'array',
            'dashboard_widgets_override' => 'array',
        ];
    }

    /**
     * Préférences par défaut si l'utilisateur n'a rien configuré.
     */
    public static function defaultPreferences(): array
    {
        return [
            'colonnes_visibles' => [
                'projet.secteur.libelle',
                'projet.vague.libelle',
                'projet.reference',
                'reference_convention',
                'porteur.raison_sociale',
                'projet.intitule',
                'statut_validation',
            ],
            'colonnes_figees' => [
                'projet.secteur.libelle',
                'projet.vague.libelle',
                'projet.reference',
                'porteur.raison_sociale',
                'statut_validation',
            ],
            'pagination_defaut'  => 25,
            'tri_colonne'        => 'created_at',
            'tri_sens'           => 'desc',
        ];
    }

    /**
     * Récupère une préférence (avec fallback sur la valeur par défaut).
     */
    public function pref(string $key)
    {
        return data_get($this->preferences, $key)
            ?? data_get(self::defaultPreferences(), $key);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['matricule', 'email', 'is_active', 'organization_type'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('user');
    }

    public function getFullNameAttribute(): string
    {
        if ($this->first_name || $this->last_name) {
            return trim($this->first_name . ' ' . mb_strtoupper((string) $this->last_name));
        }
        return $this->name ?? $this->email;
    }

    public function projetsEvalues()
    {
        return $this->belongsToMany(
            Projet::class,
            'project_evaluator',
            'evaluateur_id',
            'projet_id'
        )->withPivot(['assigne_at', 'complete_at', 'notes'])
         ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function departement()
    {
        return $this->belongsTo(Departement::class);
    }

    /**
     * Génère un matricule incrémenté pour un département.
     *
     * Format : {CODE_DEPT}{NN}  →  DEES01, DEES02, DAF01, DAF02, DG05...
     *
     * Le compteur est basé sur le nombre d'utilisateurs actuellement rattachés
     * à ce département + 1, avec vérification d'unicité au cas où.
     */
    public static function genererMatricule(int $departementId): ?string
    {
        $dept = Departement::find($departementId);
        if (! $dept) return null;

        $code = mb_strtoupper($dept->code);

        // Cherche le prochain numéro disponible pour ce département
        // On regarde le PLUS GRAND numéro déjà utilisé pour ce code, puis +1
        $existants = self::withTrashed()
            ->where('matricule', 'LIKE', $code . '%')
            ->pluck('matricule')
            ->filter();

        $maxNum = 0;
        $prefixLen = strlen($code);
        foreach ($existants as $mat) {
            $numPart = substr($mat, $prefixLen);
            if (is_numeric($numPart)) {
                $maxNum = max($maxNum, (int) $numPart);
            }
        }

        $prochain = $maxNum + 1;
        return $code . str_pad((string) $prochain, 2, '0', STR_PAD_LEFT);
    }

    // ═══ CENTRALISATION DES NOMS DE RÔLES ═══
    // Les noms techniques des rôles peuvent être personnalisés dans /admin/roles.
    // Ces méthodes acceptent les 2 conventions (ancien et nouveau naming) pour
    // rester robustes si un rôle est renommé via l'UI.

    /** Rôles considérés comme "Super Admin" (accès total) */
    public function isSuperAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'super_admin']);
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin();
    }

    /** Équipe DAF (saisie/import paiements) */
    public function isDaf(): bool
    {
        return $this->hasAnyRole(['daf', 'equipe_daf']);
    }

    /** Direction / Direction DEES (consultation, statistiques) */
    public function isDirection(): bool
    {
        return $this->hasAnyRole(['direction', 'direction_dees']);
    }

    /** Évaluateur (audit rapports techniques) */
    public function isEvaluateur(): bool
    {
        return $this->hasAnyRole(['evaluateur']);
    }

    /** Équipe DEES / chargé de projet DEES */
    public function isDees(): bool
    {
        return $this->hasAnyRole(['project_manager', 'responsable_dees', 'equipe_dees']);
    }

    /** Contrôleur interne */
    public function isControleurInterne(): bool
    {
        return $this->hasAnyRole(['controleur_interne']);
    }

    /**
     * Peut éditer les paiements = admin OU DAF.
     * Utilisé partout dans le code métier pour centraliser la règle.
     */
    public function peutEditerPaiement(): bool
    {
        return $this->isSuperAdmin() || $this->isDaf();
    }

    /**
     * Peut voir la section Paiements (lecture) = tous les rôles internes.
     */
    public function peutVoirPaiement(): bool
    {
        return $this->isSuperAdmin()
            || $this->isDaf()
            || $this->isDees()
            || $this->isDirection()
            || $this->isEvaluateur()
            || $this->isControleurInterne();
    }

    // ═══ CONFIGURATION HYBRIDE DES WIDGETS DU DASHBOARD ═══
    // Priorité : override utilisateur > profil département > défaut système (tous)

    /**
     * Retourne la liste des slugs de widgets à afficher pour cet utilisateur.
     */
    public function getDashboardWidgets(): array
    {
        // 1) Override individuel prime
        if (! empty($this->dashboard_widgets_override)) {
            return $this->dashboard_widgets_override;
        }

        // 2) Sinon, profil du département
        if ($this->departement && ! empty($this->departement->dashboard_widgets)) {
            return $this->departement->dashboard_widgets;
        }

        // 3) Défaut système : tous les widgets
        return \App\Services\DashboardWidgetCatalog::tousLesSlugs();
    }

    /**
     * L'utilisateur peut-il voir ce widget (par son slug) ?
     */
    public function peutVoirWidget(string $slug): bool
    {
        return in_array($slug, $this->getDashboardWidgets(), true);
    }
}
