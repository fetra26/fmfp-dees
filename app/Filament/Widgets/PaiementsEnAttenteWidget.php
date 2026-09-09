<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PorteurProjs\PorteurProjResource;
use App\Models\PorteurProj;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Widget "Mes paiements en attente" — visible sur le dashboard du DAF.
 *
 * Liste les projets qui :
 *   - Sont dans un statut permettant le paiement (validation_financiere ou formation_encours)
 *   - N'ont PAS ENCORE de paiement J1 enregistré
 *
 * Aide le DAF à identifier rapidement les tranches à verser.
 */
class PaiementsEnAttenteWidget extends BaseWidget
{
    use Concerns\CheckDashboardVisibility;

    public const SLUG = 'paiements_attente';

    protected static ?string $heading = '💰 Paiements en attente';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    /** Lazy loading : le widget se charge seulement quand il devient visible à l'écran */
    protected static bool $isLazy = true;

    /** Ne recharge que toutes les 5 minutes (au lieu de chaque interaction) */
    protected ?string $pollingInterval = null;

    /** Contrôle d'accès par rôle : DAF/admin uniquement (complète canView() du trait) */
    public static function canViewByRole(): bool
    {
        return Auth::user()?->peutEditerPaiement() ?? false;
    }

    public function table(Table $table): Table
    {
        // Pré-calcul en cache 5 min : IDs des porteurs_proj éligibles
        // Évite le whereHas + whereDoesntHave coûteux à chaque affichage.
        $ids = Cache::remember('widget.paiements_attente.ids', 300, function () {
            return PorteurProj::query()
                ->whereHas('projet.statut', fn ($q) => $q->whereIn('code', [
                    'validation_financiere',
                    'formation_encours',
                ]))
                ->whereDoesntHave('paiements', fn ($q) => $q->where('is_annule', false))
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->pluck('id')
                ->toArray();
        });

        return $table
            ->query(fn () => PorteurProj::query()
                ->with([
                    'projet:id,reference,intitule,statut_projet_id',
                    'projet.statut:id,libelle,code',
                    'porteur:id,raison_sociale',
                ])
                ->whereIn('id', $ids)
                ->orderBy('created_at', 'desc')
            )
            ->columns([
                TextColumn::make('projet.reference')
                    ->label('Réf. projet')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                TextColumn::make('projet.intitule')
                    ->label('Intitulé')
                    ->limit(40)
                    ->searchable()
                    ->tooltip(fn ($r) => $r?->projet?->intitule),

                TextColumn::make('porteur.raison_sociale')
                    ->label('Porteur')
                    ->searchable(),

                TextColumn::make('projet.statut.libelle')
                    ->label('Statut projet')
                    ->badge()
                    ->color(fn (string $state) => str_contains(mb_strtolower($state), 'validation') ? 'warning' : 'primary'),

                TextColumn::make('montant_total')
                    ->label('Montant total prévu')
                    ->money('MGA', locale: 'fr')
                    ->alignRight()
                    ->placeholder('—'),

                TextColumn::make('date_debut')
                    ->label('Début convention')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('voir_projet')
                    ->label('Ouvrir la fiche')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($r) => $r ? PorteurProjResource::getUrl('view', ['record' => $r->id]) : '#')
                    ->visible(fn ($r) => $r !== null),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Aucun paiement en attente')
            ->emptyStateDescription('Tous les projets éligibles ont au moins un paiement enregistré. 👍');
    }
}
