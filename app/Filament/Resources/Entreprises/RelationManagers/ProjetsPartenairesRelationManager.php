<?php

namespace App\Filament\Resources\Entreprises\RelationManagers;

use App\Models\Partenaire;
use App\Models\PorteurProj;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjetsPartenairesRelationManager extends RelationManager
{
    // Trouve les PorteurProj où cette entreprise apparaît comme partenaire (via la table partenaire)
    protected static string $relationship = 'porteurProjs';
    protected static ?string $title = 'Projets (partenaire)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference_convention')
            ->query(function () {
                $nomEntreprise = $this->getOwnerRecord()->raison_sociale;

                // IDs des porteur_proj où cette entreprise apparaît comme partenaire
                $porteurProjIds = Partenaire::where('nom', 'LIKE', '%' . $nomEntreprise . '%')
                    ->pluck('porteur_proj_id')
                    ->unique();

                return PorteurProj::query()
                    ->whereIn('id', $porteurProjIds)
                    ->with(['projet.statut', 'projet.region', 'porteur']);
            })
            ->columns([
                TextColumn::make('projet.reference')->label('Référence projet')->searchable(),
                TextColumn::make('projet.intitule')->label('Intitulé')->limit(50),
                TextColumn::make('porteur.raison_sociale')->label('Porteur principal'),
                TextColumn::make('projet.statut.libelle')->label('Statut')->badge(),
                TextColumn::make('projet.region.libelle')->label('Région'),
                TextColumn::make('statut_validation')->label('Validation')->badge()
                    ->color(fn (?string $s) => match ($s) {
                        'valide' => 'success', 'refuse' => 'danger',
                        'en_cours' => 'info', default => 'gray',
                    }),
                TextColumn::make('niveau_alerte')->label('Alerte')->badge()
                    ->color(fn (?string $s) => match ($s) {
                        'rouge' => 'danger', 'orange' => 'warning', default => 'success',
                    }),
                TextColumn::make('montant_total')->label('Montant')->numeric()->suffix(' Ar'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
