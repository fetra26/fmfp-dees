<?php

namespace App\Filament\Resources\PorteurProjs;

use App\Filament\Resources\PorteurProjs\Pages\CreatePorteurProj;
use App\Filament\Resources\PorteurProjs\Pages\EditPorteurProj;
use App\Filament\Resources\PorteurProjs\Pages\ListPorteurProjs;
use App\Filament\Resources\PorteurProjs\Pages\ViewPorteurProj;
use App\Filament\Resources\PorteurProjs\Schemas\PorteurProjForm;
use App\Filament\Resources\PorteurProjs\Schemas\PorteurProjInfolist;
use App\Filament\Resources\PorteurProjs\Tables\PorteurProjsTable;
use App\Models\PorteurProj;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PorteurProjResource extends Resource
{
    protected static ?string $model = PorteurProj::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Suivi des projets';
    protected static ?string $modelLabel = 'Données projet';
    protected static ?string $pluralModelLabel = 'Données projets';
    protected static ?int $navigationSort = 1;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    public static function form(Schema $schema): Schema
    {
        return PorteurProjForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PorteurProjInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PorteurProjsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPorteurProjs::route('/'),
            'create' => CreatePorteurProj::route('/create'),
            'view'   => ViewPorteurProj::route('/{record}'),
            'edit'   => EditPorteurProj::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['porteur.region', 'porteur.secteur', 'projet.statut', 'projet.guichet', 'projet.vague',
                    'benefs', 'paiements', 'formations.modules', 'formations.prestataires', 'partenaires']);
    }

    /**
     * Query par défaut de la table : eager loading agressif pour éviter le N+1.
     * Sans ça, chaque ligne de la table fait des dizaines de requêtes.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'porteur:id,raison_sociale,cnaps,nb_salaries,telephone,email,adresse,responsable_nom,region_id,secteur_id',
                'porteur.region:id,libelle',
                'porteur.secteur:id,libelle',
                'projet:id,reference,intitule,statut_projet_id,guichet_id,vague_id,secteur_id,region_id,date_debut,date_fin',
                'projet.statut:id,libelle',
                'projet.guichet:id,libelle',
                'projet.vague:id,libelle',
                'projet.secteur:id,libelle',
                'projet.region:id,libelle',
                'partenaires:id,porteur_proj_id,nom,cnaps,nb_salaries',
                'benefs:id,porteur_proj_id,type,total,h,f,jeunes,fpe,cadres',
                'paiements:id,porteur_proj_id,ligne,date_paiement,montant,is_annule',
                'formations',
                'formations.modules:id,intitule',
                'formations.prestataires:id,nom',
                'formations.formateurs:id,nom',
                'formations.formMods:id,formation_id,volume_horaire',
                'evaluateur:id,name',
            ]);
    }
}
