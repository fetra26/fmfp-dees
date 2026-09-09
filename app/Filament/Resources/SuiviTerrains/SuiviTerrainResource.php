<?php

namespace App\Filament\Resources\SuiviTerrains;

use App\Filament\Resources\SuiviTerrains\Pages\CreateSuiviTerrain;
use App\Filament\Resources\SuiviTerrains\Pages\EditSuiviTerrain;
use App\Filament\Resources\SuiviTerrains\Pages\ListSuiviTerrains;
use App\Filament\Resources\SuiviTerrains\Schemas\SuiviTerrainForm;
use App\Filament\Resources\SuiviTerrains\Tables\SuiviTerrainsTable;
use App\Models\SuiviTerrain;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SuiviTerrainResource extends Resource
{
    protected static ?string $model = SuiviTerrain::class;
    protected static string | NITENUM | NULL $NAVIGATIONGROUP = 'Suivi';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $modelLabel = 'Suivi terrain';
    protected static ?string $pluralModelLabel = 'Suivis terrain';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    public static function form(Schema $schema): Schema
    {
        return SuiviTerrainForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuiviTerrainsTable::configure($table);
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
            'index' => ListSuiviTerrains::route('/'),
            'create' => CreateSuiviTerrain::route('/create'),
            'edit' => EditSuiviTerrain::route('/{record}/edit'),
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
