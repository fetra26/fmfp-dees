<?php

namespace App\Filament\Resources\Relances;

use App\Filament\Resources\Relances\Pages\CreateRelance;
use App\Filament\Resources\Relances\Pages\EditRelance;
use App\Filament\Resources\Relances\Pages\ListRelances;
use App\Filament\Resources\Relances\Schemas\RelanceForm;
use App\Filament\Resources\Relances\Tables\RelancesTable;
use App\Models\Relance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RelanceResource extends Resource
{
    protected static ?string $model = Relance::class;
    protected static string | NITENUM | NULL $NAVIGATIONGROUP = 'Suivi';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $modelLabel = 'Relance';
    protected static ?string $pluralModelLabel = 'Relances';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    public static function form(Schema $schema): Schema
    {
        return RelanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RelancesTable::configure($table);
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
            'index' => ListRelances::route('/'),
            'create' => CreateRelance::route('/create'),
            'edit' => EditRelance::route('/{record}/edit'),
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
