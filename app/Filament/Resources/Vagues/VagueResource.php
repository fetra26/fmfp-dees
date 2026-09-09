<?php

namespace App\Filament\Resources\Vagues;

use App\Filament\Resources\Vagues\Pages\CreateVague;
use App\Filament\Resources\Vagues\Pages\EditVague;
use App\Filament\Resources\Vagues\Pages\ListVagues;
use App\Filament\Resources\Vagues\Schemas\VagueForm;
use App\Filament\Resources\Vagues\Tables\VaguesTable;
use App\Models\Vague;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VagueResource extends Resource
{
    protected static ?string $model = Vague::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Référentiels';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Vague';
    protected static ?string $pluralModelLabel = 'Vagues';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

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
        return VagueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VaguesTable::configure($table);
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
            'index' => ListVagues::route('/'),
            'create' => CreateVague::route('/create'),
            'edit' => EditVague::route('/{record}/edit'),
        ];
    }
}
