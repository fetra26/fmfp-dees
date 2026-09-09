<?php

namespace App\Filament\Resources\Partenaires;

use App\Filament\Resources\Partenaires\Pages\ListPartenaires;
use App\Models\Partenaire;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PartenaireResource extends Resource
{
    protected static ?string $model = Partenaire::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Suivi des projets';
    protected static ?string $modelLabel = 'Partenaire';
    protected static ?string $pluralModelLabel = 'Partenaires';
    protected static ?int $navigationSort = 2;
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function table(Table $table): Table
    {
        return $table
            // ═══ COLONNES ═══
            // Regroupés par projet → l'info projet apparaît une seule fois en en-tête de groupe.
            // Les colonnes ci-dessous sont donc les infos SPÉCIFIQUES au partenaire.
            ->columns([
                TextColumn::make('nom')
                    ->label('Nom du partenaire')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('cnaps')
                    ->label('CNaPS')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('nb_salaries')
                    ->label('Nb salariés')
                    ->numeric()
                    ->sortable()
                    ->alignRight()
                    ->placeholder('—'),

                // Colonnes contexte projet (visibles par défaut car pas de groupement actif)
                TextColumn::make('porteurProj.projet.reference')
                    ->label('Réf. projet')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('porteurProj.projet.intitule')
                    ->label('Intitulé projet')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('porteurProj.porteur.raison_sociale')
                    ->label('Porteur')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('porteurProj.projet.guichet.libelle')
                    ->label('Guichet')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            // ═══ REGROUPEMENT PAR PROJET (équivalent des cellules fusionnées Excel) ═══
            // Désactivé — décommenter pour réactiver le groupement.
            // ->groups([
            //     Group::make('porteurProj.projet.reference')
            //         ->label('Projet')
            //         ->collapsible()
            //         ->titlePrefixedWithLabel(false)
            //         // Titre visible dans l'en-tête de groupe : "REF · Porteur"
            //         ->getTitleFromRecordUsing(fn (Partenaire $record): string =>
            //             ($record->porteurProj?->projet?->reference ?? 'Sans projet')
            //             . ' · ' . ($record->porteurProj?->porteur?->raison_sociale ?? '—')
            //         )
            //         // Description du groupe : intitulé du projet
            //         ->getDescriptionFromRecordUsing(fn (Partenaire $record): ?string =>
            //             $record->porteurProj?->projet?->intitule
            //         ),
            //
            //     Group::make('porteurProj.porteur.raison_sociale')
            //         ->label('Porteur')
            //         ->collapsible(),
            //
            //     Group::make('porteurProj.projet.guichet.libelle')
            //         ->label('Guichet')
            //         ->collapsible(),
            // ])
            // ->defaultGroup('porteurProj.projet.reference')

            ->filters([
                SelectFilter::make('projet')
                    ->label('Filtrer par projet')
                    ->relationship('porteurProj.projet', 'reference')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('porteur')
                    ->label('Filtrer par porteur')
                    ->relationship('porteurProj.porteur', 'raison_sociale')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('voir_projet')
                    ->label('Voir le projet')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Partenaire $r) => $r->porteurProj
                        ? \App\Filament\Resources\PorteurProjs\PorteurProjResource::getUrl('view', ['record' => $r->porteurProj->id])
                        : null
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('nom')
            ->emptyStateHeading('Aucun partenaire')
            ->emptyStateDescription('Les partenaires apparaissent ici après import de la feuille "Partenaires" du template.');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'porteurProj:id,projet_id,porteur_id',
                'porteurProj.projet:id,reference,intitule,guichet_id,vague_id',
                'porteurProj.projet.guichet:id,libelle',
                'porteurProj.projet.vague:id,libelle',
                'porteurProj.porteur:id,raison_sociale',
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartenaires::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }
}
