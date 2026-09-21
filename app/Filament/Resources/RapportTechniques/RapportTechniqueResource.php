<?php

namespace App\Filament\Resources\RapportTechniques;

use App\Filament\Resources\RapportTechniques\Pages\CreateRapportTechnique;
use App\Filament\Resources\RapportTechniques\Pages\EditRapportTechnique;
use App\Filament\Resources\RapportTechniques\Pages\ListRapportTechniques;
use App\Filament\Resources\RapportTechniques\Schemas\RapportTechniqueForm;
use App\Filament\Resources\RapportTechniques\Tables\RapportTechniquesTable;
use App\Models\RapportTechnique;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RapportTechniqueResource extends Resource
{
    protected static ?string $model = RapportTechnique::class;
    protected static \UnitEnum | string | null $navigationGroup = 'Évaluation';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $modelLabel = 'Rapport technique';
    protected static ?string $pluralModelLabel = 'Rapports techniques';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    public static function form(Schema $schema): Schema
    {
        return RapportTechniqueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RapportTechniquesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRapportTechniques::route('/'),
            'create' => CreateRapportTechnique::route('/create'),
            'edit' => EditRapportTechnique::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
