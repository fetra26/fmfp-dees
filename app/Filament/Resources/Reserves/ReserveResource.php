<?php

namespace App\Filament\Resources\Reserves;

use App\Filament\Resources\Reserves\Pages\CreateReserve;
use App\Filament\Resources\Reserves\Pages\EditReserve;
use App\Filament\Resources\Reserves\Pages\ListReserves;
use App\Filament\Resources\Reserves\Schemas\ReserveForm;
use App\Filament\Resources\Reserves\Tables\ReservesTable;
use App\Models\Reserve;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReserveResource extends Resource
{
    protected static ?string $model = Reserve::class;
    protected static \UnitEnum | string | null $navigationGroup = 'Évaluation';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $modelLabel = 'Réserve';
    protected static ?string $pluralModelLabel = 'Réserves';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    public static function form(Schema $schema): Schema
    {
        return ReserveForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReservesTable::configure($table);
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
            'index' => ListReserves::route('/'),
            'create' => CreateReserve::route('/create'),
            'edit' => EditReserve::route('/{record}/edit'),
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
