<?php

namespace App\Filament\Resources\Entreprises;

use App\Filament\Resources\Entreprises\Pages\CreateEntreprise;
use App\Filament\Resources\Entreprises\Pages\EditEntreprise;
use App\Filament\Resources\Entreprises\Pages\ListEntreprises;
use App\Filament\Resources\Entreprises\RelationManagers\ProjetsRelationManager;
use App\Filament\Resources\Entreprises\RelationManagers\ProjetsPartenairesRelationManager;
use App\Filament\Resources\Entreprises\Schemas\EntrepriseForm;
use App\Filament\Resources\Entreprises\Tables\EntreprisesTable;
use App\Models\Entreprise;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EntrepriseResource extends Resource
{
    protected static ?string $model = Entreprise::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Suivi des projets';
    protected static ?string $modelLabel = 'Entreprise';
    protected static ?string $pluralModelLabel = 'Entreprises';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    public static function form(Schema $schema): Schema
    {
        return EntrepriseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EntreprisesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProjetsRelationManager::class,
            ProjetsPartenairesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListEntreprises::route('/'),
            'create' => CreateEntreprise::route('/create'),
            'edit'   => EditEntreprise::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
