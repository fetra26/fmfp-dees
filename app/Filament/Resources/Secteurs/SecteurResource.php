<?php

namespace App\Filament\Resources\Secteurs;

use App\Filament\Resources\Secteurs\Pages\CreateSecteur;
use App\Filament\Resources\Secteurs\Pages\EditSecteur;
use App\Filament\Resources\Secteurs\Pages\ListSecteurs;
use App\Filament\Resources\Secteurs\Schemas\SecteurForm;
use App\Filament\Resources\Secteurs\Tables\SecteursTable;
use App\Models\Secteur;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SecteurResource extends Resource
{
    protected static ?string $model = Secteur::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Référentiels';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Secteur';
    protected static ?string $pluralModelLabel = 'Secteurs';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

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
        return SecteurForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SecteursTable::configure($table);
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
            'index' => ListSecteurs::route('/'),
            'create' => CreateSecteur::route('/create'),
            'edit' => EditSecteur::route('/{record}/edit'),
        ];
    }
}
