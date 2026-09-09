<?php

namespace App\Filament\Resources\Guichets;

use App\Filament\Resources\Guichets\Pages\CreateGuichet;
use App\Filament\Resources\Guichets\Pages\EditGuichet;
use App\Filament\Resources\Guichets\Pages\ListGuichets;
use App\Filament\Resources\Guichets\Schemas\GuichetForm;
use App\Filament\Resources\Guichets\Tables\GuichetsTable;
use App\Models\Guichet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GuichetResource extends Resource
{
    protected static ?string $model = Guichet::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Référentiels';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Guichet';
    protected static ?string $pluralModelLabel = 'Guichets';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return GuichetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GuichetsTable::configure($table);
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
            'index' => ListGuichets::route('/'),
            'create' => CreateGuichet::route('/create'),
            'edit' => EditGuichet::route('/{record}/edit'),
        ];
    }
}
