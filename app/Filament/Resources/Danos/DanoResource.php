<?php

namespace App\Filament\Resources\Danos;

use App\Filament\Resources\Danos\Pages\CreateDano;
use App\Filament\Resources\Danos\Pages\EditDano;
use App\Filament\Resources\Danos\Pages\ListDanos;
use App\Filament\Resources\Danos\Schemas\DanoForm;
use App\Filament\Resources\Danos\Tables\DanosTable;
use App\Models\Dano;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DanoResource extends Resource
{
    protected static ?string $model = Dano::class;
    protected static string | NITENUM | NULL $NAVIGATIONGROUP = 'Cœur métier';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $modelLabel = 'DANO';
    protected static ?string $pluralModelLabel = 'DANOs';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboard;

    public static function form(Schema $schema): Schema
    {
        return DanoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DanosTable::configure($table);
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
            'index' => ListDanos::route('/'),
            'create' => CreateDano::route('/create'),
            'edit' => EditDano::route('/{record}/edit'),
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
